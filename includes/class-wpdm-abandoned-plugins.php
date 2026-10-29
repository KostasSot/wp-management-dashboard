<?php
/**
 * Abandoned plugin detection.
 *
 * Asks wordpress.org about every installed plugin once a day and flags the
 * ones that were closed (often for security reasons) or have not had a
 * release in two years. Results are stored, so the dashboard never waits
 * on remote requests.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Abandoned_Plugins {

	const API         = 'https://api.wordpress.org/plugins/info/1.2/';
	const OPTION      = 'wpdm_abandoned_plugins';
	const CRON_HOOK   = 'wpdm_abandoned_scan';
	const STALE_AFTER = 2 * YEAR_IN_SECONDS;

	/**
	 * Register the daily scan.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'scan' ) );

		$enabled   = WPDM_Settings::enabled( 'abandoned' );
		$scheduled = wp_next_scheduled( self::CRON_HOOK );
		if ( $enabled && ! $scheduled ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		} elseif ( ! $enabled && $scheduled ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Check every installed wordpress.org plugin and store the flagged ones.
	 *
	 * @return array See results().
	 */
	public static function scan() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$flagged = array();
		foreach ( get_plugins() as $file => $data ) {
			$slug = dirname( $file );
			if ( '.' === $slug ) {
				continue;
			}

			$info = self::fetch( $slug );
			if ( null === $info ) {
				continue;
			}

			$entry = array(
				'file'         => $file,
				'name'         => $data['Name'],
				'version'      => $data['Version'],
				'status'       => '',
				'last_updated' => 0,
				'reason'       => '',
			);

			if ( ! empty( $info['closed'] ) ) {
				$entry['status']       = 'closed';
				$entry['last_updated'] = isset( $info['closed_date'] ) ? (int) strtotime( $info['closed_date'] ) : 0;
				$entry['reason']       = isset( $info['reason_text'] ) ? sanitize_text_field( $info['reason_text'] ) : '';
			} elseif ( ! empty( $info['last_updated'] ) ) {
				$updated = (int) strtotime( $info['last_updated'] );
				if ( $updated && $updated < time() - self::STALE_AFTER ) {
					$entry['status']       = 'stale';
					$entry['last_updated'] = $updated;
				}
			}

			if ( '' !== $entry['status'] ) {
				$flagged[] = $entry;
			}
		}

		$results = array(
			'checked_at' => time(),
			'plugins'    => $flagged,
		);
		update_option( self::OPTION, $results, false );

		return $results;
	}

	/**
	 * Stored results of the last scan.
	 *
	 * @return array{checked_at:int,plugins:array[]} checked_at is 0 before the first scan.
	 */
	public static function results() {
		$results = get_option( self::OPTION );
		if ( ! is_array( $results ) ) {
			return array(
				'checked_at' => 0,
				'plugins'    => array(),
			);
		}
		return $results;
	}

	/**
	 * Human status for a flagged plugin.
	 *
	 * @param array $plugin Entry from results().
	 * @return string
	 */
	public static function label( array $plugin ) {
		$date = $plugin['last_updated'] ? wp_date( get_option( 'date_format' ), $plugin['last_updated'] ) : '';
		if ( 'closed' === $plugin['status'] ) {
			$label = $date
				/* translators: %s: closure date */
				? sprintf( __( 'Closed on wordpress.org (%s)', 'wp-dashboard-management' ), $date )
				: __( 'Closed on wordpress.org', 'wp-dashboard-management' );
			return $plugin['reason'] ? $label . ' — ' . $plugin['reason'] : $label;
		}
		/* translators: %s: date of the last release */
		return sprintf( __( 'No release since %s', 'wp-dashboard-management' ), $date );
	}

	/**
	 * Plugin info from wordpress.org.
	 *
	 * Uses the raw API rather than plugins_api(), which turns a closed plugin
	 * into a WP_Error and drops the closure date and reason.
	 *
	 * @param string $slug Plugin directory name.
	 * @return array|null Null when the request failed or the plugin is not on wordpress.org.
	 */
	private static function fetch( $slug ) {
		$url = self::API . '?' . http_build_query(
			array(
				'action'  => 'plugin_information',
				'request' => array(
					'slug'   => $slug,
					'fields' => array(
						'sections'     => 0,
						'description'  => 0,
						'versions'     => 0,
						'screenshots'  => 0,
						'banners'      => 0,
						'icons'        => 0,
						'contributors' => 0,
						'reviews'      => 0,
						'ratings'      => 0,
					),
				),
			)
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		// Unknown slugs (premium or custom plugins) answer with an error and no "closed" flag.
		if ( isset( $data['error'] ) && empty( $data['closed'] ) ) {
			return null;
		}

		return $data;
	}
}
