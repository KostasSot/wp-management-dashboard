<?php
/**
 * WordPress / PHP / server environment information.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_System_Info {

	/**
	 * Collect environment info for the dashboard.
	 *
	 * @return array
	 */
	public static function get() {
		global $wpdb, $wp_version;

		$core_update = self::core_update_available();

		return array(
			'wp_version'        => $wp_version,
			'wp_latest'         => $core_update ? $core_update : '',
			'wp_needs_update'   => (bool) $core_update,
			'php_version'       => PHP_VERSION,
			'db_version'        => $wpdb->db_version(),
			'db_server_info'    => method_exists( $wpdb, 'db_server_info' ) ? $wpdb->db_server_info() : '',
			'server_software'   => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : php_sapi_name(),
			'memory_limit'      => ini_get( 'memory_limit' ),
			'wp_memory_limit'   => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '',
			'max_execution'     => ini_get( 'max_execution_time' ),
			'upload_max'        => ini_get( 'upload_max_filesize' ),
			'debug_enabled'     => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'debug_log_enabled' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'debug_display'     => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
			'site_url'          => site_url(),
			'is_multisite'      => is_multisite(),
			'active_theme'      => wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ),
			'timezone'          => wp_timezone_string(),
		);
	}

	/**
	 * Latest available core version if an update exists.
	 *
	 * @return string Empty string when up to date.
	 */
	private static function core_update_available() {
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$updates = get_core_updates( array( 'dismissed' => false ) );
		if ( is_array( $updates ) ) {
			foreach ( $updates as $update ) {
				if ( isset( $update->response ) && 'upgrade' === $update->response ) {
					return $update->version;
				}
			}
		}

		return '';
	}
}
