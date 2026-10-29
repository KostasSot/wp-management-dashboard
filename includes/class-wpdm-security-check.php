<?php
/**
 * Security health checklist.
 *
 * Quick hardening checks with pass/fail results and a remediation hint for
 * every failure. Checks marked fixable can be repaired in one click via fix().
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Security_Check {

	/**
	 * Option that turns XML-RPC off when set by the "Fix" action.
	 */
	const XMLRPC_OPTION = 'wpdm_disable_xmlrpc';

	/**
	 * Register hooks for hardening applied through fix().
	 */
	public static function init() {
		if ( get_option( self::XMLRPC_OPTION ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
		}
	}

	/**
	 * Run every check.
	 *
	 * @return array[] Each item: id, label, pass (bool), detail (hint when
	 *                 failing), fixable (bool, fix() can repair it).
	 */
	public static function checks() {
		global $wpdb;

		$checks = array();

		$checks[] = array(
			'id'     => 'https',
			'label'  => __( 'Site served over HTTPS', 'wp-dashboard-management' ),
			'pass'   => 0 === strpos( site_url(), 'https://' ),
			'detail' => __( 'Install an SSL certificate and update the site URL to https://.', 'wp-dashboard-management' ),
		);

		$checks[] = array(
			'id'      => 'file_edit',
			'label'   => __( 'File editing in wp-admin disabled', 'wp-dashboard-management' ),
			'pass'    => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
			'fixable' => true,
			'detail'  => __( "Add define( 'DISALLOW_FILE_EDIT', true ); to wp-config.php so a compromised admin account cannot edit plugin/theme code.", 'wp-dashboard-management' ),
		);

		$checks[] = array(
			'id'      => 'admin_user',
			'label'   => __( 'No user account named "admin"', 'wp-dashboard-management' ),
			'pass'    => false === username_exists( 'admin' ),
			'detail'  => __( 'Rename the "admin" account to a unique username — it is the first username brute-force attacks try. Fix keeps its password, posts and role.', 'wp-dashboard-management' ),
			'fixable' => true,
		);

		$core_secure = self::core_version_secure();
		if ( null !== $core_secure ) {
			$checks[] = array(
				'id'     => 'core_version',
				'label'  => __( 'WordPress version has no known vulnerabilities', 'wp-dashboard-management' ),
				'pass'   => $core_secure,
				'detail' => __( 'This WordPress release is flagged insecure on wordpress.org — update as soon as possible.', 'wp-dashboard-management' ),
			);
		}

		$wp_display_errors = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY && defined( 'WP_DEBUG' ) && WP_DEBUG;
		$display_errors    = $wp_display_errors
			|| in_array( strtolower( (string) ini_get( 'display_errors' ) ), array( '1', 'on', 'true', 'stdout' ), true );
		$checks[]          = array(
			'id'      => 'display_errors',
			'label'   => __( 'PHP errors are not displayed publicly', 'wp-dashboard-management' ),
			'pass'    => ! $display_errors,
			// Only the wp-config.php cause is fixable; php.ini is the host's.
			'fixable' => $wp_display_errors,
			'detail'  => __( 'Set WP_DEBUG_DISPLAY to false (and display_errors off) so error messages never leak paths or queries to visitors.', 'wp-dashboard-management' ),
		);

		$checks[] = array(
			'id'     => 'table_prefix',
			'label'  => __( 'Custom database table prefix', 'wp-dashboard-management' ),
			'pass'   => 'wp_' !== $wpdb->prefix,
			'detail' => __( 'The default wp_ prefix makes automated SQL-injection payloads easier to write. Low priority, but worth changing on new installs.', 'wp-dashboard-management' ),
		);

		$checks[] = array(
			'id'      => 'xmlrpc',
			'label'   => __( 'XML-RPC disabled', 'wp-dashboard-management' ),
			'pass'    => ! apply_filters( 'xmlrpc_enabled', true ),
			'fixable' => true,
			'detail'  => __( 'xmlrpc.php is a common brute-force and DDoS amplification target. Disable it unless something (Jetpack, remote publishing) needs it.', 'wp-dashboard-management' ),
		);

		$checks[] = array(
			'id'      => 'registration',
			'label'   => __( 'Open user registration disabled', 'wp-dashboard-management' ),
			'pass'    => ! get_option( 'users_can_register' ),
			'fixable' => true,
			'detail'  => __( 'Anyone can create an account on this site (Settings → General → Membership). Disable it unless the site needs public registration.', 'wp-dashboard-management' ),
		);

		$config_path = WPDM_Config::path();
		if ( '' !== $config_path ) {
			$perms    = fileperms( $config_path );
			$checks[] = array(
				'id'      => 'config_perms',
				'label'   => __( 'wp-config.php not world-writable', 'wp-dashboard-management' ),
				'pass'    => ! ( $perms & 0002 ),
				'fixable' => true,
				'detail'  => __( 'wp-config.php is writable by every system user. Tighten it to 644 or stricter.', 'wp-dashboard-management' ),
			);
		}

		foreach ( $checks as $i => $check ) {
			$checks[ $i ] += array( 'fixable' => false );
		}

		return $checks;
	}

	/**
	 * Repair a failing, fixable check.
	 *
	 * @param string $id   Check id.
	 * @param array  $args Extra input (admin_user: new_login).
	 * @return string|WP_Error Success message.
	 */
	public static function fix( $id, $args = array() ) {
		$check = wp_list_filter( self::checks(), array( 'id' => $id ) );
		$check = reset( $check );
		if ( ! $check || $check['pass'] || ! $check['fixable'] ) {
			return new WP_Error( 'wpdm_fix_unavailable', __( 'This check cannot be fixed automatically.', 'wp-dashboard-management' ) );
		}

		switch ( $id ) {
			case 'file_edit':
				$result = WPDM_Config::set_constant( 'DISALLOW_FILE_EDIT', true );
				break;
			case 'display_errors':
				$result = WPDM_Config::set_constant( 'WP_DEBUG_DISPLAY', false );
				break;
			case 'admin_user':
				$result = self::rename_admin_user( isset( $args['new_login'] ) ? $args['new_login'] : '' );
				break;
			case 'xmlrpc':
				$result = update_option( self::XMLRPC_OPTION, 1 );
				break;
			case 'registration':
				$result = update_option( 'users_can_register', 0 );
				break;
			case 'config_perms':
				$path   = WPDM_Config::path();
				$result = @chmod( $path, fileperms( $path ) & 0775 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
				clearstatcache( true, $path );
				if ( ! $result ) {
					$result = new WP_Error( 'wpdm_chmod_failed', __( 'Permissions could not be changed — the file is owned by another system user. Ask your host to set it to 644.', 'wp-dashboard-management' ) );
				}
				break;
			default:
				$result = false;
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			return new WP_Error( 'wpdm_fix_failed', __( 'The fix could not be applied.', 'wp-dashboard-management' ) );
		}

		/* translators: %s: check label */
		return sprintf( __( 'Fixed: %s', 'wp-dashboard-management' ), $check['label'] );
	}

	/**
	 * Rename the "admin" account in place.
	 *
	 * WordPress has no API for changing user_login, so the row is updated
	 * directly. Password, role and authored content stay with the account.
	 *
	 * @param string $new_login Requested username.
	 * @return true|WP_Error
	 */
	private static function rename_admin_user( $new_login ) {
		global $wpdb;

		$new_login = sanitize_user( (string) $new_login, true );
		if ( '' === $new_login || 'admin' === strtolower( $new_login ) || ! validate_username( $new_login ) ) {
			return new WP_Error( 'wpdm_bad_login', __( 'Enter a valid username other than "admin".', 'wp-dashboard-management' ) );
		}
		if ( username_exists( $new_login ) ) {
			return new WP_Error( 'wpdm_login_taken', __( 'That username is already taken.', 'wp-dashboard-management' ) );
		}

		$user = get_user_by( 'login', 'admin' );
		if ( ! $user ) {
			return new WP_Error( 'wpdm_no_admin', __( 'The "admin" account no longer exists.', 'wp-dashboard-management' ) );
		}

		$data = array( 'user_login' => $new_login );
		// The author URL (/author/admin/) would still reveal the old login.
		if ( 'admin' === $user->user_nicename ) {
			$data['user_nicename'] = sanitize_title( $new_login );
		}
		if ( 'admin' === $user->display_name ) {
			$data['display_name'] = $new_login;
		}

		if ( false === $wpdb->update( $wpdb->users, $data, array( 'ID' => $user->ID ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return new WP_Error( 'wpdm_rename_failed', __( 'The account could not be renamed.', 'wp-dashboard-management' ) );
		}

		// Clears the cache keys of the old login.
		clean_user_cache( $user );

		if ( is_multisite() ) {
			$super_admins = get_site_option( 'site_admins', array() );
			$key          = array_search( 'admin', $super_admins, true );
			if ( false !== $key ) {
				$super_admins[ $key ] = $new_login;
				update_site_option( 'site_admins', $super_admins );
			}
		}

		// The auth cookie embeds the login; reissue it so the user stays signed in.
		if ( get_current_user_id() === $user->ID ) {
			wp_set_auth_cookie( $user->ID );
		}

		return true;
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
