<?php
/**
 * The settings page of a form (Forms > form > Settings > EntryStage), drawn as a workshop in five
 * steps: Access, Columns (and details in EntryStage Pro), Actions, Statuses (EntryStage Pro), Display, Shortcode.
 *
 * The page is a custom Gravity Forms form settings page (GFAddOn::form_settings()). It saves with
 * GFAddOn::save_form_settings(), so the settings live in the form, as with every add-on.
 *
 * @package EntryStage
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelier {

	/**
	 * Steps of the page.
	 *
	 * @return array slug => label.
	 */
	public static function steps() {
		return array(
			'access'   => __( 'Access', 'entrystage' ),
			'columns'  => __( 'Columns', 'entrystage' ),
			'actions'  => __( 'Actions', 'entrystage' ),
			'display'  => __( 'Display', 'entrystage' ),
			'shortcode' => __( 'Shortcode', 'entrystage' ),
		);
	}

	/**
	 * Draws the page (and saves it first when it was posted).
	 *
	 * @param Addon $addon Add-on.
	 * @param array $form  Form object.
	 */
	public static function render( Addon $addon, $form ) {
		$message = '';
		$error   = false;
		$step    = 'access';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in save().
		if ( isset( $_POST['estg_atelier_nonce'] ) ) {
			list( $message, $error, $step ) = self::save( $addon, $form );
			$form                           = \GFAPI::get_form( (int) $form['id'] );
		}
		Settings::flush();
		$settings = Settings::get( $form );
		$raw      = $addon->get_form_settings( $form );
		$raw      = is_array( $raw ) ? $raw : array();
		$form_id  = (int) $form['id'];
		$d        = Settings::defaults();
		$list_url = self::list_page_url( $form_id );
		$roles    = self::roles();

		echo '<div class="estga" data-step="' . esc_attr( $step ) . '">';
		echo '<form method="post" class="estga-form" novalidate>';
		wp_nonce_field( 'estg_atelier_' . $form_id, 'estg_atelier_nonce' );
		echo '<input type="hidden" name="estg[step]" value="' . esc_attr( $step ) . '" data-estga-step-input>';

		// Header.
		echo '<section class="estga-hero">';
		echo '<div class="estga-hero-title"><span class="estga-logo" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M4 12h11"/><path d="M4 17h7"/><path d="M17 15l3 3-3 3"/></svg></span>';
		echo '<div><p class="estga-kicker">' . esc_html__( 'EntryStage · list workshop', 'entrystage' ) . '</p><h2 class="estga-h1" title="' . esc_attr( sprintf( /* translators: %s: form title */ __( 'List of "%s"', 'entrystage' ), $form['title'] ) ) . '">' . esc_html( sprintf( /* translators: %s: form title */ __( 'List of "%s"', 'entrystage' ), $form['title'] ) ) . '</h2></div></div>';
		echo '<label class="estga-switch estga-switch-hero">';
		self::checkbox( 'enabled', ! empty( $settings['enabled'] ) );
		echo '<span class="estga-track" aria-hidden="true"></span><span>' . esc_html__( 'List enabled', 'entrystage' ) . '</span></label>';
		$code = '[entrystage form="' . $form_id . '"]';
		echo '<div class="estga-code"><code>' . esc_html( $code ) . '</code><button type="button" class="estga-copy" data-estga-copy="' . esc_attr( $code ) . '">' . esc_html__( 'Copy', 'entrystage' ) . '</button></div>';
		if ( '' !== $list_url ) {
			echo '<a class="estga-hero-link" href="' . esc_url( $list_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View the page', 'entrystage' ) . ' <span aria-hidden="true">↗</span></a>';
		}
		echo '<button type="submit" class="estga-save">' . esc_html__( 'Save', 'entrystage' ) . '</button>';
		echo '</section>';
		// Short banner about EntryStage Pro, on this settings page only. The chevron opens the details;
		// the cross hides it (remembered by the browser).
		echo '<aside class="estga-pro" data-estga-pro hidden aria-label="' . esc_attr__( 'EntryStage Pro', 'entrystage' ) . '"><div class="estga-pro-bar">';
		echo '<span class="estga-pro-badge">' . esc_html__( 'EntryStage Pro', 'entrystage' ) . '</span>';
		echo '<span class="estga-pro-text">' . esc_html__( 'Follow every entry from start to finish.', 'entrystage' ) . '</span>';
		if ( '' === ENTRYSTAGE_PRO_URL ) {
			echo '<span class="estga-pro-soon">' . esc_html__( 'Available soon', 'entrystage' ) . '</span>';
		}
		echo '<button type="button" class="estga-pro-toggle" data-estga-pro-toggle aria-expanded="false" aria-controls="estga-pro-more"><span class="screen-reader-text">' . esc_html__( 'More details', 'entrystage' ) . '</span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button>';
		echo '<button type="button" class="estga-pro-hide" data-estga-pro-hide aria-label="' . esc_attr__( 'Hide this banner', 'entrystage' ) . '"><span aria-hidden="true">×</span></button></div>';
		echo '<div class="estga-pro-more" id="estga-pro-more" hidden>';
		echo '<p>' . esc_html__( 'Requests, registrations, applications, orders: give each entry a status of your own (for example Received, In progress, Done), shown as a coloured label. One click moves it to the next step, one tab per status counts what is waiting at each step, and every change is written in the notes of the entry. Your team sees at a glance what is left to do.', 'entrystage' ) . '</p>';
		echo '<p>' . esc_html__( 'Also included: More details under each row, bulk actions, CSV export, drop-down and date filters, PDF links (Gravity PDF), custom icons, shortcode conditions and priority support.', 'entrystage' ) . '</p>';
		if ( '' !== ENTRYSTAGE_PRO_URL ) {
			echo '<p><a class="estga-pro-link" href="' . esc_url( ENTRYSTAGE_PRO_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Discover EntryStage Pro', 'entrystage' ) . ' <span aria-hidden="true">↗</span></a></p>';
		} else {
			echo '<p class="estga-pro-soon">' . esc_html__( 'EntryStage Pro will be available soon.', 'entrystage' ) . '</p>';
		}
		echo '</div></aside>';

		if ( '' !== $message ) {
			echo '<div class="estga-msg' . ( $error ? ' is-error' : '' ) . '" role="status">' . esc_html( $message ) . '</div>';
		}
		if ( ! empty( $settings['columns_migrated'] ) ) {
			echo '<div class="estga-msg is-info" role="status">' . esc_html__( 'The columns come from the field options of an earlier build (form editor). They are already in use; save this page to keep them here. The option has left the form editor.', 'entrystage' ) . '</div>';
		}

		// Steps.
		echo '<nav class="estga-steps" aria-label="' . esc_attr__( 'Steps', 'entrystage' ) . '">';
		$n = 0;
		foreach ( self::steps() as $slug => $label ) {
			++$n;
			echo '<button type="button" class="estga-step" data-estga-go="' . esc_attr( $slug ) . '" aria-controls="estga-panel-' . esc_attr( $slug ) . '"><span class="estga-num" aria-hidden="true">' . (int) $n . '</span><span class="estga-step-text"><span class="estga-step-label">' . esc_html( $label ) . '</span><span class="estga-state" data-estga-state="' . esc_attr( $slug ) . '"></span></span></button>';
		}
		echo '</nav>';

		// 1. Access.
		self::panel_open( 'access' );
		echo '<div class="estga-grid">';
		self::card_open( __( 'Who sees the list', 'entrystage' ) );
		self::segmented(
			'access',
			$settings['access'],
			array(
				'everyone' => __( 'Everyone', 'entrystage' ),
				'loggedin' => __( 'Logged-in users', 'entrystage' ),
				'roles'    => __( 'Some roles', 'entrystage' ),
			),
			__( 'Who sees the list', 'entrystage' )
		);
		echo '<div class="estga-sub" data-estga-show="access=roles"><p class="estga-label">' . esc_html__( 'Roles that see the list', 'entrystage' ) . '</p><div class="estga-chips">';
		foreach ( $roles as $role => $name ) {
			echo '<label class="estga-chip"><input type="checkbox" name="estg[access_roles][]" value="' . esc_attr( $role ) . '"' . checked( in_array( $role, $settings['access_roles'], true ), true, false ) . '><span>' . esc_html( $name ) . '</span></label>';
		}
		echo '</div></div>';
		self::card_close();

		self::card_open( __( 'Entries shown', 'entrystage' ) );
		self::segmented(
			'scope',
			$settings['scope'],
			array(
				'own' => __( 'Their own entries', 'entrystage' ),
				'all' => __( 'All entries', 'entrystage' ),
			),
			__( 'Entries shown', 'entrystage' )
		);
		echo '<p class="estga-help" data-estga-show="scope=own">' . esc_html__( 'Each person sees only their own entries, and edits, duplicates and archives them. List managers see and handle every entry.', 'entrystage' ) . '</p>';
		echo '<p class="estga-help" data-estga-show="scope=all">' . esc_html__( 'Each person sees every entry, but edits, duplicates and archives only their own. List managers handle every entry.', 'entrystage' ) . '</p>';
		self::card_close();

		self::card_open( __( 'List managers', 'entrystage' ) );
		echo '<div class="estga-chips"><span class="estga-chip is-locked"><span>' . esc_html( translate_user_role( 'Administrator' ) ) . '</span></span>';
		foreach ( $roles as $role => $name ) {
			if ( 'administrator' === $role ) {
				continue;
			}
			echo '<label class="estga-chip"><input type="checkbox" name="estg[manager_roles][]" value="' . esc_attr( $role ) . '"' . checked( in_array( $role, $settings['manager_roles'], true ), true, false ) . '><span>' . esc_html( $name ) . '</span></label>';
		}
		echo '</div><p class="estga-help">' . esc_html__( 'Managers see and handle every entry. Administrators and users who can edit Gravity Forms entries are always managers.', 'entrystage' ) . '</p>';
		self::card_close();

		self::card_open( __( 'Page of the form', 'entrystage' ) );
		echo '<label class="estga-label" for="estga-page">' . esc_html__( 'Edit and Duplicate open the entry on this page', 'entrystage' ) . '</label>';
		echo '<select id="estga-page" name="estg[page_id]" class="estga-input">';
		$auto  = ! empty( $settings['page_auto'] ) && ! empty( $settings['page_id'] ) ? get_the_title( (int) $settings['page_id'] ) : '';
		/* translators: %s: page title */
		$first = '' !== $auto ? sprintf( __( 'Automatic: %s', 'entrystage' ), $auto ) : __( 'Automatic', 'entrystage' );
		echo '<option value="">' . esc_html( $first ) . '</option>';
		$chosen = absint( rgar( $raw, 'page_id' ) );
		foreach ( self::pages() as $id => $title ) {
			echo '<option value="' . (int) $id . '"' . selected( $chosen, (int) $id, false ) . '>' . esc_html( $title ) . '</option>';
		}
		echo '</select>';
		if ( ! empty( $settings['page_id'] ) ) {
			/* translators: %s: page title */
			echo '<p class="estga-ok">' . esc_html( sprintf( __( 'Form found on the page "%s".', 'entrystage' ), get_the_title( (int) $settings['page_id'] ) ) ) . '</p>';
		} else {
			echo '<p class="estga-warn" data-estga-nopage>' . esc_html__( 'No page displaying this form was found: Edit and Duplicate stay hidden until one is chosen.', 'entrystage' ) . '</p>';
		}
		self::card_close();
		echo '</div>';
		self::panel_close();

		// 2. Columns.
		self::panel_open( 'columns' );
		echo '<div class="estga-builder" data-estga-builder>';
		echo '<section class="estga-card estga-zone estga-zone-avail" aria-labelledby="estga-avail-h"><h3 id="estga-avail-h" class="estga-h">' . esc_html__( 'Available fields', 'entrystage' ) . '</h3>';
		echo '<label class="estga-label" for="estga-search">' . esc_html__( 'Find a field', 'entrystage' ) . '</label><input type="search" id="estga-search" class="estga-input" data-estga-search placeholder="' . esc_attr__( 'Name, type or number…', 'entrystage' ) . '">';
		/* translators: %s: number of fields of the form */
		echo '<p class="estga-help">' . esc_html( sprintf( _n( '%s field in this form.', '%s fields in this form.', count( Fields::listable( $form ) ), 'entrystage' ), number_format_i18n( count( Fields::listable( $form ) ) ) ) ) . '</p>';
		echo '<ul class="estga-list" data-estga-zone="avail"></ul>';
		echo '<p class="estga-help">' . esc_html__( 'HTML, Section, Page break, CAPTCHA, Password and Credit card fields are not offered.', 'entrystage' ) . '</p></section>';
		// the columns and the details are stacked on the right, each card on two lines.
		echo '<div class="estga-stack">';
		echo '<section class="estga-card estga-zone estga-zone-cols" aria-labelledby="estga-cols-h"><div class="estga-zone-head"><span class="estga-fig-1" aria-hidden="true">1</span><h3 id="estga-cols-h" class="estga-h">' . esc_html__( 'Columns of the list', 'entrystage' ) . '</h3><span class="estga-count" data-estga-count></span></div>';
		echo '<p class="estga-help">' . esc_html__( 'From left to right in the list. Width: for example 120px or 15%; empty: automatic. The Actions column always comes last; on phones each row becomes a card.', 'entrystage' ) . '</p>';
		echo '<ol class="estga-list" data-estga-zone="cols"></ol></section>';
		echo '</div>';
		echo '<div class="estga-live" aria-live="polite" data-estga-live></div>';
		echo '</div>';
		echo '<input type="hidden" name="estg[columns]" data-estga-columns value="">';
		self::panel_close();

		// 4. Actions.
		self::panel_open( 'actions' );
		echo '<div class="estga-grid">';
		self::card_open( __( 'Buttons of each row', 'entrystage' ) );
		foreach ( array(
			'view'      => __( 'View', 'entrystage' ),
			'edit'      => __( 'Edit', 'entrystage' ),
			'duplicate' => __( 'Duplicate', 'entrystage' ),
			'archive'   => __( 'Archive', 'entrystage' ),
		) as $action => $label ) {
			echo '<div class="estga-action-row"><label class="estga-switch">';
			self::checkbox( $action, ! empty( $settings[ $action ] ) );
			echo '<span class="estga-track" aria-hidden="true"></span><span class="estga-action-name">' . esc_html( $label ) . '</span></label>';
			/* translators: %s: button name */
			echo '<input type="text" class="estga-input" name="estg[' . esc_attr( $action ) . '_label]" value="' . esc_attr( $settings[ $action . '_label' ] ) . '" aria-label="' . esc_attr( sprintf( __( 'Label of the "%s" button', 'entrystage' ), $label ) ) . '">';
			echo '</div>';
		}
		self::text( 'actions_header', __( 'Header of the actions column', 'entrystage' ), $settings['actions_header'] );
		self::card_close();
		self::card_open( __( 'How buttons look', 'entrystage' ) );
		self::segmented(
			'action_display',
			$settings['action_display'],
			array(
				'icons' => __( 'Icons', 'entrystage' ),
				'both'  => __( 'Icons and labels', 'entrystage' ),
				'text'  => __( 'Labels', 'entrystage' ),
			),
			__( 'How buttons look', 'entrystage' )
		);
		echo '<p class="estga-label">' . esc_html__( 'Archive means', 'entrystage' ) . '</p>';
		self::segmented(
			'archive_mode',
			$settings['archive_mode'],
			array(
				'trash'  => __( 'Gravity Forms trash', 'entrystage' ),
				'delete' => __( 'Permanent deletion', 'entrystage' ),
			),
			__( 'Archive means', 'entrystage' )
		);
		self::text( 'archive_confirm', __( 'Question before archiving (empty: none)', 'entrystage' ), $settings['archive_confirm'] );
		self::select(
			'edit_notifications',
			__( 'Notifications after an edit', 'entrystage' ),
			$settings['edit_notifications'],
			array(
				'skip' => __( 'Do not send them', 'entrystage' ),
				'send' => __( 'Send them, as for a new submission', 'entrystage' ),
			)
		);
		self::select(
			'after_edit',
			__( 'After an edit or a copy', 'entrystage' ),
			$settings['after_edit'],
			array(
				'list'         => __( 'Go back to the list', 'entrystage' ),
				'confirmation' => __( 'Show the form confirmation', 'entrystage' ),
			)
		);
		self::card_close();
		echo '</div>';
		self::panel_close();


		// 6. Display.
		self::panel_open( 'display' );
		echo '<div class="estga-grid">';
		self::card_open( __( 'Sorting and pages', 'entrystage' ) );
		echo '<div class="estga-pair">';
		echo '<label class="estga-label" for="estga-per">' . esc_html__( 'Rows per page', 'entrystage' ) . '<input id="estga-per" type="number" min="1" max="500" class="estga-input" name="estg[per_page]" value="' . esc_attr( $settings['per_page'] ) . '"></label>';
		$sort_choices = array(
			'date_created' => __( 'Date of the entry', 'entrystage' ),
			'id'           => __( 'Entry ID', 'entrystage' ),
		);
		// the columns of the list (and the current choice), not every field of the form.
		$listable = Fields::listable( $form );
		$keys     = is_array( $settings['columns'] ) ? wp_list_pluck( $settings['columns'], 'key' ) : array();
		$keys[]   = (string) $settings['sort_key'];
		foreach ( array_unique( $keys ) as $id ) {
			if ( isset( $listable[ $id ] ) && '' !== Fields::sort_key( $listable[ $id ], 'default' ) ) {
				$sort_choices[ $id ] = Fields::label( $listable[ $id ] );
			}
		}
		echo '<label class="estga-label" for="estga-sort">' . esc_html__( 'Initial sort', 'entrystage' ) . '<select id="estga-sort" class="estga-input" name="estg[sort_key]">';
		foreach ( $sort_choices as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( (string) $settings['sort_key'], (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></div>';
		echo '<p class="estga-help">' . esc_html__( 'Initial sort: date, entry ID or one of the columns of the list (save after changing the columns to see them here).', 'entrystage' ) . '</p>';
		self::segmented(
			'sort_dir',
			$settings['sort_dir'],
			array(
				'DESC' => __( 'Newest / Z to A first', 'entrystage' ),
				'ASC'  => __( 'Oldest / A to Z first', 'entrystage' ),
			),
			__( 'Sort direction', 'entrystage' )
		);
		self::text( 'empty_text', __( 'Text when there is no entry', 'entrystage' ), $settings['empty_text'] );
		echo '<p class="estga-label">' . esc_html__( 'Thumbnail size', 'entrystage' ) . '</p>';
		self::segmented(
			'thumb_size',
			(string) $settings['thumb_size'],
			array(
				'48' => __( '48 px', 'entrystage' ),
				'96' => __( '96 px', 'entrystage' ),
			),
			__( 'Thumbnail size', 'entrystage' )
		);
		echo '<p class="estga-help">' . esc_html__( 'Thumbnails load the original picture: keep 25 rows per page or fewer when a column shows pictures.', 'entrystage' ) . '</p>';
		self::card_close();
		self::card_open( __( 'Tools above the list', 'entrystage' ) );
		self::toggle( 'search', ! empty( $settings['search'] ), __( 'Search box', 'entrystage' ) );
		self::toggle( 'print', ! empty( $settings['print'] ), __( 'Print button', 'entrystage' ), __( 'Opens a printable page with the entries matching the filters, every page (at most 500).', 'entrystage' ) );
		self::card_close();
		self::card_open( __( 'Files', 'entrystage' ) );
		self::toggle( 'secure_files', ! empty( $settings['secure_files'] ), __( 'Downloads only for people who may see the entry', 'entrystage' ), __( 'Off by default. Logged-in users only; the check covers the Gravity Forms download links of this form.', 'entrystage' ) );
		echo '<div class="estga-warn" data-estga-show="secure_files" role="note">' . esc_html__( 'File links in notifications will no longer open for a recipient who is not logged in, and visitors of a public list will not see the thumbnails.', 'entrystage' ) . '</div>';
		self::card_close();
		echo '</div>';
		self::panel_close();

		// 6. Shortcode: a composer and ready-made examples, built from this form.
		self::panel_open( 'shortcode' );
		self::shortcode_panel( $form, $settings );
		self::panel_close();

		echo '<div class="estga-footer"><button type="submit" class="estga-save">' . esc_html__( 'Save', 'entrystage' ) . '</button></div>';
		echo '</form>';
		echo '<script type="application/json" id="estga-data">' . wp_json_encode( self::data( $form, $settings ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
		echo '</div>';
	}

	/**
	 * Saves the posted settings.
	 *
	 * @param Addon $addon Add-on.
	 * @param array $form  Form object.
	 * @return array array( message, is error, step to show ).
	 */
	private static function save( Addon $addon, $form ) {
		$form_id = (int) $form['id'];
		check_admin_referer( 'estg_atelier_' . $form_id, 'estg_atelier_nonce' );
		if ( ! \GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			return array( __( 'You are not allowed to change these settings.', 'entrystage' ), true, 'access' );
		}
		$in   = isset( $_POST['estg'] ) && is_array( $_POST['estg'] ) ? wp_unslash( $_POST['estg'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is sanitized below.
		$on   = function ( $key ) use ( $in ) {
			return ! empty( $in[ $key ] ) ? '1' : '0';
		};
		$text = function ( $key ) use ( $in ) {
			return isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? sanitize_text_field( (string) $in[ $key ] ) : '';
		};
		$pick = function ( $key, array $allowed, $default ) use ( $in ) {
			$value = isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? (string) $in[ $key ] : '';
			return in_array( $value, $allowed, true ) ? $value : $default;
		};
		$d     = Settings::defaults();
		$roles = array_keys( self::roles() );
		$raw   = array(
			'enabled'            => $on( 'enabled' ),
			'access'             => $pick( 'access', array( 'everyone', 'loggedin', 'roles' ), $d['access'] ),
			'scope'              => $pick( 'scope', array( 'own', 'all' ), $d['scope'] ),
			'page_id'            => (string) absint( rgar( $in, 'page_id' ) ),
			'per_page'           => (string) max( 1, min( 500, absint( rgar( $in, 'per_page' ) ) ? absint( $in['per_page'] ) : $d['per_page'] ) ),
			'sort_key'           => Settings::clean_key( rgar( $in, 'sort_key' ) ),
			'sort_dir'           => $pick( 'sort_dir', array( 'ASC', 'DESC' ), $d['sort_dir'] ),
			'empty_text'         => $text( 'empty_text' ),
			'actions_header'     => $text( 'actions_header' ),
			'action_display'     => $pick( 'action_display', array( 'icons', 'both', 'text' ), $d['action_display'] ),
			'archive_mode'       => $pick( 'archive_mode', array( 'trash', 'delete' ), $d['archive_mode'] ),
			'archive_confirm'    => $text( 'archive_confirm' ),
			'edit_notifications' => $pick( 'edit_notifications', array( 'send', 'skip' ), $d['edit_notifications'] ),
			'after_edit'         => $pick( 'after_edit', array( 'list', 'confirmation' ), $d['after_edit'] ),
			'search'             => $on( 'search' ),
			'print'              => $on( 'print' ),
			'thumb_size'         => $pick( 'thumb_size', array( '48', '96' ), '48' ),
			'secure_files'       => $on( 'secure_files' ),
			'settings_version'   => ENTRYSTAGE_VERSION,
		);
		$actions = array( 'view', 'edit', 'duplicate', 'archive' );
		foreach ( $actions as $action ) {
			$raw[ $action ]            = $on( $action );
			$raw[ $action . '_label' ] = $text( $action . '_label' );
		}
		foreach ( array( 'access_roles' => 'access_role_', 'manager_roles' => 'manager_role_' ) as $key => $prefix ) {
			$posted = isset( $in[ $key ] ) && is_array( $in[ $key ] ) ? array_map( 'sanitize_key', $in[ $key ] ) : array();
			foreach ( $roles as $role ) {
				$raw[ $prefix . $role ] = in_array( $role, $posted, true ) ? '1' : '0';
			}
		}
		$raw['manager_role_administrator'] = '1';

		// The column lists are written by the page script; when it did not run, the saved lists are kept.
		$current               = Settings::get( $form );
		$posted_columns   = trim( (string) rgar( $in, 'columns' ) );
		$settings         = Settings::from_raw( $raw, $form );
		$raw['columns']   = '' !== $posted_columns ? Settings::parse_columns( $posted_columns, $form, $settings ) : (array) $current['columns'];
		$raw['show_date'] = in_array( '_date', wp_list_pluck( $raw['columns'], 'key' ), true ) ? '1' : '0';
		// The Status column belongs to EntryStage Pro: it is kept at its place, for when Pro is activated again.
		$stored = (array) rgar( (array) $addon->get_form_settings( $form ), 'columns', array() );
		foreach ( $stored as $i => $item ) {
			if ( is_array( $item ) && '_status' === rgar( $item, 'key' ) && ! in_array( '_status', wp_list_pluck( $raw['columns'], 'key' ), true ) ) {
				array_splice( $raw['columns'], min( (int) $i, count( $raw['columns'] ) ), 0, array( $item ) );
			}
		}

		// Settings of the other edition (EntryStage Pro or the free EntryStage) that this page does not show are kept.
		$saved = $addon->get_form_settings( $form );
		if ( is_array( $saved ) ) {
			$raw = array_merge( $saved, $raw );
		}

		/**
		 * Filters the settings of a form before they are saved from the settings page.
		 *
		 * @param array $raw  Settings.
		 * @param array $form Form object.
		 */
		$raw  = (array) apply_filters( 'estg_save_settings', $raw, $form );
		$step = $pick( 'step', array_keys( self::steps() ), 'access' );
		if ( ! $addon->save_form_settings( $form, $raw ) ) {
			return array( __( 'The settings could not be saved.', 'entrystage' ), true, $step );
		}
		return array( __( 'Settings saved.', 'entrystage' ), false, $step );
	}

	/**
	 * Data read by the page script.
	 *
	 * @param array $form     Form object.
	 * @param array $settings Form settings.
	 * @return array
	 */
	private static function data( $form, array $settings ) {
		$fields = array();
		foreach ( Fields::listable( $form ) as $field ) {
			$fields[] = Fields::describe( $field );
		}
		return array(
			'fields'     => $fields,
			'columns'    => array_values( (array) $settings['columns'] ),
			'fixed'      => array(
				'_status' => __( 'Status', 'entrystage' ),
				'_date'   => __( 'Date', 'entrystage' ),
			),
			'thumb'      => (int) $settings['thumb_size'],
		);
	}

	/**
	 * Address of a published page that shows this list, or ''.
	 *
	 * @param int $form_id Form id.
	 * @return string
	 */
	private static function list_page_url( $form_id ) {
		$key   = 'estg_list_page_' . (int) $form_id;
		$found = get_transient( $key );
		if ( false === $found ) {
			global $wpdb;
			$found = 0;
			$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one query, cached below.
				$wpdb->prepare(
					"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status IN ('publish','private') AND post_type NOT IN ('revision','nav_menu_item','attachment') AND post_content LIKE %s ORDER BY ID ASC LIMIT 200",
					'%' . $wpdb->esc_like( '[entrystage' ) . '%'
				)
			);
			foreach ( (array) $rows as $row ) {
				if ( preg_match( '/\[entrystage\b[^\]]*\b(?:form|id)\s*=\s*["\']?' . (int) $form_id . '\b/i', (string) $row->post_content ) ) {
					$found = (int) $row->ID;
					break;
				}
			}
			set_transient( $key, $found, HOUR_IN_SECONDS );
		}
		return $found ? (string) get_permalink( (int) $found ) : '';
	}

	/**
	 * Editable roles.
	 *
	 * @return array role => name.
	 */
	private static function roles() {
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		$out = array();
		foreach ( get_editable_roles() as $role => $info ) {
			$out[ $role ] = translate_user_role( $info['name'] );
		}
		return $out;
	}

	/**
	 * Published and private pages.
	 *
	 * @return array id => title.
	 */
	private static function pages() {
		$out   = array();
		$pages = get_pages(
			array(
				'post_status' => 'publish,private',
				'sort_column' => 'post_title',
			)
		);
		foreach ( (array) $pages as $page ) {
			$out[ (int) $page->ID ] = '' !== $page->post_title ? $page->post_title : '#' . $page->ID;
		}
		return $out;
	}

	/**
	 * Opens a step panel.
	 *
	 * @param string $slug Step.
	 */
	private static function panel_open( $slug ) {
		$steps = self::steps();
		echo '<section class="estga-panel" id="estga-panel-' . esc_attr( $slug ) . '" data-estga-panel="' . esc_attr( $slug ) . '" aria-label="' . esc_attr( $steps[ $slug ] ) . '">';
	}

	/** Closes a step panel. */
	private static function panel_close() {
		echo '</section>';
	}

	/**
	 * Opens a card.
	 *
	 * @param string $title Title.
	 */
	private static function card_open( $title ) {
		echo '<div class="estga-card"><h3 class="estga-h">' . esc_html( $title ) . '</h3>';
	}

	/** Closes a card. */
	private static function card_close() {
		echo '</div>';
	}

	/**
	 * Checkbox input.
	 *
	 * @param string $name    Setting.
	 * @param bool   $checked Checked.
	 * @return string
	 */
	private static function checkbox( $name, $checked ) {
		echo '<input type="checkbox" name="estg[' . esc_attr( $name ) . ']" value="1" ';
		checked( $checked, true );
		echo ' data-estga-input="' . esc_attr( $name ) . '">';
	}

	/**
	 * Switch with a label and an optional help line.
	 *
	 * @param string $name    Setting.
	 * @param bool   $checked Checked.
	 * @param string $label   Label.
	 * @param string $help    Help.
	 */
	private static function toggle( $name, $checked, $label, $help = '' ) {
		echo '<label class="estga-switch estga-switch-block">';
		self::checkbox( $name, $checked );
		echo '<span class="estga-track" aria-hidden="true"></span><span class="estga-switch-text"><span class="estga-switch-label">' . esc_html( $label ) . '</span>' . ( '' !== $help ? '<span class="estga-help">' . esc_html( $help ) . '</span>' : '' ) . '</span></label>';
	}

	/**
	 * Shortcode step: a composer (read by the page script) and examples taken from this form.
	 *
	 * @param array $form     Form object.
	 * @param array $settings Form settings.
	 */
	private static function shortcode_panel( $form, array $settings ) {
		$form_id = (int) $form['id'];
		$base    = '[entrystage form="' . $form_id . '"]';
		echo '<div class="estga-grid">';
		self::card_open( __( 'Build the shortcode', 'entrystage' ) );
		echo '<p class="estga-help">' . esc_html__( 'Paste it in a Shortcode block of any page. The options only narrow what this list shows: the rights stay those of the Access step.', 'entrystage' ) . '</p>';
		echo '<div class="estga-sc" data-estga-sc data-form="' . esc_attr( $form_id ) . '">';
		echo '<label class="estga-label">' . esc_html__( 'Entries of', 'entrystage' ) . '<select class="estga-input" data-sc="user"><option value="">' . esc_html__( 'Everyone (as set in Access)', 'entrystage' ) . '</option><option value="current">' . esc_html__( 'The logged-in person only', 'entrystage' ) . '</option><option value="id">' . esc_html__( 'One user (ID)…', 'entrystage' ) . '</option></select></label>';
		echo '<label class="estga-label" data-sc-show="user=id">' . esc_html__( 'User ID', 'entrystage' ) . '<input type="number" min="1" class="estga-input" data-sc="user_id"></label>';
		echo '<div class="estga-pair">';
		/* translators: %d: rows per page set in the Display step */
		echo '<label class="estga-label">' . esc_html__( 'Rows per page', 'entrystage' ) . '<input type="number" min="1" max="500" class="estga-input" data-sc="per_page" placeholder="' . esc_attr( $settings['per_page'] ) . '"></label>';
		echo '<label class="estga-label">' . esc_html__( 'Page of the form (for Edit and Duplicate)', 'entrystage' ) . '<select class="estga-input" data-sc="page"><option value="">' . esc_html__( 'The one chosen in Access', 'entrystage' ) . '</option>';
		foreach ( self::pages() as $id => $title ) {
			echo '<option value="' . (int) $id . '">' . esc_html( $title ) . '</option>';
		}
		echo '</select></label></div>';
		echo '<p class="estga-help">' . esc_html__( 'Edit and Duplicate open the Gravity Forms form filled in with the entry, so they need a page that shows this form. By default it is the page chosen in the Access step; choose another one only when this list must send people to a different page (for example one page per agency, each showing the form).', 'entrystage' ) . '</p>';
		echo '<div class="estga-code estga-sc-out"><code data-sc-out>' . esc_html( $base ) . '</code><button type="button" class="estga-copy" data-estga-copy="' . esc_attr( $base ) . '" data-sc-copy>' . esc_html__( 'Copy', 'entrystage' ) . '</button></div>';
		echo '</div>';
		self::card_close();

		// Examples, with real ids of this form.
		self::card_open( __( 'Examples', 'entrystage' ) );
		$examples = array(
			array( $base, __( 'The list, with the settings of this form.', 'entrystage' ) ),
			array( '[entrystage form="' . $form_id . '" user="current"]', __( 'A "My entries" page: each logged-in person sees only their own entries, even when the Access step shows all entries.', 'entrystage' ) ),
		);
		$examples[] = array( '[entrystage form="' . $form_id . '" per_page="50"]', __( '50 rows per page on this page only.', 'entrystage' ) );
		$examples[] = array( '[entrystage form="' . $form_id . '" user="5"]', __( 'Only the entries created by the user with ID 5.', 'entrystage' ) );
		// Personal space: this form and another form whose list is enabled (or a form number to replace).
		$other = 0;
		foreach ( (array) \GFAPI::get_forms( true, false ) as $candidate ) {
			if ( (int) $candidate['id'] !== $form_id && ! empty( Settings::get( $candidate )['enabled'] ) ) {
				$other = (int) $candidate['id'];
				break;
			}
		}
		$examples[] = array(
			'[entrystage forms="' . $form_id . ',' . ( $other ? $other : 12 ) . '" user="current"]',
			$other
				/* translators: 1: id of this form, 2: id of another form */
				? sprintf( __( 'Personal space: one tab per form (here forms %1$d and %2$d), with the entries of the person logged in.', 'entrystage' ), $form_id, $other )
				: __( 'Personal space: one tab per form, with the entries of the person logged in. Replace 12 with the ID of another form whose list is enabled.', 'entrystage' ),
		);
		echo '<ul class="estga-examples">';
		foreach ( $examples as $example ) {
			echo '<li><div class="estga-code"><code>' . esc_html( $example[0] ) . '</code><button type="button" class="estga-copy" data-estga-copy="' . esc_attr( $example[0] ) . '">' . esc_html__( 'Copy', 'entrystage' ) . '</button></div><p class="estga-help">' . esc_html( $example[1] ) . '</p></li>';
		}
		echo '</ul>';
		echo '<p class="estga-help">' . esc_html__( 'Lists of different forms can be shown on the same page.', 'entrystage' ) . '</p>';
		self::card_close();
		echo '</div>';
	}

	/**
	 * Segmented control (radio buttons).
	 *
	 * @param string $name    Setting.
	 * @param string $current Current value.
	 * @param array  $choices value => label.
	 * @param string $legend  Accessible name of the group.
	 */
	private static function segmented( $name, $current, array $choices, $legend ) {
		echo '<fieldset class="estga-seg"><legend class="screen-reader-text">' . esc_html( $legend ) . '</legend>';
		foreach ( $choices as $value => $label ) {
			echo '<label><input type="radio" name="estg[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '"' . checked( (string) $current, (string) $value, false ) . ' data-estga-input="' . esc_attr( $name ) . '"><span>' . esc_html( $label ) . '</span></label>';
		}
		echo '</fieldset>';
	}

	/**
	 * Text box with its label.
	 *
	 * @param string $name  Setting.
	 * @param string $label Label.
	 * @param string $value Value.
	 */
	private static function text( $name, $label, $value ) {
		echo '<label class="estga-label">' . esc_html( $label ) . '<input type="text" class="estga-input" name="estg[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '" data-estga-input="' . esc_attr( $name ) . '"></label>';
	}

	/**
	 * Drop-down with its label.
	 *
	 * @param string $name    Setting.
	 * @param string $label   Label.
	 * @param string $current Current value.
	 * @param array  $choices value => label.
	 */
	private static function select( $name, $label, $current, array $choices ) {
		echo '<label class="estga-label">' . esc_html( $label ) . '<select class="estga-input" name="estg[' . esc_attr( $name ) . ']" data-estga-input="' . esc_attr( $name ) . '">';
		foreach ( $choices as $value => $text ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select></label>';
	}
}
