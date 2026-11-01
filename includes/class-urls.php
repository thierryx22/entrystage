<?php
/**
 * Links and nonces used by the list.
 *
 * @package EntryStage
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Urls {

	/**
	 * Address of the current page without the list arguments.
	 *
	 * @return string
	 */
	public static function current_page() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$url = home_url( $uri );
		return remove_query_arg( self::own_args(), $url );
	}

	/**
	 * Query arguments owned by the plugin.
	 *
	 * @return string[]
	 */
	public static function own_args() {
		return array( 'estg_f', 'estg_p', 'estg_pp', 'estg_s', 'estg_st', 'estg_o', 'estg_d', 'estg_from', 'estg_to', 'estg_c', 'estg_v', 'estg_view', 'estg_msg', 'estg_n', 'estg_action', 'estg_entry', 'estg_nonce', 'estg_return', 'estg_export', 'estg_sc', 'estg_print' );
	}

	/**
	 * List address keeping the current state, with changes.
	 *
	 * @param int   $form_id Form id.
	 * @param array $state   List state.
	 * @param array $changes Arguments to change (null removes one).
	 * @return string
	 */
	public static function list_url( $form_id, array $state, array $changes = array() ) {
		$args = array(
			'estg_f'    => (int) $form_id,
			'estg_p'    => $state['page'] > 1 ? $state['page'] : null,
			'estg_pp'   => $state['per_page'],
			'estg_s'    => '' !== $state['search'] ? $state['search'] : null,
			'estg_st'   => '' !== $state['status'] ? $state['status'] : ( ! empty( $state['status_all'] ) ? '_all' : null ),
			'estg_o'    => $state['orderby'],
			'estg_d'    => $state['dir'],
			'estg_from' => '' !== $state['from'] ? $state['from'] : null,
			'estg_to'   => '' !== $state['to'] ? $state['to'] : null,
			'estg_c'    => ! empty( $state['filters'] ) ? $state['filters'] : null,
			'estg_v'    => ! empty( $state['board'] ) ? 'board' : null,
		);
		$args = array_merge( $args, $changes );
		$args = array_filter(
			$args,
			function ( $v ) {
				return null !== $v && '' !== $v;
			}
		);
		return add_query_arg( urlencode_deep( $args ), self::current_page() );
	}

	/**
	 * Nonce action for an entry action.
	 *
	 * @param string $action   Action.
	 * @param int    $entry_id Entry id (0 for list-wide actions).
	 * @return string
	 */
	public static function nonce_action( $action, $entry_id = 0 ) {
		return 'estg_' . $action . '_' . (int) $entry_id;
	}

	/**
	 * Link that opens the form page with an entry loaded (edit or duplicate).
	 *
	 * @param array  $settings Form settings.
	 * @param string $action   edit | duplicate.
	 * @param int    $entry_id Entry id.
	 * @param string $return   List address to come back to.
	 * @return string '' when no form page is set.
	 */
	public static function form_url( array $settings, $action, $entry_id, $return ) {
		$status = empty( $settings['page_id'] ) ? '' : get_post_status( (int) $settings['page_id'] );
		if ( 'publish' === $status || 'private' === $status ) {
			$page = get_permalink( $settings['page_id'] );
		} elseif ( ! empty( $settings['page_url'] ) ) {
			$page = $settings['page_url'];
		} else {
			return '';
		}
		return add_query_arg(
			array(
				'estg_action' => $action,
				'estg_entry'  => (int) $entry_id,
				'estg_nonce'  => wp_create_nonce( self::nonce_action( $action, $entry_id ) ),
				'estg_return' => rawurlencode( $return ),   // add_query_arg() does not encode values.
			),
			$page
		);
	}

	/**
	 * Safe return address (same site only).
	 *
	 * @param string $url      Candidate.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	public static function safe_return( $url, $fallback ) {
		$url = esc_url_raw( (string) $url );
		return '' !== $url ? wp_validate_redirect( $url, $fallback ) : $fallback;
	}
}
