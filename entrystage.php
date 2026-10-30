<?php
/**
 * Plugin Name:       EntryStage
 * Description:       Front-end list of Gravity Forms entries: your users view, edit, duplicate and archive their entries, with thumbnails, files and search. Responsive.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Thierry Daniaux
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       entrystage
 * Domain Path:       /languages
 *
 * EntryStage is free software: you can redistribute it and/or modify it under the terms
 * of the GNU General Public License as published by the Free Software Foundation, either version 2 of
 * the License, or (at your option) any later version.
 */

namespace EntryStage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// When EntryStage Pro is active, it replaces this plugin: this edition stays idle.
if ( in_array( 'entrystage-pro/entrystage-pro.php', (array) get_option( 'active_plugins', array() ), true ) || array_key_exists( 'entrystage-pro/entrystage-pro.php', (array) get_site_option( 'active_sitewide_plugins', array() ) ) ) {
	return;
}

// Only one edition runs: EntryStage Pro replaces EntryStage. The first one loaded wins; the other shows a notice.
if ( defined( 'ENTRYSTAGE_VERSION' ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'EntryStage and EntryStage Pro are both active: only one of them runs. Deactivate EntryStage (the free edition) to use EntryStage Pro.', 'entrystage' ) . '</p></div>';
			}
		}
	);
	return;
}

define( 'ENTRYSTAGE_VERSION', '1.0.0' );
define( 'ENTRYSTAGE_FILE', __FILE__ );
define( 'ENTRYSTAGE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ENTRYSTAGE_URL', plugin_dir_url( __FILE__ ) );
define( 'ENTRYSTAGE_MIN_GF_VERSION', '2.5' );

// Page presenting EntryStage Pro, set by the build from features.json (pro_release).
// Empty: "EntryStage Pro will be available soon" is shown instead of the link.
define( 'ENTRYSTAGE_PRO_URL', '' );


require_once ENTRYSTAGE_DIR . 'includes/bootstrap.php';
