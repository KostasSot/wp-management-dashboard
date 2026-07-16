<?php
/**
 * Read-only security health checklist.
 *
 * Quick hardening checks with pass/fail results and a remediation hint for
 * every failure. Nothing here changes the site.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Security_Check {

	/**
	 * Run every check.
	 *
	 * @return array[] Each item: label, pass (bool), detail (hint when failing).
	 */
	public static function checks() {
		global $wpdb;

		$checks = array();

		$checks[] = array(
			'label'  => __( 'Site served over HTTPS', 'wp-dashboard-management' ),
			'pass'   => 0 === strpos( site_url(), 'https://' ),
			'detail' => __( 'Install an SSL certificate and update the site URL to https://.', 'wp-dashboard-management' ),
		);

		$checks[] = array(
			'label'  => __( 'File editing in wp-admin disabled', 'wp-dashboard-management' ),
			'pass'   => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
			'detail' => __( "Add define( 'DISALLOW_FILE_EDIT', true ); to wp-config.php so a compromised admin account cannot edit plugin/theme code.", 'wp-dashboard-management' ),
		);

		$checks[] = array(
			'label'  => __( 'No user account named "admin"', 'wp-dashboard-management' ),
			'pass'   => false === username_exists( 'admin' ),
			'detail' => __( 'Create a new administrator with a unique username and delete the "admin" account — it is the first username brute-force attacks try.', 'wp-dashboard-management' ),
		);

		$core_secure = self::core_version_secure();
		if ( null !== $core_secure ) {
			$checks[] = array(
				'label'  => __( 'WordPress version has no known vulnerabilities', 'wp-dashboard-management' ),
				'pass'   => $core_secure,
				'detail' => __( 'This WordPress release is flagged insecure on wordpress.org — update as soon as possible.', 'wp-dashboard-management' ),
			);
		}

		$display_errors = ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY && defined( 'WP_DEBUG' ) && WP_DEBUG )
			|| in_array( strtolower( (string) ini_get( 'display_errors' ) ), array( '1', 'on', 'true', 'stdout' ), true );
		$checks[]       = array(
			'label'  => __( 'PHP errors are not displayed publicly', 'wp-dashboard-management' ),
			'pass'   => ! $display_errors,
			'detail' => __( 'Set WP_DEBUG_DISPLAY to false (and display_errors off) so error messages never leak paths or queries to visitors.', 'wp-dashboard-management' ),
		);

		$checks[] = array(
			'label'  => __( 'Custom database table prefix', 'wp-dashboard-management' ),
			'pass'   => 'wp_' !== $wpdb->prefix,
			'detail' => __( 'The default wp_ prefix makes automated SQL-injection payloads easier to write. Low priority, but worth changing on new installs.', 'wp-dashboard-management' ),
		);

		$checks[] = array(
			'label'  => __( 'XML-RPC disabled', 'wp-dashboard-management' ),
			'pass'   => ! apply_filters( 'xmlrpc_enabled', true ),
			'detail' => __( 'xmlrpc.php is a common brute-force and DDoS amplification target. Disable it unless something (Jetpack, remote publishing) needs it.', 'wp-dashboard-management' ),
		);

		$checks[] = array(
			'label'  => __( 'Open user registration disabled', 'wp-dashboard-management' ),
			'pass'   => ! get_option( 'users_can_register' ),
			'detail' => __( 'Anyone can create an account on this site (Settings → General → Membership). Disable it unless the site needs public registration.', 'wp-dashboard-management' ),
		);

		$config_path = WPDM_Config::path();
		if ( '' !== $config_path ) {
			$perms    = fileperms( $config_path );
			$checks[] = array(
				'label'  => __( 'wp-config.php not world-writable', 'wp-dashboard-management' ),
				'pass'   => ! ( $perms & 0002 ),
				'detail' => __( 'wp-config.php is writable by every system user. Tighten it to 644 or stricter.', 'wp-dashboard-management' ),
			);
		}

		return $checks;
	}

	/**
	 * Pass/fail summary.
	 *
	 * @return array{passed:int,total:int,checks:array}
	 */
	public static function summary() {
		$checks = self::checks();
		return array(
			'passed' => count( wp_list_filter( $checks, array( 'pass' => true ) ) ),
			'total'  => count( $checks ),
			'checks' => $checks,
		);
	}

	/**
	 * Whether the running core version is free of known vulnerabilities.
	 *
	 * @return bool|null Null when the release list is unavailable.
	 */
	private static function core_version_secure() {
		global $wp_version;

		foreach ( WPDM_Core_Manager::available_versions() as $release ) {
			if ( $release['version'] === $wp_version ) {
				return 'insecure' !== $release['status'];
			}
		}

		return null;
	}
}
