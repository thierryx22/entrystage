<?php
/**
 * Reads the list state from the request (page, sort, search, filters) and fetches the entries
 * through the Gravity Forms API.
 *
 * Query arguments are only read when "estg_f" matches the form id, so several lists can live
 * on the same page.
 *
 * @package EntryStage
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Query {

	/**
	 * List state for a form.
	 *
	 * @param array $form     Form object.
	 * @param array $settings Form settings.
	 * @param array $columns  Unused (the columns are read from the settings); kept for compatibility.
	 * @param array $atts     Shortcode attributes (may force a status or the page size).
	 * @return array
	 */
	public static function state( $form, array $settings, array $columns = array(), array $atts = array() ) {
		$layout = Settings::layout( $form, $settings );
		// "defaults": the state as the list opens (used for the counters of the personal space).
		$mine = empty( $atts['defaults'] ) && isset( $_GET['estg_f'] ) && absint( wp_unslash( $_GET['estg_f'] ) ) === (int) $form['id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list state.
		$get  = function ( $key ) use ( $mine ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list state.
			return $mine && isset( $_GET[ $key ] ) && ! is_array( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		};

		// Sorting is allowed on the columns marked sortable, on the initial sort key and on the entry date and id.
		$sortable = array( 'date_created', 'date_updated', 'id', (string) $settings['sort_key'] );
		foreach ( $layout as $item ) {
			if ( '' !== $item['sort_key'] ) {
				$sortable[] = $item['sort_key'];
			}
		}
		$orderby = Settings::clean_key( $get( 'estg_o' ) );
		if ( '' === $orderby || ! in_array( $orderby, $sortable, true ) ) {
			$orderby = in_array( $settings['sort_key'], $sortable, true ) ? $settings['sort_key'] : 'date_created';
		}
		$dir = strtoupper( $get( 'estg_d' ) );
		if ( 'ASC' !== $dir && 'DESC' !== $dir ) {
			$dir = $settings['sort_dir'];
		}

		$per_page = absint( $get( 'estg_pp' ) );
		$choices  = array_unique( array_merge( (array) $settings['per_page_choices'], array( (int) $settings['per_page'] ) ) );
		if ( ! in_array( $per_page, $choices, true ) ) {
			$per_page = isset( $atts['per_page'] ) && absint( $atts['per_page'] ) ? absint( $atts['per_page'] ) : (int) $settings['per_page'];
		}

		$status = '';
		$all    = false;

		$filters = array();

		$board = false;

		return array(
			'user'     => isset( $atts['user_filter'] ) ? $atts['user_filter'] : null,
			'fixed'    => isset( $atts['fixed'] ) ? (array) $atts['fixed'] : array(),
			'page'     => max( 1, absint( $get( 'estg_p' ) ) ),
			'per_page' => max( 1, $per_page ),
			'orderby'  => $orderby,
			'dir'      => $dir,
			'search'   => ! empty( $settings['search'] ) ? $get( 'estg_s' ) : '',
			'status'   => $status,
			'status_all' => $all,
			'from'     => '',
			'to'       => '',
			'filters'  => $filters,
			'board'    => $board,
		);
	}

	/**
	 * Fixed filters written in the shortcode: user="5" or user="current", and field="5" value="Test",
	 * field2="…" value2="…", field3… (no limit). Visitors cannot change them.
	 *
	 * @param array $raw Raw shortcode attributes.
	 * @return array array( 'user' => int|null, 'fields' => array( key => value ) ).
	 */
	public static function shortcode_filters( array $raw ) {
		$user = null;
		if ( isset( $raw['user'] ) && '' !== trim( (string) $raw['user'] ) ) {
			$value = strtolower( trim( (string) $raw['user'] ) );
			if ( 'current' === $value ) {
				$user = is_user_logged_in() ? get_current_user_id() : -1;   // -1: nobody.
			} elseif ( ctype_digit( $value ) ) {
				$user = (int) $value;
			}
		}
		$fields = array();
		return array(
			'user'   => $user,
			'fields' => $fields,
		);
	}

	/**
	 * Gravity Forms search criteria.
	 *
	 * @param array $form       Form object.
	 * @param array $settings   Form settings.
	 * @param array $state      List state.
	 * @param bool  $use_status Apply the status filter (false for the tab counters).
	 * @return array
	 */
	public static function criteria( $form, array $settings, array $state, $use_status = true ) {
		$criteria = array(
			'status'        => 'active',
			'field_filters' => array( 'mode' => 'all' ),
		);
		if ( null !== $state['user'] ) {
			// Shortcode attribute user="…": the entries of this user only.
			$criteria['field_filters'][] = array(
				'key'   => 'created_by',
				'value' => (int) $state['user'],
			);
		} elseif ( Access::own_only( $settings ) ) {
			$criteria['field_filters'][] = array(
				'key'   => 'created_by',
				'value' => get_current_user_id(),
			);
		}
		if ( '' !== $state['search'] ) {
			$criteria['field_filters'][] = array(
				'operator' => 'contains',
				'value'    => $state['search'],
			);
		}
		/**
		 * Filters the search criteria of the list.
		 *
		 * @param array $criteria Search criteria.
		 * @param array $form     Form object.
		 * @param array $state    List state.
		 */
		return apply_filters( 'estg_search_criteria', $criteria, $form, $state );
	}

	/**
	 * Sorting argument.
	 *
	 * @param array $form  Form object.
	 * @param array $state List state.
	 * @return array
	 */
	public static function sorting( $form, array $state ) {
		$numeric = 'id' === $state['orderby'];
		if ( ! $numeric && preg_match( '/^\d+/', $state['orderby'], $m ) ) {
			$field   = \GFAPI::get_field( $form, (int) $m[0] );
			$numeric = $field && in_array( $field->get_input_type(), array( 'number', 'total', 'quantity', 'price', 'calculation' ), true );
		}
		return array(
			'key'        => $state['orderby'],
			'direction'  => $state['dir'],
			'is_numeric' => $numeric,
		);
	}

	/**
	 * Entries of the current page.
	 *
	 * @param array $form     Form object.
	 * @param array $settings Form settings.
	 * @param array $state    List state.
	 * @return array array( 'entries' => array, 'total' => int ).
	 */
	public static function run( $form, array $settings, array $state ) {
		$total   = 0;
		$entries = \GFAPI::get_entries(
			(int) $form['id'],
			self::criteria( $form, $settings, $state ),
			self::sorting( $form, $state ),
			array(
				'offset'    => ( $state['page'] - 1 ) * $state['per_page'],
				'page_size' => $state['per_page'],
			),
			$total
		);
		if ( is_wp_error( $entries ) ) {
			$entries = array();
		}
		return array(
			'entries' => $entries,
			'total'   => (int) $total,
		);
	}

}
