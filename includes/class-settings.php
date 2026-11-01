<?php
/**
 * Normalised list settings for a form, with defaults.
 *
 * @package EntryStage
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	/** Add-on slug, also the key under which Gravity Forms stores the form settings. */
	const SLUG = 'entrystage';

	/** @var array Cache per form id. */
	private static $cache = array();

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'            => false,
			'access'             => 'loggedin',   // everyone | loggedin | roles.
			'access_roles'       => array(),
			'scope'              => 'own',        // own | all.
			'manager_roles'      => array( 'administrator' ),
			'page_id'            => 0,
			'page_url'           => '',           // Address of the form page when it is not a WordPress page (shortcode page="…").
			'per_page'           => 25,
			'per_page_choices'   => array( 10, 25, 50, 100 ),
			'sort_key'           => 'date_created',
			'sort_dir'           => 'DESC',
			'empty_text'         => __( 'No entries yet.', 'entrystage' ),
			'show_date'          => true,
			'column_layout'      => array(),      // Earliest builds only: ordered list of array( 'key', 'width' ). Read once to build "columns".
			'columns'            => null,         // list of array( key, label, mode, width, sort, filter ). null: not set yet.
			'details'            => array(),      // fields of "More details", list of array( key, label, mode ).
			'details_open'       => __( 'More details', 'entrystage' ),
			'details_close'      => __( 'Fewer details', 'entrystage' ),
			'details_expand_all' => false,        // Removed option (kept for old settings).
			'details_export'     => true,         // the detail fields always follow the columns in the CSV export.
			'view_icon'          => '',           // attachment id of a picture used instead of the built-in icon.
			'edit_icon'          => '',
			'duplicate_icon'     => '',
			'archive_icon'       => '',
			'pdf_icon'           => '',
			'next_icon'          => '',
			'thumb_size'         => 48,           // 48 | 96 pixels.
			'secure_files'       => false,        // File links only open for people who may see the entry.
			'actions_header'     => __( 'Actions', 'entrystage' ),
			'view'               => true,
			'view_label'         => __( 'View', 'entrystage' ),
			'edit'               => true,
			'edit_label'         => __( 'Edit', 'entrystage' ),
			'duplicate'          => false,
			'duplicate_label'    => __( 'Duplicate', 'entrystage' ),
			'archive'            => false,
			'archive_label'      => __( 'Archive', 'entrystage' ),
			'archive_mode'       => 'trash',      // trash | delete.
			'archive_confirm'    => __( 'Archive this entry?', 'entrystage' ),
			'pdf'                => false,
			'pdf_label'          => __( 'PDF', 'entrystage' ),
			'pdf_id'             => '',
			'edit_notifications' => 'send',       // send | skip.
			'after_edit'         => 'list',       // list | confirmation.
			'status_field'       => '',
			'statuses'           => array(),      // list of array( value, label, color ).
			'status_tabs'        => true,
			'status_next'        => false,
			'status_place'       => 'actions',    // actions | column.
			'search'             => true,
			'print'              => false,
			'ajax'               => true,          // search, sorting and pages without reloading the page.
			'column_filters'     => false,
			'date_filter'        => false,
			'export'             => false,
			'bulk'               => false,
			'action_display'     => 'icons',      // icons | both | text.
		);
	}

	/**
	 * Settings of a form.
	 *
	 * @param array $form Gravity Forms form object.
	 * @return array
	 */
	public static function get( $form ) {
		$form_id = (int) rgar( $form, 'id' );
		if ( isset( self::$cache[ $form_id ] ) ) {
			return self::$cache[ $form_id ];
		}
		$raw = rgar( $form, self::SLUG );
		if ( is_array( $raw ) && ! empty( $raw ) ) {
			$settings = self::from_addon( $raw, $form );
		} else {
			$settings = self::defaults();
		}
		if ( null === $settings['columns'] ) {
			// Not saved from the settings page yet: columns come from the field options of the first builds.
			$settings['columns']          = self::columns_from_v1( $form, $settings );
			$settings['columns_migrated'] = true;
		}
		/**
		 * Filters the list settings of a form.
		 *
		 * @param array $settings Normalised settings.
		 * @param array $form     Form object.
		 */
		$settings = apply_filters( 'estg_settings', $settings, $form );
		$settings['form_id'] = $form_id;
		if ( ! self::page_is_valid( $settings ) ) {
			// No form page chosen (or the page was removed): look for a page that displays the form.
			$settings['page_id']   = self::detect_page( $form_id );
			$settings['page_auto'] = true;
		}
		self::$cache[ $form_id ] = $settings;
		return $settings;
	}

	/** Clears the cache (after a settings save). */
	public static function flush() {
		self::$cache = array();
	}

	/**
	 * Settings built from raw settings-page values (used to redraw the tab right after a save).
	 *
	 * @param array $raw  Raw values.
	 * @param array $form Form object.
	 * @return array
	 */
	public static function from_raw( array $raw, $form ) {
		$settings = self::from_addon( $raw, $form );
		if ( null === $settings['columns'] ) {
			$settings['columns'] = self::columns_from_v1( $form, $settings );
		}
		$settings['form_id'] = (int) rgar( $form, 'id' );
		return $settings;
	}

	/**
	 * Reads the values saved by the add-on settings page.
	 *
	 * @param array $raw  Raw saved values.
	 * @param array $form Form object.
	 * @return array
	 */
	private static function from_addon( array $raw, $form ) {
		$d   = self::defaults();
		$on  = function ( $key ) use ( $raw ) {
			return '1' === (string) rgar( $raw, $key );
		};
		$txt = function ( $key ) use ( $raw, $d ) {
			$v = trim( (string) rgar( $raw, $key ) );
			return '' !== $v ? $v : $d[ $key ];
		};
		$s = $d;

		$s['enabled']        = $on( 'enabled' );
		$s['access']         = in_array( rgar( $raw, 'access' ), array( 'everyone', 'loggedin', 'roles' ), true ) ? $raw['access'] : $d['access'];
		$s['access_roles']   = self::checked_roles( $raw, 'access_role_' );
		$s['scope']          = 'all' === rgar( $raw, 'scope' ) ? 'all' : 'own';
		$s['manager_roles']  = array_values( array_unique( array_merge( array( 'administrator' ), self::checked_roles( $raw, 'manager_role_' ) ) ) );
		$s['page_id']        = absint( rgar( $raw, 'page_id' ) );
		$s['per_page']       = max( 1, min( 500, absint( rgar( $raw, 'per_page' ) ) ? absint( $raw['per_page'] ) : $d['per_page'] ) );
		$s['sort_key']       = self::clean_key( rgar( $raw, 'sort_key' ) ) ? self::clean_key( $raw['sort_key'] ) : $d['sort_key'];
		$s['sort_dir']       = 'ASC' === strtoupper( (string) rgar( $raw, 'sort_dir' ) ) ? 'ASC' : 'DESC';
		$s['empty_text']     = $txt( 'empty_text' );
		$s['show_date']      = $on( 'show_date' );
		$s['column_layout']  = self::parse_layout( rgar( $raw, 'column_layout' ) );
		$s['actions_header'] = $txt( 'actions_header' );

		$actions = array( 'view', 'edit', 'duplicate', 'archive' );
		foreach ( $actions as $action ) {
			$s[ $action ]              = $on( $action );
			$s[ $action . '_label' ]   = $txt( $action . '_label' );
		}
		$s['archive_mode']       = 'delete' === rgar( $raw, 'archive_mode' ) ? 'delete' : 'trash';
		$s['archive_confirm']    = $txt( 'archive_confirm' );
		$s['edit_notifications'] = 'skip' === rgar( $raw, 'edit_notifications' ) ? 'skip' : 'send';
		$s['after_edit']         = 'confirmation' === rgar( $raw, 'after_edit' ) ? 'confirmation' : 'list';

		$s['search']         = $on( 'search' );
		$s['print'] = $on( 'print' );
		$s['ajax'] = array_key_exists( 'ajax', $raw ) ? $on( 'ajax' ) : true;   // on for the lists saved before the option.
		$s['action_display'] = in_array( rgar( $raw, 'action_display' ), array( 'icons', 'both', 'text' ), true ) ? $raw['action_display'] : $d['action_display'];

		if ( isset( $raw['columns'] ) ) {
			$s['columns'] = self::parse_columns( $raw['columns'], $form, $s );
			$keys         = wp_list_pluck( $s['columns'], 'key' );
			$s['show_date'] = in_array( '_date', $keys, true );
		}
		$s['thumb_size']         = 96 === absint( rgar( $raw, 'thumb_size' ) ) ? 96 : 48;
		$s['secure_files']       = $on( 'secure_files' );
		return $s;
	}

	/**
	 * Roles checked in a group of checkboxes named "<prefix><role>".
	 *
	 * @param array  $raw    Raw values.
	 * @param string $prefix Checkbox name prefix.
	 * @return string[]
	 */
	private static function checked_roles( array $raw, $prefix ) {
		$roles = array();
		foreach ( $raw as $key => $value ) {
			if ( 0 === strpos( (string) $key, $prefix ) && '1' === (string) $value ) {
				$roles[] = sanitize_key( substr( $key, strlen( $prefix ) ) );
			}
		}
		return $roles;
	}

	/**
	 * The chosen form page exists and is published or private (or a custom address is set).
	 *
	 * @param array $settings Settings.
	 * @return bool
	 */
	public static function page_is_valid( array $settings ) {
		if ( ! empty( $settings['page_url'] ) ) {
			return true;
		}
		$status = empty( $settings['page_id'] ) ? '' : get_post_status( (int) $settings['page_id'] );
		return 'publish' === $status || 'private' === $status;
	}

	/**
	 * First published or private page (then post) whose content displays the form, through the
	 * [gravityform] shortcode or the Gravity Forms block. Cached for one hour.
	 *
	 * @param int $form_id Form id.
	 * @return int Page id, 0 when none.
	 */
	public static function detect_page( $form_id ) {
		$form_id = (int) $form_id;
		$key     = 'estg_form_page_' . $form_id;
		$cached  = get_transient( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		global $wpdb;
		$found = 0;
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one query, cached below.
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status IN ('publish','private') AND post_type NOT IN ('revision','nav_menu_item','attachment') AND ( post_content LIKE %s OR post_content LIKE %s ) ORDER BY ( post_type = 'page' ) DESC, ID ASC LIMIT 500",
				'%' . $wpdb->esc_like( '[gravityform' ) . '%',
				'%' . $wpdb->esc_like( 'gravityforms/form' ) . '%'
			)
		);
		foreach ( (array) $rows as $row ) {
			$content = (string) $row->post_content;
			if ( preg_match( '/\[gravityforms?\b[^\]]*\bid\s*=\s*["\']?' . $form_id . '\b/i', $content )
				|| preg_match( '/"formId"\s*:\s*"?' . $form_id . '\b/', $content ) ) {
				$found = (int) $row->ID;
				break;
			}
		}
		set_transient( $key, $found, HOUR_IN_SECONDS );
		return $found;
	}

	/**
	 * Finds the form page from a page id, a path ("contact/form") or an address of this site.
	 *
	 * @param mixed $value Id, path or address.
	 * @return array array( page id, address ) — id 0 with an address when the address is not a page;
	 *               array( 0, '' ) when nothing valid was found (foreign sites are refused).
	 */
	public static function resolve_page( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return array( 0, '' );
		}
		if ( ctype_digit( $value ) ) {
			$post = get_post( (int) $value );
		} else {
			$url = false === strpos( $value, ':' ) ? home_url( '/' . ltrim( $value, '/' ) ) : $value;
			$url = wp_validate_redirect( esc_url_raw( $url ), '' );
			if ( '' === $url ) {
				return array( 0, '' );
			}
			$id   = url_to_postid( $url );
			$post = $id ? get_post( $id ) : null;
			if ( ! $post ) {
				$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
				$post = '' !== $path ? get_page_by_path( $path ) : null;
			}
			if ( ! $post ) {
				return array( 0, $url );
			}
		}
		if ( ! $post || ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
			return array( 0, '' );
		}
		return array( (int) $post->ID, '' );
	}

	/**
	 * Reads the saved column layout (JSON written by the settings tab).
	 *
	 * @param mixed $raw JSON string or array.
	 * @return array List of array( 'key' => string, 'width' => string ).
	 */
	public static function parse_layout( $raw ) {
		$list = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		$out  = array();
		$seen = array();
		foreach ( (array) $list as $item ) {
			$key = is_array( $item ) ? (string) rgar( $item, 'key' ) : '';
			if ( ! in_array( $key, array( '_status', '_date' ), true ) ) {
				$key = preg_match( '/^\d+$/', $key ) ? $key : '';
			}
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = array(
				'key'   => $key,
				'width' => self::clean_width( rgar( $item, 'width' ) ),
			);
		}
		return $out;
	}

	/**
	 * Accepts a CSS width such as "120px", "15%", "10em", "12rem" or "20ch" (a bare number means pixels).
	 *
	 * @param mixed $width Raw value.
	 * @return string Clean width or ''.
	 */
	public static function clean_width( $width ) {
		$width = strtolower( str_replace( ' ', '', (string) $width ) );
		if ( preg_match( '/^\d{1,4}(\.\d{1,2})?$/', $width ) ) {
			$width .= 'px';
		}
		return preg_match( '/^\d{1,4}(\.\d{1,2})?(px|%|em|rem|ch)$/', $width ) && (float) $width > 0 ? $width : '';
	}

	/**
	 * Builds the column list from the settings of the first builds: fields ticked "Show in the entry list" in
	 * the form editor, then status and date, in the order and with the widths saved then.
	 *
	 * @param array $form     Form object.
	 * @param array $settings Settings (keys of the first builds).
	 * @return array
	 */
	public static function columns_from_v1( $form, array $settings ) {
		$items = array();
		foreach ( Fields::listable( $form ) as $id => $field ) {
			if ( empty( $field->estgShowInList ) ) {
				continue;
			}
			$mode           = Fields::default_mode( $field );
			$items[ $id ] = array(
				'key'    => (string) $id,
				'label'  => isset( $field->estgColumnLabel ) ? sanitize_text_field( (string) $field->estgColumnLabel ) : '',
				'mode'   => $mode,
				'width'  => '',
				'sort'   => '' !== Fields::sort_key( $field, $mode ),
				'filter' => ! empty( $settings['column_filters'] ) && Fields::is_filterable( $field ),
			);
		}
		if ( ! empty( $settings['show_date'] ) ) {
			$items['_date'] = self::fixed_item( '_date' );
		}
		$ordered = array();
		foreach ( (array) $settings['column_layout'] as $saved ) {
			if ( isset( $items[ $saved['key'] ] ) ) {
				$item          = $items[ $saved['key'] ];
				$item['width'] = $saved['width'];
				$ordered[]     = $item;
				unset( $items[ $saved['key'] ] );
			}
		}
		return array_merge( $ordered, array_values( $items ) );
	}

	/**
	 * Status or date column.
	 *
	 * @param string $key _status | _date.
	 * @return array
	 */
	private static function fixed_item( $key ) {
		return array(
			'key'    => $key,
			'label'  => '',
			'mode'   => 'default',
			'width'  => '',
			'sort'   => true,
			'filter' => false,
			'board'  => true,
		);
	}

	/**
	 * Cleans the columns posted by the settings page (or saved in the form).
	 *
	 * @param mixed $raw      JSON string or array.
	 * @param array $form     Form object.
	 * @param array $settings Settings (status configuration).
	 * @return array
	 */
	public static function parse_columns( $raw, $form, array $settings ) {
		$list   = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		$fields = Fields::listable( $form );
		$out    = array();
		$seen   = array();
		foreach ( (array) $list as $item ) {
			$key = is_array( $item ) ? (string) rgar( $item, 'key' ) : '';
			if ( '' === $key || isset( $seen[ $key ] ) || ( ! isset( $fields[ $key ] ) && ! in_array( $key, array( '_status', '_date' ), true ) ) ) {
				continue;
			}
			$seen[ $key ] = true;
			if ( isset( $fields[ $key ] ) ) {
				$field = $fields[ $key ];
				$modes = Fields::modes( $field );
				$mode  = isset( $modes[ (string) rgar( $item, 'mode' ) ] ) ? (string) $item['mode'] : Fields::default_mode( $field );
				$clean = array(
					'key'    => $key,
					'label'  => self::short_text( rgar( $item, 'label' ) ),
					'mode'   => $mode,
					'width'  => self::clean_width( rgar( $item, 'width' ) ),
					'sort'   => ! empty( $item['sort'] ) && '' !== Fields::sort_key( $field, $mode ),
					'filter' => ! empty( $item['filter'] ) && Fields::is_filterable( $field ),
					// shown on the cards of the board (Kanban); on unless turned off
					'board'  => ! array_key_exists( 'board', (array) $item ) || ! empty( $item['board'] ),
				);
			} else {
				$clean          = self::fixed_item( $key );
				$clean['board'] = ! array_key_exists( 'board', (array) $item ) || ! empty( $item['board'] );
				$clean['label'] = self::short_text( rgar( $item, 'label' ) );
				$clean['width'] = self::clean_width( rgar( $item, 'width' ) );
				$clean['sort']  = ! array_key_exists( 'sort', (array) $item ) || ! empty( $item['sort'] );
			}
			$out[] = $clean;
		}
		return $out;
	}

	/**
	 * Short label typed in the settings (100 characters at most).
	 *
	 * @param mixed $text Raw text.
	 * @return string
	 */
	private static function short_text( $text ) {
		$text = sanitize_text_field( (string) $text );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 100 ) : substr( $text, 0, 100 );
	}

	/**
	 * Columns of the list in display order: form fields, status and date.
	 *
	 * @param array $form     Form object.
	 * @param array $settings Form settings.
	 * @return array List of array( 'key', 'kind' => field|status|date, 'label', 'width', 'mode',
	 *               'sort_key' ('' when not sortable), 'filter', 'column' (id, label, field) ).
	 */
	public static function layout( $form, array $settings ) {
		$fields     = Fields::listable( $form );
		$has_status = false;
		$out        = array();
		$columns    = is_array( $settings['columns'] ) ? $settings['columns'] : self::columns_from_v1( $form, $settings );
		if ( $has_status && ! in_array( '_status', wp_list_pluck( $columns, 'key' ), true ) ) {
			$columns[] = self::fixed_item( '_status' );
		}
		foreach ( $columns as $item ) {
			$key = (string) $item['key'];
			if ( '_status' === $key ) {
				if ( ! $has_status ) {
					continue;
				}
			} elseif ( '_date' === $key ) {
				$out[] = array(
					'key'      => $key,
					'kind'     => 'date',
					'label'    => '' !== $item['label'] ? $item['label'] : __( 'Date', 'entrystage' ),
					'width'    => $item['width'],
					'mode'     => 'default',
					'sort_key' => ! empty( $item['sort'] ) ? 'date_created' : '',
					'filter'   => false,
					'board'    => ! isset( $item['board'] ) || ! empty( $item['board'] ),
					'column'   => null,
				);
			} elseif ( isset( $fields[ $key ] ) ) {
				$field = $fields[ $key ];
				$label = '' !== $item['label'] ? $item['label'] : Fields::label( $field );
				$out[] = array(
					'key'      => $key,
					'kind'     => 'field',
					'label'    => $label,
					'width'    => $item['width'],
					'mode'     => $item['mode'],
					'sort_key' => ! empty( $item['sort'] ) ? Fields::sort_key( $field, $item['mode'] ) : '',
					'filter'   => ! empty( $item['filter'] ) && Fields::is_filterable( $field ),
					'board'    => ! isset( $item['board'] ) || ! empty( $item['board'] ),
					'column'   => array(
						'id'    => $key,
						'label' => $label,
						'field' => $field,
					),
				);
			}
		}
		/**
		 * Filters the columns in display order.
		 *
		 * @param array $layout   Columns.
		 * @param array $form     Form object.
		 * @param array $settings Form settings.
		 */
		return (array) apply_filters( 'estg_layout', $out, $form, $settings );
	}


	/**
	 * Accepts "date_created", "id", a field id ("12") or an input id ("12.3").
	 *
	 * @param mixed $key Raw key.
	 * @return string Clean key or ''.
	 */
	public static function clean_key( $key ) {
		$key = trim( (string) $key );
		if ( in_array( $key, array( 'date_created', 'date_updated', 'id' ), true ) ) {
			return $key;
		}
		return preg_match( '/^\d+(\.\d+)?$/', $key ) ? $key : '';
	}

	/**
	 * Field columns of the list (kept for add-ons written for the first builds).
	 *
	 * @param array      $form     Form object.
	 * @param array|null $settings Form settings (read when omitted).
	 * @return array List of array( 'id', 'label', 'field' ).
	 */
	public static function columns( $form, $settings = null ) {
		$settings = is_array( $settings ) ? $settings : self::get( $form );
		$columns  = array();
		foreach ( self::layout( $form, $settings ) as $item ) {
			if ( 'field' === $item['kind'] ) {
				$columns[] = $item['column'];
			}
		}
		/**
		 * Filters the field columns of the list.
		 *
		 * @param array $columns Columns.
		 * @param array $form    Form object.
		 */
		return apply_filters( 'estg_columns', $columns, $form );
	}
}
