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
delete_site_transient( 'wpdm_core_versions' );
delete_transient( 'wpdm_activation_redirect' );
