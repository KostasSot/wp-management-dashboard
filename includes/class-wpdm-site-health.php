<?php
/**
 * Site Health summary sourced from WordPress core's own WP_Site_Health tests.
 *
 * Runs core's synchronous ("direct") tests and caches the results, since
 * several of them make loopback HTTP requests.
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

		$health = WP_Site_Health::get_instance();
		$tests  = WP_Site_Health::get_tests();

		$counts = array(
			'good'        => 0,
			'recommended' => 0,
			'critical'    => 0,
		);
		$issues = array();

		foreach ( $tests['direct'] as $test ) {
			$result = null;

			if ( is_string( $test['test'] ) && is_callable( array( $health, 'get_test_' . $test['test'] ) ) ) {
				$result = call_user_func( array( $health, 'get_test_' . $test['test'] ) );
			} elseif ( is_callable( $test['test'] ) ) {
				$result = call_user_func( $test['test'] );
			}

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
}
