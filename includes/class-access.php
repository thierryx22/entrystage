<?php
/**
 * Who may see the list and act on an entry.
 *
 * Rules:
 *  - managers (administrators, the manager roles of the form, or users with the Gravity Forms
 *    "gravityforms_edit_entries" capability) see every entry, use every enabled button on every entry,
 *    and are the only ones to change statuses and export;
 *  - other users see the list according to the "access" setting and, with scope "own",
 *    only the entries they created; they use their buttons on their own entries only.
 *
 * @package EntryStage
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Access {

	/**
	 * The current user manages the list of this form.
	 *
	 * @param array $settings Form settings.
	 * @return bool
	 */
	public static function is_manager( array $settings ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( \GFCommon::current_user_can_any( 'gravityforms_edit_entries' ) ) {
			return true;
		}
		$user  = wp_get_current_user();
		$roles = array_intersect( (array) $user->roles, (array) $settings['manager_roles'] );
		return (bool) apply_filters( 'estg_is_manager', ! empty( $roles ), $settings );
	}

	/**
	 * The current user may display the list.
	 *
	 * @param array $settings Form settings.
	 * @return bool
	 */
	public static function can_see_list( array $settings ) {
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}
		if ( self::is_manager( $settings ) ) {
			return true;
		}
		switch ( $settings['access'] ) {
			case 'everyone':
				$ok = true;
				break;
			case 'roles':
				$ok = is_user_logged_in() && (bool) array_intersect( (array) wp_get_current_user()->roles, (array) $settings['access_roles'] );
				break;
			default:
				$ok = is_user_logged_in();
		}
		return (bool) apply_filters( 'estg_can_see_list', $ok, $settings );
	}

	/**
	 * Only the current user's entries are listed.
	 *
	 * @param array $settings Form settings.
	 * @return bool
	 */
	public static function own_only( array $settings ) {
		return 'own' === $settings['scope'] && ! self::is_manager( $settings );
	}

	/**
	 * The current user created the entry.
	 *
	 * @param array $entry Entry.
	 * @return bool
	 */
	public static function is_owner( $entry ) {
		return is_user_logged_in() && (int) rgar( $entry, 'created_by' ) > 0 && (int) rgar( $entry, 'created_by' ) === get_current_user_id();
	}

	/**
	 * The current user may see this entry in the list (and so its "More details" and its files).
	 *
	 * @param array $entry    Entry.
	 * @param array $settings Form settings.
	 * @return bool
	 */
	public static function can_see_entry( $entry, array $settings ) {
		$ok = ! empty( $entry ) && 'active' === rgar( $entry, 'status' ) && self::can_see_list( $settings )
			&& ( self::is_manager( $settings ) || 'all' === $settings['scope'] || self::is_owner( $entry ) );
		/**
		 * Filters whether the current user may see an entry of the list.
		 *
		 * @param bool  $ok       Permission.
		 * @param array $entry    Entry.
		 * @param array $settings Form settings.
		 */
		return (bool) apply_filters( 'estg_can_see_entry', $ok, $entry, $settings );
	}

	/**
	 * The current user may run an action on an entry.
	 *
	 * @param string $action   view | edit | duplicate | archive | pdf | status | export.
	 * @param array  $entry    Entry (empty array for list-wide actions such as export).
	 * @param array  $settings Form settings.
	 * @return bool
	 */
	public static function can( $action, $entry, array $settings ) {
		if ( ! self::can_see_list( $settings ) ) {
			return false;
		}
		$enabled = array(
			'view'      => ! empty( $settings['view'] ),
			'edit'      => ! empty( $settings['edit'] ),
			'duplicate' => ! empty( $settings['duplicate'] ),
			'archive'   => ! empty( $settings['archive'] ),
		);
		$ok = ! empty( $enabled[ $action ] );
		// Statuses and export: managers only. Outside managers, users act on their own entries;
		// with scope "all" they may also view them all (and their PDF).
		$manager_only = array( 'status', 'export' );
		$view_all     = array( 'view', 'pdf' );
		if ( $ok && in_array( $action, $manager_only, true ) ) {
			$ok = self::is_manager( $settings );
		} elseif ( $ok && ! empty( $entry ) && ! self::is_manager( $settings ) ) {
			$ok = self::is_owner( $entry ) || ( 'all' === $settings['scope'] && in_array( $action, $view_all, true ) );
		}
		if ( $ok && ! empty( $entry ) && 'active' !== rgar( $entry, 'status' ) ) {
			$ok = false;   // Trashed or spam entries are never handled from the list.
		}
		/**
		 * Filters a permission.
		 *
		 * @param bool   $ok       Permission.
		 * @param string $action   Action.
		 * @param array  $entry    Entry.
		 * @param array  $settings Form settings.
		 */
		return (bool) apply_filters( 'estg_can', $ok, $action, $entry, $settings );
	}
}
