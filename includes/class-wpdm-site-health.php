<?php
/**
 * Site Health summary sourced from WordPress core's own WP_Site_Health tests.
 *
 * Runs core's synchronous ("direct") tests and caches the results, since
 * several of them make loopback HTTP requests. A few failures can be fixed
 * in one click via fix().
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Site_Health {

	const CACHE_KEY = 'wpdm_site_health';
	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Core test names fix() can repair without risk of data loss.
	 *
	 * @var string[]
	 */
	const FIXABLE = array( 'debug_enabled', 'update_temp_backup_writable', 'search_engine_visibility' );

	/**
	 * Run (or read cached) core Site Health tests.
	 *
	 * @param bool $force Skip the cache and re-run.
	 * @return array{counts:array,issues:array,checked_at:int}
	 */
	public static function results( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		self::load_core();

		$health = WP_Site_Health::get_instance();
		$tests  = WP_Site_Health::get_tests();

		$counts = array(
			'good'        => 0,
			'recommended' => 0,
			'critical'    => 0,
		);
		$issues = array();

		foreach ( $tests['direct'] as $name => $test ) {
			$result = self::run_test( $health, $test );
			if ( ! is_array( $result ) || empty( $result['status'] ) ) {
				continue;
			}

			$status = $result['status'];
			if ( ! isset( $counts[ $status ] ) ) {
				continue;
			}
			$counts[ $status ]++;

			if ( 'good' !== $status ) {
				$issues[] = array(
					'test'        => $name,
					'fixable'     => in_array( $name, self::FIXABLE, true ),
					'status'      => $status,
					'label'       => isset( $result['label'] ) ? wp_strip_all_tags( $result['label'] ) : '',
					'description' => isset( $result['description'] ) ? wp_strip_all_tags( $result['description'] ) : '',
				);
			}
		}

		// Critical issues first.
		usort(
			$issues,
			function ( $a, $b ) {
				if ( $a['status'] === $b['status'] ) {
					return strcasecmp( $a['label'], $b['label'] );
				}
				return 'critical' === $a['status'] ? -1 : 1;
			}
		);

		$results = array(
			'counts'     => $counts,
			'issues'     => $issues,
			'checked_at' => time(),
		);

		set_transient( self::CACHE_KEY, $results, self::CACHE_TTL );

		return $results;
	}

	/**
	 * Repair a failing, fixable core test.
	 *
	 * @param string $test Core test name (see FIXABLE).
	 * @return true|WP_Error
	 */
	public static function fix( $test ) {
		if ( ! in_array( $test, self::FIXABLE, true ) ) {
			return new WP_Error( 'wpdm_fix_unavailable', __( 'This test cannot be fixed automatically.', 'wp-dashboard-management' ) );
		}

		self::load_core();
		$tests  = WP_Site_Health::get_tests();
		$result = isset( $tests['direct'][ $test ] ) ? self::run_test( WP_Site_Health::get_instance(), $tests['direct'][ $test ] ) : null;
		if ( is_array( $result ) && isset( $result['status'] ) && 'good' === $result['status'] ) {
			delete_transient( self::CACHE_KEY );
			return new WP_Error( 'wpdm_fix_unneeded', __( 'This test already passes.', 'wp-dashboard-management' ) );
		}

		switch ( $test ) {
			case 'debug_enabled':
				$fixed = WPDM_Config::set_debug_mode( false );
				break;
			case 'update_temp_backup_writable':
				$fixed = self::create_temp_backup_dirs();
				break;
			case 'search_engine_visibility':
				$fixed = update_option( 'blog_public', '1' );
				break;
			default:
				$fixed = false;
		}

		if ( is_wp_error( $fixed ) ) {
			return $fixed;
		}
		if ( ! $fixed ) {
			return new WP_Error( 'wpdm_fix_failed', __( 'The fix could not be applied.', 'wp-dashboard-management' ) );
		}

		delete_transient( self::CACHE_KEY );

		return true;
	}

	/**
	 * Run one core test definition. Test names are not method names
	 * (e.g. debug_enabled → get_test_is_in_debug_mode), so use its callback.
	 *
	 * @param WP_Site_Health $health Core instance.
	 * @param array          $test   Entry from WP_Site_Health::get_tests().
	 * @return array|null
	 */
	private static function run_test( $health, $test ) {
		if ( is_string( $test['test'] ) && is_callable( array( $health, 'get_test_' . $test['test'] ) ) ) {
			return call_user_func( array( $health, 'get_test_' . $test['test'] ) );
		}
		if ( is_callable( $test['test'] ) ) {
			return call_user_func( $test['test'] );
		}
		return null;
	}

	/**
	 * Create the folders core uses to back up plugins/themes during updates.
	 *
	 * @return true|WP_Error
	 */
	private static function create_temp_backup_dirs() {
		$base = WP_CONTENT_DIR . '/upgrade-temp-backup';

		foreach ( array( $base, $base . '/plugins', $base . '/themes' ) as $dir ) {
			if ( ! wp_mkdir_p( $dir ) || ! wp_is_writable( $dir ) ) {
				return new WP_Error( 'wpdm_temp_backup_dir', __( 'wp-content/upgrade-temp-backup could not be created or is not writable — check the folder permissions.', 'wp-dashboard-management' ) );
			}
		}

		return true;
	}

	/**
	 * Load the admin includes core's Site Health tests depend on.
	 */
	private static function load_core() {
		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		if ( ! function_exists( 'got_url_rewrite' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}
}
