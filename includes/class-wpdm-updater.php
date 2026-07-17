<?php
/**
 * Self-updates from GitHub.
 *
 * Compares the local version against the Version header of the plugin's
 * main file on the repo's main branch. When the remote is newer, the
 * update is injected into WordPress's normal update flow — it appears on
 * the Plugins screen, Updates screen, and this plugin's own dashboard,
 * and installs with the standard one-click updater.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Updater {

	const CACHE_KEY = 'wpdm_remote_version';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Register hooks (no-op until a repo is configured).
	 */
	public static function init() {
		if ( '' === self::repo() ) {
			return;
		}
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_dir' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
	}

	/**
	 * The GitHub repo in "owner/name" form.
	 *
	 * @return string
	 */
	public static function repo() {
		/**
		 * Filter the GitHub repository the plugin updates itself from.
		 *
		 * @param string $repo "owner/name".
		 */
		return apply_filters( 'wpdm_github_repo', WPDM_GITHUB_REPO );
	}

	/**
	 * Drop the cached remote version (used by "Check for updates").
	 */
	public static function flush_cache() {
		delete_site_transient( self::CACHE_KEY );
	}

	/**
	 * Latest version on the repo's main branch.
	 *
	 * @return string Empty string when unknown.
	 */
	public static function remote_version() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$url      = sprintf( 'https://raw.githubusercontent.com/%s/main/wp-dashboard-management.php', self::repo() );
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		$version = '';
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = wp_remote_retrieve_body( $response );
			if ( preg_match( '/^\s*\*\s*Version:\s*([0-9][0-9a-zA-Z.\-]*)\s*$/m', $body, $m ) ) {
				$version = $m[1];
			}
		}

		if ( '' !== $version ) {
			set_site_transient( self::CACHE_KEY, $version, self::CACHE_TTL );
		}

		return $version;
	}

	/**
	 * Zip package URL for the main branch.
	 *
	 * @return string
	 */
	private static function package_url() {
		return sprintf( 'https://github.com/%s/archive/refs/heads/main.zip', self::repo() );
	}

	/**
	 * Inject our update into the update_plugins transient.
	 *
	 * @param object $transient The transient value being saved.
	 * @return object
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$basename = plugin_basename( WPDM_PLUGIN_FILE );
		$remote   = self::remote_version();

		$item = (object) array(
			'id'          => 'github.com/' . self::repo(),
			'slug'        => 'wp-dashboard-management',
			'plugin'      => $basename,
			'new_version' => $remote ? $remote : WPDM_VERSION,
			'url'         => 'https://github.com/' . self::repo(),
			'package'     => self::package_url(),
		);

		if ( $remote && version_compare( $remote, WPDM_VERSION, '>' ) ) {
			$transient->response[ $basename ] = $item;
			unset( $transient->no_update[ $basename ] );
		} else {
			$transient->no_update[ $basename ] = $item;
			unset( $transient->response[ $basename ] );
		}

		return $transient;
	}

	/**
	 * GitHub zips extract to "<repo>-main/" — rename to the plugin slug so
	 * the update lands in the existing plugin folder.
	 *
	 * @param string      $source        Extracted source directory.
	 * @param string      $remote_source Upgrade staging directory.
	 * @param WP_Upgrader $upgrader      Upgrader instance.
	 * @param array       $hook_extra    Context (plugin basename on updates).
	 * @return string|WP_Error
	 */
	public static function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( ! isset( $hook_extra['plugin'] ) || plugin_basename( WPDM_PLUGIN_FILE ) !== $hook_extra['plugin'] ) {
			return $source;
		}

		// Match whatever folder the plugin is actually installed in (manual
		// GitHub-zip installs may use "<repo>-main"), so the update replaces
		// the existing folder instead of creating a second copy.
		$installed_dir = dirname( plugin_basename( WPDM_PLUGIN_FILE ) );
		$target        = trailingslashit( $remote_source ) . trailingslashit( $installed_dir );
		if ( untrailingslashit( $source ) === untrailingslashit( $target ) ) {
			return $source;
		}

		if ( function_exists( 'move_dir' ) ) {
			$moved = move_dir( $source, $target, true );
			if ( is_wp_error( $moved ) ) {
				return $moved;
			}
		} elseif ( ! rename( untrailingslashit( $source ), untrailingslashit( $target ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'wpdm_rename_failed', __( 'Could not rename the update package folder.', 'wp-dashboard-management' ) );
		}

		return $target;
	}

	/**
	 * Minimal "View details" popup content.
	 *
	 * @param false|object|array $result Result so far.
	 * @param string             $action plugins_api action.
	 * @param object             $args   Request args.
	 * @return false|object
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'wp-dashboard-management' !== $args->slug ) {
			return $result;
		}

		$remote = self::remote_version();

		return (object) array(
			'name'          => 'WP Dashboard Management',
			'slug'          => 'wp-dashboard-management',
			'version'       => $remote ? $remote : WPDM_VERSION,
			'author'        => 'Konstantinos Sotiriou',
			'homepage'      => 'https://github.com/' . self::repo(),
			'download_link' => self::package_url(),
			'sections'      => array(
				'description' => __( 'A modular site-management dashboard for WordPress. Updates are delivered from the GitHub repository.', 'wp-dashboard-management' ),
			),
		);
	}
}
