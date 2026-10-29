<?php
/**
 * Install an earlier wordpress.org release of a plugin.
 *
 * Release list and package URLs come from the wordpress.org plugin API;
 * installs go through core's Plugin_Upgrader with overwrite_package, the
 * same path as uploading a replacement zip in wp-admin.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Plugin_Rollback {

	const CACHE_PREFIX = 'wpdm_plugin_versions_';
	const CACHE_TTL    = 12 * HOUR_IN_SECONDS;
	const MAX_VERSIONS = 30;

	/**
	 * Whether a plugin can be rolled back from the dashboard.
	 *
	 * @param string $file Plugin basename, e.g. "akismet/akismet.php".
	 * @return bool
	 */
	public static function supports( $file ) {
		return '.' !== dirname( $file ) && plugin_basename( WPDM_PLUGIN_FILE ) !== $file;
	}

	/**
	 * Installable releases, newest first.
	 *
	 * @param string $file Plugin basename.
	 * @return array|WP_Error version => package URL.
	 */
	public static function versions( $file ) {
		$valid = self::validate( $file );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$slug   = dirname( $file );
		$cached = get_site_transient( self::CACHE_PREFIX . md5( $slug ) );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$info = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array(
					'versions' => true,
					'sections' => false,
				),
			)
		);
		if ( is_wp_error( $info ) ) {
			return new WP_Error( 'wpdm_not_on_org', __( 'This plugin is not available on wordpress.org, so no earlier releases can be installed.', 'wp-dashboard-management' ) );
		}

		$versions = array();
		foreach ( (array) $info->versions as $version => $package ) {
			if ( 'trunk' !== $version && self::is_org_package( $package ) ) {
				$versions[ (string) $version ] = $package;
			}
		}
		uksort(
			$versions,
			function ( $a, $b ) {
				return version_compare( $b, $a );
			}
		);
		$versions = array_slice( $versions, 0, self::MAX_VERSIONS, true );

		if ( empty( $versions ) ) {
			return new WP_Error( 'wpdm_no_versions', __( 'wordpress.org lists no installable releases for this plugin.', 'wp-dashboard-management' ) );
		}

		set_site_transient( self::CACHE_PREFIX . md5( $slug ), $versions, self::CACHE_TTL );

		return $versions;
	}

	/**
	 * Replace a plugin with a specific release.
	 *
	 * @param string $file    Plugin basename.
	 * @param string $version Release from versions().
	 * @return true|WP_Error
	 */
	public static function install( $file, $version ) {
		$versions = self::versions( $file );
		if ( is_wp_error( $versions ) ) {
			return $versions;
		}
		if ( ! isset( $versions[ $version ] ) ) {
			return new WP_Error( 'wpdm_unknown_version', __( 'That is not a known release of this plugin.', 'wp-dashboard-management' ) );
		}

		$installed = get_plugins();
		if ( $installed[ $file ]['Version'] === $version ) {
			return new WP_Error( 'wpdm_same_version', __( 'That version is already installed.', 'wp-dashboard-management' ) );
		}

		// Older releases cannot undo database migrations; the snapshot can.
		if ( WPDM_Settings::enabled( 'snapshots' ) ) {
			$snapshot = WPDM_DB_Backup::create( 'pre-rollback' );
			if ( is_wp_error( $snapshot ) ) {
				return $snapshot;
			}
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install(
			$versions[ $version ],
			array(
				'overwrite_package'  => true,
				'clear_update_cache' => true,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( is_wp_error( $skin->result ) ) {
			return $skin->result;
		}
		if ( ! $result ) {
			return new WP_Error( 'wpdm_rollback_failed', __( 'The release could not be installed (check file permissions).', 'wp-dashboard-management' ) );
		}

		return true;
	}

	/**
	 * Basic checks shared by versions() and install().
	 *
	 * @param string $file Plugin basename.
	 * @return true|WP_Error
	 */
	private static function validate( $file ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed = get_plugins();
		if ( '' === $file || ! isset( $installed[ $file ] ) ) {
			return new WP_Error( 'wpdm_plugin_missing', __( 'Plugin not found.', 'wp-dashboard-management' ) );
		}
		if ( ! self::supports( $file ) ) {
			return new WP_Error( 'wpdm_rollback_unsupported', __( 'This plugin cannot be rolled back from the dashboard.', 'wp-dashboard-management' ) );
		}
		return true;
	}

	/**
	 * Only packages served by wordpress.org are installed.
	 *
	 * @param mixed $url Package URL from the API.
	 * @return bool
	 */
	private static function is_org_package( $url ) {
		return is_string( $url )
			&& 'https' === wp_parse_url( $url, PHP_URL_SCHEME )
			&& 'downloads.wordpress.org' === wp_parse_url( $url, PHP_URL_HOST );
	}
}
