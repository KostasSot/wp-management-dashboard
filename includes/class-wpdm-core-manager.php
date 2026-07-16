<?php
/**
 * WordPress core version switching (install any 6.0+ release).
 *
 * Version list comes from the wordpress.org stable-check API; installs go
 * through core's own Core_Upgrader with an explicit release package, which
 * works for both upgrades and rollbacks.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Core_Manager {

	const VERSIONS_API   = 'https://api.wordpress.org/core/stable-check/1.0/';
	const CACHE_KEY      = 'wpdm_core_versions';
	const CACHE_TTL      = 12 * HOUR_IN_SECONDS;
	const MIN_VERSION    = '6.0';

	/**
	 * All installable releases (>= 6.0), newest first.
	 *
	 * @return array[] Each item: version, status (latest|outdated|insecure).
	 */
	public static function available_versions() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get( self::VERSIONS_API, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		$versions = array();
		foreach ( $data as $version => $status ) {
			if ( version_compare( $version, self::MIN_VERSION, '>=' ) ) {
				$versions[] = array(
					'version' => $version,
					'status'  => $status,
				);
			}
		}

		usort(
			$versions,
			function ( $a, $b ) {
				return version_compare( $b['version'], $a['version'] );
			}
		);

		set_site_transient( self::CACHE_KEY, $versions, self::CACHE_TTL );

		return $versions;
	}

	/**
	 * Whether a version is in the installable list.
	 *
	 * @param string $version Version string.
	 * @return bool
	 */
	public static function is_known_version( $version ) {
		foreach ( self::available_versions() as $release ) {
			if ( $release['version'] === $version ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Install a specific core release (upgrade or rollback).
	 *
	 * @param string $version Target version, e.g. "6.8.2".
	 * @return true|WP_Error
	 */
	public static function install( $version ) {
		global $wp_version;

		if ( ! self::is_known_version( $version ) ) {
			return new WP_Error( 'wpdm_unknown_version', __( 'That is not a known WordPress release.', 'wp-dashboard-management' ) );
		}
		if ( $version === $wp_version ) {
			return new WP_Error( 'wpdm_same_version', __( 'That version is already installed.', 'wp-dashboard-management' ) );
		}

		// Safety net: snapshot the database before touching core (only when
		// the snapshots module is enabled for this site).
		if ( WPDM_Settings::enabled( 'snapshots' ) ) {
			$snapshot = WPDM_DB_Backup::create( 'pre-' . str_replace( '.', '-', $version ) );
			if ( is_wp_error( $snapshot ) ) {
				return $snapshot;
			}
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$package = 'https://downloads.wordpress.org/release/wordpress-' . $version . '.zip';

		// Offer object shaped like the update-core transient entries, with an
		// explicit package so Core_Upgrader installs exactly this release.
		$update = (object) array(
			'response'        => 'upgrade',
			'current'         => $version,
			'version'         => $version,
			'download'        => $package,
			'locale'          => 'en_US',
			'packages'        => (object) array(
				'full'        => $package,
				'no_content'  => false,
				'new_bundled' => false,
				'partial'     => false,
				'rollback'    => false,
			),
			'php_version'     => '7.2',
			'mysql_version'   => '5.0',
			'new_bundled'     => '',
			'partial_version' => false,
		);

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Core_Upgrader( $skin );
		$result   = $upgrader->upgrade(
			$update,
			array(
				'allow_relaxed_file_ownership' => true,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( is_wp_error( $skin->result ) ) {
			return $skin->result;
		}

		// Refresh update data so the dashboard reflects the new version.
		delete_site_transient( 'update_core' );
		wp_version_check( array(), true );

		return true;
	}
}
