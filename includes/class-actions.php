<?php
/**
 * Handles the buttons of the list (archive, next status, bulk actions).
 *
 * Every list is one POST form protected by a nonce; each entry is then checked one by one
 * with Access::can(). After the action the visitor is redirected (Post/Redirect/Get) to the
 * list with a short message.
 *
 * @package EntryStage
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actions {


	/**
	 * Hooked on template_redirect.
	 */
	public static function maybe_handle() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '' ) ) {
			return;
		}
		if ( ! isset( $_POST['estg_do'], $_POST['estg_form'], $_POST['estg_list_nonce'] ) ) {
			return;
		}
		$form_id = absint( wp_unslash( $_POST['estg_form'] ) );
		$nonce   = sanitize_text_field( wp_unslash( $_POST['estg_list_nonce'] ) );
		$back    = Urls::safe_return( isset( $_POST['estg_return'] ) ? wp_unslash( $_POST['estg_return'] ) : '', home_url( '/' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by safe_return().

		if ( ! $form_id || ! wp_verify_nonce( $nonce, Urls::nonce_action( 'list', $form_id ) ) ) {
			self::redirect( $back, $form_id, 'denied' );
		}
		$form = \GFAPI::get_form( $form_id );
		if ( ! $form ) {
			self::redirect( $back, $form_id, 'denied' );
		}
		$settings = Settings::get( $form );
		if ( ! Access::can_see_list( $settings ) ) {
			self::redirect( $back, $form_id, 'denied' );
		}

		$do = sanitize_text_field( wp_unslash( $_POST['estg_do'] ) );

		$parts  = explode( '|', $do, 2 );
		$single = array( 'archive' );
		if ( 2 === count( $parts ) && in_array( $parts[0], $single, true ) ) {
			self::run_many( $form, $settings, $parts[0], array( absint( $parts[1] ) ), $back );
		}
		self::redirect( $back, $form_id, 'denied' );
	}

	/**
	 * Runs an action on entries and redirects.
	 *
	 * @param array  $form     Form object.
	 * @param array  $settings Form settings.
	 * @param string $action   archive | duplicate | next | status:<value>.
	 * @param int[]  $ids      Entry ids.
	 * @param string $back     Return address.
	 */
	private static function run_many( $form, array $settings, $action, array $ids, $back ) {
		$form_id = (int) $form['id'];
		$done    = 0;
		$denied  = 0;
		$allowed = array( 'archive' );
		if ( ! in_array( $action, $allowed, true ) ) {
			self::redirect( $back, $form_id, 'denied' );
		}
		$permission = 'next' === $action ? 'status' : $action;

		foreach ( $ids as $id ) {
			$entry = \GFAPI::get_entry( $id );
			if ( is_wp_error( $entry ) || (int) $entry['form_id'] !== $form_id || ! Access::can( $permission, $entry, $settings ) ) {
				++$denied;
				continue;
			}
			switch ( $action ) {
				case 'archive':
					$ok = self::archive( $entry, $settings );
					break;
				default:
					$ok = false;
			}
			if ( $ok ) {
				++$done;
			}
		}

		if ( ! $done ) {
			self::redirect( $back, $form_id, 'denied' );
		}
		$codes = array(
			'archive'   => 'archived',
		);
		self::redirect( $back, $form_id, $codes[ $action ], $done );
	}

	/**
	 * Archives an entry: moved to the Gravity Forms trash, or deleted when so configured.
	 *
	 * @param array $entry    Entry.
	 * @param array $settings Form settings.
	 * @return bool
	 */
	private static function archive( $entry, array $settings ) {
		if ( 'delete' === $settings['archive_mode'] ) {
			$result = \GFAPI::delete_entry( (int) $entry['id'] );
		} else {
			$result = \GFAPI::update_entry_property( (int) $entry['id'], 'status', 'trash' );
		}
		if ( is_wp_error( $result ) || false === $result ) {
			return false;
		}
		/**
		 * Fires after an entry was archived (trash) or deleted from the list.
		 *
		 * @param array  $entry Entry (before the change).
		 * @param string $mode  trash | delete.
		 */
		do_action( 'estg_entry_archived', $entry, $settings['archive_mode'] );
		return true;
	}


	/**
	 * Redirects to the list with a message, then stops.
	 *
	 * @param string $url     List address.
	 * @param int    $form_id Form id.
	 * @param string $code    Message code.
	 * @param int    $n       Number of entries.
	 */
	public static function redirect( $url, $form_id, $code, $n = 0 ) {
		$url = add_query_arg(
			array(
				'estg_f'   => (int) $form_id,
				'estg_msg' => $code,
				'estg_n'   => (int) $n,
			),
			remove_query_arg( array( 'estg_msg', 'estg_n', 'estg_view' ), $url )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
