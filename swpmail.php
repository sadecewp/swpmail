<?php
/**
 * Plugin Name:       SWPMail - SMTP & Subscription Plugin 
 * Plugin URI:        https://github.com/sadecewp/swpmail
 * Description:       Professional email subscription, notifications and SMTP/API delivery for WordPress.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            SadeceWP
 * Author URI:        https://sadecewp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       swpmail
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SWPM_VERSION', '1.0.0' );
define( 'SWPM_PLUGIN_FILE', __FILE__ );
define( 'SWPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SWPM_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// Helper functions (must be available before classes load).
require_once SWPM_PLUGIN_DIR . 'includes/helpers.php';

// Core infrastructure (needed for activation/deactivation hooks).
require_once SWPM_PLUGIN_DIR . 'includes/class-loader.php';
require_once SWPM_PLUGIN_DIR . 'includes/class-activator.php';
require_once SWPM_PLUGIN_DIR . 'includes/class-deactivator.php';

// Main plugin class (loads all other dependencies).
require_once SWPM_PLUGIN_DIR . 'includes/class-swpmail.php';

register_activation_hook( __FILE__, array( 'SWPM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SWPM_Deactivator', 'deactivate' ) );

/**
 * Run the plugin.
 *
 * @since 1.0.0
 */
function swpm_run(): void {
	static $ran = false;
	if ( $ran ) {
		return;
	}
	$ran    = true;
	$plugin = new SWPMail();
	$plugin->run();
}
swpm_run();
