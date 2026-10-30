<?php
/**
 * Protection of the file links and, in EntryStage Pro, "More details": the second level of an
 * entry, loaded when its row is opened.
 *
 * Rules:
 *  - each row carries its own nonce, so only an entry drawn for the current visitor can be
 *    requested, and the rights are checked again on that entry (Access::can_see_entry());
 *  - with "secure_files" on, the Gravity Forms download links of the form require a logged-in
 *    user who may see the entry in the list (or who can view entries in the admin).
 *
 * @package EntryStage
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Details {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_filter( 'gform_require_login_pre_download', array( __CLASS__, 'require_login' ), 20, 3 );
		add_filter( 'gform_permission_granted_pre_download', array( __CLASS__, 'permission' ), 20, 4 );
	}

	/**
	 * Logged-in users only, for the files of a form whose list protects them.
	 *
	 * @param bool $require  Current value.
	 * @param int  $form_id  Form id.
	 * @param int  $field_id Field id.
	 * @return bool
	 */
	public static function require_login( $require, $form_id, $field_id = 0 ) {
		return $require || self::protects( $form_id );
	}

	/**
	 * The download is allowed only to people who may see the entry.
	 *
	 * Gravity Forms gives the entry id since version 2.9.28.1. With older versions the check stops
	 * at "logged in and allowed to see the list".
	 *
	 * @param bool $granted  Result of the Gravity Forms checks (valid link).
	 * @param int  $form_id  Form id.
	 * @param int  $field_id Field id.
	 * @param int  $entry_id Entry id (Gravity Forms 2.9.28.1 or later).
	 * @return bool
	 */
	public static function permission( $granted, $form_id, $field_id = 0, $entry_id = 0 ) {
		if ( ! $granted || ! self::protects( $form_id ) ) {
			return $granted;
		}
		if ( \GFCommon::current_user_can_any( array( 'gravityforms_view_entries', 'gravityforms_edit_entries' ) ) ) {
			return true;
		}
		$form     = \GFAPI::get_form( (int) $form_id );
		$settings = Settings::get( $form );
		if ( ! $entry_id ) {
			return Access::can_see_list( $settings );
		}
		$entry = \GFAPI::get_entry( (int) $entry_id );
		return ! is_wp_error( $entry ) && (int) $entry['form_id'] === (int) $form_id && Access::can_see_entry( $entry, $settings );
	}

	/**
	 * The list of this form protects its files.
	 *
	 * @param int $form_id Form id.
	 * @return bool
	 */
	private static function protects( $form_id ) {
		$form = $form_id ? \GFAPI::get_form( (int) $form_id ) : false;
		if ( ! $form ) {
			return false;
		}
		$settings = Settings::get( $form );
		return ! empty( $settings['enabled'] ) && ! empty( $settings['secure_files'] );
	}
}
