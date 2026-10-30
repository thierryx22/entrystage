<?php
/**
 * Front-end output: the list (shortcode) and the read-only entry view.
 *
 * @package EntryStage
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Renderer {

	/**
	 * Shortcode [entrystage form="12"] (id="12" is accepted too).
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$raw     = is_array( $atts ) ? $atts : array();
		if ( ! empty( $raw['forms'] ) ) {
			return self::space( $raw );
		}
		$atts    = shortcode_atts(
			array(
				'form'     => 0,
				'id'       => 0,
				'status'   => '',
				'per_page' => 0,
				'page'     => '',
			),
			$raw,
			'entrystage'
		);
		$fixed               = Query::shortcode_filters( $raw );
		$atts['user_filter'] = $fixed['user'];
		$atts['fixed']       = $fixed['fields'];
		$form_id = absint( $atts['form'] ? $atts['form'] : $atts['id'] );
		$form    = $form_id ? \GFAPI::get_form( $form_id ) : false;
		if ( ! $form || empty( $form['is_active'] ) || ! empty( $form['is_trash'] ) ) {
			return '';
		}
		$settings = Settings::get( $form );
		if ( '' !== trim( (string) $atts['page'] ) ) {
			// page="…": form page of this list (page id, path or address of this site).
			list( $page_id, $page_url ) = Settings::resolve_page( $atts['page'] );
			if ( $page_id || '' !== $page_url ) {
				$settings['page_id']  = $page_id;
				$settings['page_url'] = $page_url;
			}
		}
		if ( ! Access::can_see_list( $settings ) ) {
			return is_user_logged_in() || empty( $settings['enabled'] ) ? '' : '<p class="estg-login">' . esc_html__( 'Please log in to see your entries.', 'entrystage' ) . '</p>';
		}
		Addon::get_instance()->enqueue_front_assets();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view.
		$view_id = isset( $_GET['estg_view'], $_GET['estg_f'] ) && absint( $_GET['estg_f'] ) === $form_id ? absint( $_GET['estg_view'] ) : 0;
		if ( $view_id ) {
			$entry = \GFAPI::get_entry( $view_id );
			if ( ! is_wp_error( $entry ) && (int) $entry['form_id'] === $form_id && Access::can( 'view', $entry, $settings ) ) {
				return self::entry_view( $form, $entry, $settings );
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view.
		if ( ! empty( $settings['print'] ) && isset( $_GET['estg_print'] ) && absint( $_GET['estg_print'] ) === $form_id ) {
			return self::print_html( $form, $settings, $atts );
		}
		return self::list_html( $form, $settings, $atts );
	}

	/**
	 * Personal space: several forms, one tab per form with its number of entries; the tab shows the
	 * list of that form as set in its settings. [entrystage forms="12,15" user="current"]
	 *
	 * @param array $raw Shortcode attributes.
	 * @return string
	 */
	private static function space( array $raw ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $raw['forms'] ) ) ) ) );
		unset( $raw['forms'], $raw['form'], $raw['id'], $raw['page'] );
		$fixed = Query::shortcode_filters( $raw );
		$tabs  = array();
		foreach ( array_slice( $ids, 0, 20 ) as $id ) {
			$form = \GFAPI::get_form( $id );
			if ( ! $form || empty( $form['is_active'] ) || ! empty( $form['is_trash'] ) ) {
				continue;
			}
			$settings = Settings::get( $form );
			if ( empty( $settings['enabled'] ) || ! Access::can_see_list( $settings ) ) {
				continue;
			}
			// number of entries as the list opens (without the search of the visitor).
			$state = Query::state(
				$form,
				$settings,
				array(),
				array(
					'defaults'    => true,
					'user_filter' => $fixed['user'],
					'fixed'       => $fixed['fields'],
				)
			);
			$count       = Query::run( $form, $settings, array_merge( $state, array( 'page' => 1, 'per_page' => 1 ) ) );
			$tabs[ $id ] = array(
				'title' => (string) $form['title'],
				'count' => (int) $count['total'],
			);
		}
		if ( ! $tabs ) {
			return is_user_logged_in() ? '' : '<p class="estg-login">' . esc_html__( 'Please log in to see your entries.', 'entrystage' ) . '</p>';
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view.
		$active = isset( $_GET['estg_tab'] ) ? absint( $_GET['estg_tab'] ) : 0;
		if ( ! isset( $tabs[ $active ] ) && isset( $_GET['estg_f'] ) ) {
			$active = absint( $_GET['estg_f'] );   // back from an action or a page of a list.
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = (int) array_key_first( $tabs );
		}
		/**
		 * Filters the tabs of a personal space (form id => title and count).
		 *
		 * @param array $tabs   Tabs.
		 * @param int   $active Form id of the tab shown.
		 */
		$tabs = (array) apply_filters( 'estg_space_tabs', $tabs, $active );

		$h = '<div class="estg-space">';
		if ( count( $tabs ) > 1 ) {
			$h .= '<nav class="estg-tabs estg-space-tabs" aria-label="' . esc_attr__( 'Forms', 'entrystage' ) . '">';
			foreach ( $tabs as $id => $tab ) {
				$url = add_query_arg( array( 'estg_tab' => (int) $id ), Urls::current_page() );
				$cur = (int) $id === $active;
				$h  .= '<a class="estg-tab' . ( $cur ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $cur ? ' aria-current="page"' : '' ) . '>' . esc_html( $tab['title'] ) . ' <span class="estg-count">' . esc_html( number_format_i18n( $tab['count'] ) ) . '</span></a>';
			}
			$h .= '</nav>';
		}
		$h .= self::shortcode( array_merge( $raw, array( 'form' => $active ) ) );
		return $h . '</div>';
	}

	/** Most entries on one printed list. */
	const PRINT_LIMIT = 500;

	/**
	 * Printable list: the entries matching the filters, every page, without buttons. front.js opens the
	 * print dialog once the page is loaded.
	 *
	 * @param array $form     Form object.
	 * @param array $settings Form settings.
	 * @param array $atts     Shortcode attributes.
	 * @return string
	 */
	private static function print_html( $form, array $settings, array $atts ) {
		$form_id = (int) $form['id'];
		$layout  = Settings::layout( $form, $settings );
		$state   = Query::state( $form, $settings, array(), $atts );
		$back    = Urls::list_url( $form_id, $state );
		$state   = array_merge(
			$state,
			array(
				'page'     => 1,
				'per_page' => self::PRINT_LIMIT,
			)
		);
		$result  = Query::run( $form, $settings, $state );
		$title   = get_the_title();
		$title   = '' !== trim( wp_strip_all_tags( (string) $title ) ) ? $title : $form['title'];

		$h  = '<div class="estg estg-print" id="estg-' . esc_attr( $form_id ) . '" data-form="' . esc_attr( $form_id ) . '" data-estg-autoprint>';
		$h .= '<div class="estg-print-bar"><button type="button" class="estg-btn" data-estg-print>' . self::icon( 'print' ) . '<span>' . esc_html__( 'Print', 'entrystage' ) . '</span></button>';
		$h .= ' <a class="estg-link" href="' . esc_url( $back ) . '">&larr; ' . esc_html__( 'Back to the list', 'entrystage' ) . '</a></div>';
		$h .= '<h2 class="estg-print-title">' . esc_html( wp_strip_all_tags( (string) $title ) ) . '</h2>';
		$info = array(
			/* translators: %s: date and time */
			sprintf( __( 'Printed on %s', 'entrystage' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
			/* translators: %s: number of entries */
			sprintf( _n( '%s entry', '%s entries', $result['total'], 'entrystage' ), number_format_i18n( $result['total'] ) ),
		);
		if ( '' !== (string) $state['search'] ) {
			/* translators: %s: text searched */
			$info[] = sprintf( __( 'Search: %s', 'entrystage' ), $state['search'] );
		}
		$h .= '<p class="estg-print-info">' . esc_html( implode( ' · ', $info ) ) . '</p>';
		if ( $result['total'] > self::PRINT_LIMIT ) {
			/* translators: %d: most entries printed */
			$h .= '<p class="estg-notice is-error">' . esc_html( sprintf( __( 'Only the first %d entries are printed. Narrow the list with the filters to print the others.', 'entrystage' ), self::PRINT_LIMIT ) ) . '</p>';
		}
		if ( empty( $result['entries'] ) ) {
			return $h . '<p class="estg-empty">' . esc_html( $settings['empty_text'] ) . '</p></div>';
		}
		$h .= '<table class="estg-table estg-print-table"><thead><tr>';
		foreach ( $layout as $item ) {
			$h .= '<th class="estg-col-' . esc_attr( sanitize_html_class( $item['key'] ) ) . '">' . esc_html( $item['label'] ) . '</th>';
		}
		$h .= '</tr></thead><tbody>';
		foreach ( $result['entries'] as $entry ) {
			$h .= '<tr class="estg-row">';
			foreach ( $layout as $item ) {
				$class = 'estg-col-' . sanitize_html_class( $item['key'] );
				if ( 'field' === $item['kind'] ) {
					$h .= '<td class="' . esc_attr( $class ) . '">' . Fields::render( $form, $entry, $item['column']['field'], $item['mode'], $settings ) . '</td>';
				} else {
					$h .= '<td class="' . esc_attr( $class . ' estg-nowrap' ) . '">' . esc_html( \GFCommon::format_date( $entry['date_created'], false, '', false ) ) . '</td>';
				}
			}
			$h .= '</tr>';
		}
		return $h . '</tbody></table></div>';
	}

	/**
	 * The list.
	 *
	 * @param array $form     Form object.
	 * @param array $settings Form settings.
	 * @param array $atts     Shortcode attributes.
	 * @return string
	 */
	private static function list_html( $form, array $settings, array $atts ) {
		$form_id = (int) $form['id'];
		$layout  = Settings::layout( $form, $settings );
		$state  = Query::state( $form, $settings, array(), $atts );
		$result = Query::run( $form, $settings, $state );
		$here   = Urls::list_url( $form_id, $state );
		$with_bulk = false;
		$more = false;

		ob_start();
		echo '<div class="estg' . ( 96 === (int) $settings['thumb_size'] ? ' estg-thumbs-96' : '' ) . '" id="estg-' . esc_attr( $form_id ) . '" data-form="' . esc_attr( $form_id ) . '"';
		echo '>';
		do_action( 'estg_before_list', $form, $settings, $state );
		if ( ( ! empty( $settings['edit'] ) || ! empty( $settings['duplicate'] ) ) && empty( $settings['page_id'] ) && empty( $settings['page_url'] ) && Access::is_manager( $settings ) ) {
			// Only managers see this hint.
			echo '<div class="estg-notice is-error" role="status">' . esc_html__( 'Edit and Duplicate are hidden: no page displaying this form was found. Choose it in the form settings, EntryStage tab, "Page containing the form".', 'entrystage' ) . '</div>';
		}
		echo self::notice( $form_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in notice().

		echo self::toolbar( $form, $settings, $state, $layout ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in toolbar().

		if ( empty( $result['entries'] ) ) {
			echo '<p class="estg-empty">' . esc_html( $settings['empty_text'] ) . '</p>';
		} else {
			echo '<form method="post" class="estg-rows" action="' . esc_url( $here ) . '">';
			wp_nonce_field( Urls::nonce_action( 'list', $form_id ), 'estg_list_nonce' );
			echo '<input type="hidden" name="estg_form" value="' . esc_attr( $form_id ) . '">';
			echo '<input type="hidden" name="estg_return" value="' . esc_url( $here ) . '">';


			$fixed = false;
			foreach ( $layout as $item ) {
				$fixed = $fixed || '' !== $item['width'];
			}
			// a width in % is a share of the space left by the actions, the chevron and the boxes
			// (applied by front.js, data-estg-share), so that columns adding up to 100 % (or less) never push
			// the table out of its container. Without JavaScript the % applies to the whole table.
			echo '<div class="estg-scroll"><table class="estg-table' . ( $fixed ? ' estg-fixed' : '' ) . '">';
			if ( $fixed ) {
				// Chosen widths are applied with a fixed table layout so that themes cannot override them;
				// columns without a width share the remaining space.
				echo '<colgroup>';
				foreach ( $layout as $item ) {
					$share = self::share( $item['width'] );
					echo '' !== $item['width'] ? '<col style="width:' . esc_attr( $item['width'] ) . '"' . ( '' !== $share ? ' data-estg-share="' . esc_attr( $share ) . '"' : '' ) . '>' : '<col>';
				}
				echo '<col style="width:' . esc_attr( self::actions_width( $settings ) ) . '" data-estg-actions>';
				echo '</colgroup>';
			}
			echo '<thead><tr>';
			foreach ( $layout as $item ) {
				$style = '' !== $item['width'] ? ' style="width:' . esc_attr( $item['width'] ) . '"' : '';
				$head  = '' !== $item['sort_key'] ? self::sort_link( $form_id, $state, $item['sort_key'], $item['label'] ) : '<span>' . esc_html( $item['label'] ) . '</span>';
				echo '<th class="estg-col-' . esc_attr( sanitize_html_class( $item['key'] ) ) . '"' . $style . '>' . $head . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above and in sort_link().
			}
			// at most 25 % of the table (front.js, data-estg-em); without JavaScript, the CSS max-width.
			echo '<th class="estg-actions-col" style="width:' . esc_attr( self::actions_width( $settings ) ) . '" data-estg-em="' . esc_attr( (float) self::actions_width( $settings ) ) . '">' . esc_html( $settings['actions_header'] ) . '</th>';
			echo '</tr></thead><tbody>';

			$span = count( $layout ) + 1 + ( $with_bulk ? 1 : 0 ) + ( $more ? 1 : 0 );
			foreach ( $result['entries'] as $entry ) {
				echo self::row( $form, $entry, $settings, $layout, $with_bulk, $here, $more ? $span : 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in row().
			}
			echo '</tbody></table></div></form>';
			echo self::pagination( $form_id, $state, $result['total'], $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pagination().
		}
		do_action( 'estg_after_list', $form, $settings, $state );
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * One row.
	 *
	 * @param array  $form      Form object.
	 * @param array  $entry     Entry.
	 * @param array  $settings  Form settings.
	 * @param array  $layout    Columns in display order.
	 * @param bool   $with_bulk Show the selection box.
	 * @param string $here      Current list address.
	 * @param int    $span      Number of cells of a row when "More details" is used, 0 otherwise.
	 * @return string
	 */
	private static function row( $form, $entry, array $settings, array $layout, $with_bulk, $here, $span = 0 ) {
		$id      = (int) $entry['id'];
		$classes = (array) apply_filters( 'estg_row_classes', array( 'estg-row' ), $entry, $settings );
		$h       = '<tr class="' . esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ) . '" data-entry="' . esc_attr( $id ) . '">';
		foreach ( $layout as $item ) {
			$class = 'estg-col-' . sanitize_html_class( $item['key'] );
			if ( 'field' === $item['kind'] ) {
				$h .= '<td data-label="' . esc_attr( $item['label'] ) . '" class="' . esc_attr( $class ) . '">' . Fields::render( $form, $entry, $item['column']['field'], $item['mode'], $settings ) . '</td>';
			} else {
				$h .= '<td data-label="' . esc_attr( $item['label'] ) . '" class="' . esc_attr( $class . ' estg-nowrap' ) . '">' . esc_html( \GFCommon::format_date( $entry['date_created'], false, '', false ) ) . '</td>';
			}
		}
		$acts = self::row_actions( $entry, $settings, $here );
		$top  = '';
		$h .= '<td class="estg-actions" data-label="' . esc_attr( $settings['actions_header'] ) . '">' . $top . '<div class="estg-act-row">' . implode( ' ', $acts ) . '</div></td>';
		return $h . '</tr>';
	}

	/**
	 * Action links of a row.
	 *
	 * @param array  $entry    Entry.
	 * @param array  $settings Form settings.
	 * @param string $here     Current list address.
	 * @return string[] Safe HTML.
	 */
	private static function row_actions( $entry, array $settings, $here ) {
		$id      = (int) $entry['id'];
		$form_id = (int) $entry['form_id'];
		$out     = array();
		if ( Access::can( 'view', $entry, $settings ) ) {
			$url   = add_query_arg(
				array(
					'estg_f'    => $form_id,
					'estg_view' => $id,
				),
				$here
			);
			$out[] = '<a class="estg-act estg-act-view" href="' . esc_url( $url ) . '"' . self::act_attrs( $settings, 'view' ) . '>' . self::act_inner( $settings, 'view' ) . '</a>';
		}
		foreach ( array( 'edit', 'duplicate' ) as $action ) {
			if ( Access::can( $action, $entry, $settings ) ) {
				$url = Urls::form_url( $settings, $action, $id, $here );
				if ( '' !== $url ) {
					$out[] = '<a class="estg-act estg-act-' . esc_attr( $action ) . '" href="' . esc_url( $url ) . '"' . self::act_attrs( $settings, $action ) . '>' . self::act_inner( $settings, $action ) . '</a>';
				}
			}
		}
		if ( Access::can( 'archive', $entry, $settings ) ) {
			$out[] = '<button type="submit" class="estg-act estg-act-archive" name="estg_do" value="' . esc_attr( 'archive|' . $id ) . '"' . ( '' !== $settings['archive_confirm'] ? ' data-estg-confirm="' . esc_attr( $settings['archive_confirm'] ) . '"' : '' ) . self::act_attrs( $settings, 'archive' ) . '>' . self::act_inner( $settings, 'archive' ) . '</button>';
		}
		/**
		 * Filters the action links of a row (each item is safe HTML).
		 *
		 * @param string[] $out      Links.
		 * @param array    $entry    Entry.
		 * @param array    $settings Form settings.
		 */
		return (array) apply_filters( 'estg_row_actions', $out, $entry, $settings );
	}

	/**
	 * Share of a column whose width is a percentage, for its data-estg-share attribute (read by front.js).
	 *
	 * @param string $width Clean width (Settings::clean_width()).
	 * @return string The share (for example "0.15"), or '' when the width is not a percentage.
	 */
	private static function share( $width ) {
		return '%' === substr( $width, -1 ) ? (string) round( (float) $width / 100, 4 ) : '';
	}

	/**
	 * Width kept for the actions column when the table layout is fixed.
	 *
	 * @param array $settings Form settings.
	 * @return string CSS width.
	 */
	private static function actions_width( array $settings ) {
		$count = 0;
		$actions = array( 'view', 'edit', 'duplicate', 'archive' );
		foreach ( $actions as $action ) {
			$count += ! empty( $settings[ $action ] ) ? 1 : 0;
		}
		$count = max( 1, $count );
		$per   = array(
			'icons' => 2.6,
			'both'  => 7.5,
			'text'  => 6,
		);
		$em    = $count * ( isset( $per[ $settings['action_display'] ] ) ? $per[ $settings['action_display'] ] : 2.6 ) + 2; // + cell padding (1.4em).
		return max( 6, $em ) . 'em';
	}

	/**
	 * Tooltip and accessible name of an action button (icons only).
	 *
	 * @param array  $settings Form settings.
	 * @param string $action   Action.
	 * @return string
	 */
	private static function act_attrs( array $settings, $action ) {
		return 'icons' === $settings['action_display'] ? ' title="' . esc_attr( $settings[ $action . '_label' ] ) . '"' : '';
	}

	/**
	 * Content of an action button: icon, text or both, as set for the form.
	 *
	 * @param array  $settings Form settings.
	 * @param string $action   Action.
	 * @return string
	 */
	private static function act_inner( array $settings, $action ) {
		$display = $settings['action_display'];
		$icon    = 'text' !== $display ? self::action_icon( $settings, $action ) : '';
		$class   = 'icons' === $display ? 'screen-reader-text' : 'estg-act-text';
		return $icon . '<span class="' . $class . '">' . esc_html( $settings[ $action . '_label' ] ) . '</span>';
	}

	/**
	 * Icon of an action button: the picture chosen in the settings, or the built-in icon.
	 *
	 * @param array  $settings Form settings.
	 * @param string $action   view | edit | duplicate | archive | pdf | next.
	 * @return string
	 */
	private static function action_icon( array $settings, $action ) {
		return self::icon( $action );
	}

	/**
	 * Inline SVG icon (drawn with the text colour).
	 *
	 * @param string $name view | edit | duplicate | archive | pdf | next | export | download | file | chevron.
	 * @return string
	 */
	public static function icon( $name ) {
		$paths = array(
			'view'      => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
			'edit'      => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
			'duplicate' => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
			'archive'   => '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/><path d="M10 12h4"/>',
			'pdf'       => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/>',
			'next'      => '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>',
			'export'    => '<path d="M12 4v11"/><path d="M7 10l5 5 5-5"/><path d="M5 20h14"/>',
			'download'  => '<path d="M12 4v11"/><path d="M7 10l5 5 5-5"/><path d="M5 20h14"/>',
			'file'      => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
			'chevron'   => '<path d="M6 9l6 6 6-6"/>',
			'search'    => '<circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/>',
			'print'     => '<path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/>',
		);
		/**
		 * Filters the SVG content of an icon (the inner elements of a 24x24 <svg>).
		 *
		 * @param string $svg  SVG elements.
		 * @param string $name Icon name.
		 */
		$inner = (string) apply_filters( 'estg_icon', isset( $paths[ $name ] ) ? $paths[ $name ] : '', $name );
		if ( '' === $inner ) {
			return '';
		}
		return '<svg class="estg-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $inner . '</svg>';
	}

	/**
	 * Column header with sort link.
	 *
	 * @param int    $form_id Form id.
	 * @param array  $state   List state.
	 * @param string $key     Sort key.
	 * @param string $label   Label.
	 * @return string
	 */
	private static function sort_link( $form_id, array $state, $key, $label ) {
		$active = (string) $state['orderby'] === (string) $key;
		$dir    = $active && 'ASC' === $state['dir'] ? 'DESC' : 'ASC';
		$url    = Urls::list_url(
			$form_id,
			$state,
			array(
				'estg_o' => $key,
				'estg_d' => $dir,
				'estg_p' => null,
			)
		);
		$arrow  = $active ? ( 'ASC' === $state['dir'] ? ' &uarr;' : ' &darr;' ) : '';
		return '<a class="estg-sort' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $active ? ' aria-sort="' . ( 'ASC' === $state['dir'] ? 'ascending' : 'descending' ) . '"' : '' ) . '>' . esc_html( $label ) . $arrow . '</a>';
	}

	/**
	 * Search, filters, page size and export.
	 *
	 * @param array $form     Form object.
	 * @param array $settings Form settings.
	 * @param array $state    List state.
	 * @param array $layout   Columns (Settings::layout()).
	 * @return string
	 */
	private static function toolbar( $form, array $settings, array $state, array $layout ) {
		$form_id    = (int) $form['id'];
		$has_tools = ! empty( $settings['search'] );
		$export = false;
		$print = ! empty( $settings['print'] );
		if ( ! $has_tools && ! $export && ! $print ) {
			return '';
		}
		$uid    = 'estg-' . $form_id;
		$active = '' !== (string) $state['search'];
		$h      = '<form method="get" class="estg-toolbar" action="' . esc_url( Urls::current_page() ) . '" role="search">';
		$h     .= '<input type="hidden" name="estg_f" value="' . esc_attr( $form_id ) . '">';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view.
		if ( isset( $_GET['estg_tab'] ) && absint( $_GET['estg_tab'] ) ) {
			$h .= '<input type="hidden" name="estg_tab" value="' . esc_attr( absint( $_GET['estg_tab'] ) ) . '">'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		foreach ( array( 'estg_st' => '' !== (string) $state['status'] ? $state['status'] : ( ! empty( $state['status_all'] ) ? '_all' : '' ), 'estg_o' => $state['orderby'], 'estg_d' => $state['dir'], 'estg_pp' => $state['per_page'] ) as $name => $value ) {
			if ( '' !== (string) $value ) {
				$h .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
			}
		}
		// the fields and their "Filter" button sit together in one box; "Clear the filters" only shows when a filter is on.
		if ( $has_tools ) {
			$h .= '<div class="estg-filters' . ( $active ? ' is-active' : '' ) . '">';
			if ( ! empty( $settings['search'] ) ) {
				$h .= '<label class="estg-field estg-field-search"><span>' . esc_html__( 'Search', 'entrystage' ) . '</span><input type="search" name="estg_s" value="' . esc_attr( $state['search'] ) . '" id="' . esc_attr( $uid ) . '-s" placeholder="' . esc_attr__( 'A word, a name…', 'entrystage' ) . '"></label>';
			}
			$h .= '<div class="estg-filter-go"><button type="submit" class="estg-btn">' . self::icon( 'search' ) . '<span>' . esc_html__( 'Filter', 'entrystage' ) . '</span></button>';
			if ( $active ) {
				$clear = array_merge(
					$state,
					array(
						'search'  => '',
						'from'    => '',
						'to'      => '',
						'filters' => array(),
						'page'    => 1,
					)
				);
				$h    .= '<a class="estg-link" href="' . esc_url( Urls::list_url( $form_id, $clear ) ) . '">' . esc_html__( 'Clear the filters', 'entrystage' ) . '</a>';
			}
			$h .= '</div></div>';
		}
		if ( $export || $print ) {
			$h .= '<div class="estg-tools">';
		}
		if ( $print ) {
			$h .= '<a class="estg-btn estg-btn-ghost" href="' . esc_url( Urls::list_url( $form_id, $state, array( 'estg_p' => null, 'estg_print' => $form_id ) ) ) . '" title="' . esc_attr__( 'Entries matching the filters, every page', 'entrystage' ) . '">' . self::icon( 'print' ) . '<span>' . esc_html__( 'Print', 'entrystage' ) . '</span></a>';
		}
		if ( $export || $print ) {
			$h .= '</div>';
		}
		return $h . '</form>';
	}

	/**
	 * Rows per page, next to the pagination. Changing it reloads the list (front.js); the
	 * button is there for browsers without JavaScript.
	 *
	 * @param int   $form_id  Form id.
	 * @param array $state    List state.
	 * @param array $settings Form settings.
	 * @return string
	 */
	private static function per_page( $form_id, array $state, array $settings ) {
		if ( count( $settings['per_page_choices'] ) < 2 ) {
			return '';
		}
		$args = array();
		wp_parse_str( (string) wp_parse_url( Urls::list_url( $form_id, array_merge( $state, array( 'page' => 1 ) ), array( 'estg_pp' => null ) ), PHP_URL_QUERY ), $args );
		$h = '<form method="get" class="estg-perpage" action="' . esc_url( Urls::current_page() ) . '">';
		foreach ( $args as $name => $value ) {
			foreach ( is_array( $value ) ? $value : array( '' => $value ) as $sub => $v ) {
				if ( is_scalar( $v ) ) {
					$h .= '<input type="hidden" name="' . esc_attr( '' === $sub ? $name : $name . '[' . $sub . ']' ) . '" value="' . esc_attr( $v ) . '">';
				}
			}
		}
		$h .= '<label><span>' . esc_html__( 'Rows per page', 'entrystage' ) . '</span> <select name="estg_pp" data-estg-autosubmit>';
		foreach ( array_unique( array_merge( (array) $settings['per_page_choices'], array( (int) $state['per_page'] ) ) ) as $n ) {
			$h .= '<option value="' . esc_attr( $n ) . '"' . selected( (int) $state['per_page'], (int) $n, false ) . '>' . esc_html( $n ) . '</option>';
		}
		$h .= '</select></label> <button type="submit" class="estg-btn estg-btn-ghost estg-btn-small estg-nojs">' . esc_html__( 'OK', 'entrystage' ) . '</button></form>';
		return $h;
	}

	/**
	 * Pagination.
	 *
	 * @param int   $form_id  Form id.
	 * @param array $state    List state.
	 * @param int   $total    Total entries.
	 * @param array $settings Form settings.
	 * @return string
	 */
	private static function pagination( $form_id, array $state, $total, array $settings ) {
		$pages = (int) ceil( $total / max( 1, $state['per_page'] ) );
		$h     = '<div class="estg-footer"><span class="estg-total">' . esc_html( sprintf( /* translators: %s: number of entries */ _n( '%s entry', '%s entries', $total, 'entrystage' ), number_format_i18n( $total ) ) ) . '</span>';
		if ( $pages > 1 ) {
			$h    .= '<nav class="estg-pages" aria-label="' . esc_attr__( 'Pages', 'entrystage' ) . '">';
			$links = array_unique( array_filter( array( 1, $state['page'] - 2, $state['page'] - 1, $state['page'], $state['page'] + 1, $state['page'] + 2, $pages ), function ( $p ) use ( $pages ) {
				return $p >= 1 && $p <= $pages;
			} ) );
			sort( $links );
			$prev = 0;
			foreach ( $links as $p ) {
				if ( $prev && $p > $prev + 1 ) {
					$h .= '<span class="estg-gap">&hellip;</span>';
				}
				if ( $p === $state['page'] ) {
					$h .= '<span class="estg-page is-active" aria-current="page">' . esc_html( $p ) . '</span>';
				} else {
					$h .= '<a class="estg-page" href="' . esc_url( Urls::list_url( $form_id, $state, array( 'estg_p' => $p > 1 ? $p : null ) ) ) . '">' . esc_html( $p ) . '</a>';
				}
				$prev = $p;
			}
			$h .= '</nav>';
		}
		return $h . self::per_page( $form_id, $state, $settings ) . '</div>';
	}

	/**
	 * Message after an action (from the redirect).
	 *
	 * @param int $form_id Form id.
	 * @return string
	 */
	private static function notice( $form_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( ! isset( $_GET['estg_msg'], $_GET['estg_f'] ) || absint( $_GET['estg_f'] ) !== (int) $form_id ) {
			return '';
		}
		$code = sanitize_key( wp_unslash( $_GET['estg_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$n    = isset( $_GET['estg_n'] ) ? absint( $_GET['estg_n'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages = array(
			/* translators: %d: number of entries */
			'archived'   => _n( '%d entry archived.', '%d entries archived.', $n, 'entrystage' ),
			/* translators: %d: number of entries */
			'duplicated' => _n( '%d entry duplicated.', '%d entries duplicated.', $n, 'entrystage' ),
			/* translators: %d: number of entries */
			'updated'    => __( 'Entry updated.', 'entrystage' ),
			'denied'     => __( 'This action is not allowed.', 'entrystage' ),
			'none'       => __( 'No entry selected.', 'entrystage' ),
		);
		if ( ! isset( $messages[ $code ] ) ) {
			return '';
		}
		$error = in_array( $code, array( 'denied', 'none' ), true );
		return '<div class="estg-notice' . ( $error ? ' is-error' : '' ) . '" role="status">' . esc_html( sprintf( $messages[ $code ], $n ) ) . '</div>';
	}

	/**
	 * Read-only view of an entry.
	 *
	 * @param array $form     Form object.
	 * @param array $entry    Entry.
	 * @param array $settings Form settings.
	 * @return string
	 */
	private static function entry_view( $form, $entry, array $settings ) {
		$back = remove_query_arg( array( 'estg_view' ), Urls::list_url( (int) $form['id'], Query::state( $form, $settings ) ) );
		$skip = array( 'html', 'section', 'page', 'captcha', 'password' );
		$h    = '<div class="estg estg-view"><p><a class="estg-link" href="' . esc_url( $back ) . '">&larr; ' . esc_html__( 'Back to the list', 'entrystage' ) . '</a></p>';
		$h   .= '<h2 class="estg-view-title">' . esc_html( sprintf( /* translators: %d: entry id */ __( 'Entry #%d', 'entrystage' ), (int) $entry['id'] ) ) . '</h2>';
		$h .= '<dl class="estg-detail">';
		foreach ( (array) $form['fields'] as $field ) {
			if ( in_array( $field->type, $skip, true ) || ! empty( rgobj( $field, 'adminOnly' ) ) || in_array( rgobj( $field, 'visibility' ), array( 'hidden', 'administrative' ), true ) ) {
				continue;
			}
			$value = \GFFormsModel::get_lead_field_value( $entry, $field );
			$html  = Fields::display( $field, $value, $entry, 'html' );
			if ( '' === trim( wp_strip_all_tags( (string) $html ) ) ) {
				continue;
			}
			$h .= '<dt>' . esc_html( wp_strip_all_tags( \GFCommon::get_label( $field ) ) ) . '</dt><dd>' . wp_kses_post( $html ) . '</dd>';
		}
		$h .= '</dl></div>';
		return (string) apply_filters( 'estg_entry_view_html', $h, $entry, $form, $settings );
	}
}
