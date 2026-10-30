<?php
/**
 * Loading of EntryStage: classes, translations and the Gravity Forms add-on.
 *
 * @package EntryStage
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the add-on once Gravity Forms is ready.
 */
function load() {
	if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
		return;
	}
	\GFForms::include_addon_framework();

	require_once ENTRYSTAGE_DIR . 'includes/class-fields.php';
	require_once ENTRYSTAGE_DIR . 'includes/class-settings.php';
	require_once ENTRYSTAGE_DIR . 'includes/class-access.php';
	require_once ENTRYSTAGE_DIR . 'includes/class-query.php';
	require_once ENTRYSTAGE_DIR . 'includes/class-urls.php';
	require_once ENTRYSTAGE_DIR . 'includes/class-renderer.php';
	require_once ENTRYSTAGE_DIR . 'includes/class-actions.php';
	require_once ENTRYSTAGE_DIR . 'includes/class-editor.php';
	require_once ENTRYSTAGE_DIR . 'includes/class-details.php';
	require_once ENTRYSTAGE_DIR . 'includes/class-atelier.php';
	require_once ENTRYSTAGE_DIR . 'includes/class-block.php';
	require_once ENTRYSTAGE_DIR . 'includes/class-addon.php';

	\GFAddOn::register( __NAMESPACE__ . '\\Addon' );

	// Registered now: Gravity Forms delivers file downloads on "init", before add-ons run their own init().
	Details::hooks();
}
add_action( 'gform_loaded', __NAMESPACE__ . '\\load', 5 );


/**
 * Admin notice when Gravity Forms is missing.
 */
function missing_gravity_forms_notice() {
	if ( class_exists( 'GFForms' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'EntryStage requires Gravity Forms to be installed and active.', 'entrystage' ) . '</p></div>';
}
add_action( 'admin_notices', __NAMESPACE__ . '\\missing_gravity_forms_notice' );

/**
 * Returns the add-on instance (or null before Gravity Forms is loaded).
 *
 * @return Addon|null
 */
function addon() {
	return class_exists( __NAMESPACE__ . '\\Addon' ) ? Addon::get_instance() : null;
}
