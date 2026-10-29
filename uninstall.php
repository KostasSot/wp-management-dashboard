<?php
/**
 * Uninstall cleanup for WP Dashboard Management.
 *
 * Removes the plugin's settings and cached data. Log files and database
 * snapshots in wp-content/uploads are intentionally left in place — they
 * may be the only backup the site has.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpdm_settings' );
delete_option( 'wpdm_debug_log_path' );
delete_option( 'wpdm_disable_xmlrpc' );
delete_option( 'wpdm_block_user_enum' );
delete_option( 'wpdm_vulnerabilities' );
delete_option( 'wpdm_abandoned_plugins' );
delete_transient( 'wpdm_security_probes' );
delete_transient( 'wpdm_fatal_alert_sent' );
delete_site_transient( 'wpdm_core_versions' );
delete_transient( 'wpdm_activation_redirect' );
