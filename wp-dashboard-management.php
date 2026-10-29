<?php
/**
 * Plugin Name:       WP Dashboard Management
 * Description:       A site management dashboard: plugin update overview, active/inactive status, PHP error logs, WordPress & PHP environment info, and Beqlic Activity Log integration with charts.
 * Version:           1.2.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Konstantinos Sotiriou
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-dashboard-management
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'WPDM_VERSION', '1.2.1' );
define( 'WPDM_PLUGIN_FILE', __FILE__ );
define( 'WPDM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPDM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * GitHub zip the Beqlic Activity Log plugin is installed from when missing.
 * Point this at the repo's zip download (branch or release asset).
 */
/**
 * GitHub repository ("owner/name") this plugin updates itself from.
 * Leave empty to disable the self-update check.
 */
if ( ! defined( 'WPDM_GITHUB_REPO' ) ) {
	define( 'WPDM_GITHUB_REPO', 'KostasSot/wp-management-dashboard' );
}

if ( ! defined( 'WPDM_BEQLIC_REPO_ZIP' ) ) {
	define( 'WPDM_BEQLIC_REPO_ZIP', 'https://github.com/Becliq-Devs/beqlic-activity-log/archive/refs/heads/main.zip' );
}

require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-settings.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-wizard.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-error-logger.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-system-info.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-plugins-status.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-themes-status.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-core-manager.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-db-backup.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-config.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-export.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-security-check.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-site-health.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-updater.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-email-report.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-php-logs.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-activity-log.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-admin.php';
require_once WPDM_PLUGIN_DIR . 'includes/class-wpdm-ajax.php';

/**
 * Boot the plugin.
 */
function wpdm_init() {
	WPDM_Error_Logger::init();
	WPDM_Admin::init();
	WPDM_Wizard::init();
	WPDM_Ajax::init();
	WPDM_Export::init();
	WPDM_Updater::init();
	WPDM_Email_Report::init();
	WPDM_Security_Check::init();
}
add_action( 'plugins_loaded', 'wpdm_init' );

/**
 * On activation, flag a one-time redirect to the setup wizard.
 */
function wpdm_activate() {
	set_transient( 'wpdm_activation_redirect', 1, 60 );
}
register_activation_hook( __FILE__, 'wpdm_activate' );

/**
 * On deactivation, remove the scheduled report event.
 */
function wpdm_deactivate() {
	wp_clear_scheduled_hook( 'wpdm_send_report' );
}
register_deactivation_hook( __FILE__, 'wpdm_deactivate' );
