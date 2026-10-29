<?php
/**
 * Known-vulnerability scan for core, plugins, and themes.
 *
 * Data comes from WPVulnerability.net (free, no API key). Only slugs and the
 * core version leave the site; plugin and theme versions are matched
 * locally against each advisory's affected range. Runs daily via WP-Cron
 * and stores the results, so the dashboard never waits on remote requests.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Vulnerabilities {

	const API       = 'https://www.wpvulnerability.net/';
	const OPTION    = 'wpdm_vulnerabilities';
	const CRON_HOOK = 'wpdm_vulnerability_scan';

	/**
	 * CVSS severity letters used by the API.
	 */
	const SEVERITIES = array(
		'c' => 'critical',
		'h' => 'high',
		'm' => 'medium',
		'l' => 'low',
	);

	/**
	 * Register the daily scan.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'scan' ) );

		$enabled   = WPDM_Settings::enabled( 'vulnerabilities' );
		$scheduled = wp_next_scheduled( self::CRON_HOOK );
		if ( $enabled && ! $scheduled ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		} elseif ( ! $enabled && $scheduled ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Check every component and store the vulnerable ones.
	 *
	 * @return array See results().
	 */
	public static function scan() {
		global $wp_version;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$components = array(
			array(
				'type'    => 'core',
				'slug'    => $wp_version,
				'name'    => 'WordPress',
				'version' => $wp_version,
			),
		);
		foreach ( get_plugins() as $file => $data ) {
			if ( '.' !== dirname( $file ) ) {
				$components[] = array(
					'type'    => 'plugin',
					'slug'    => dirname( $file ),
					'name'    => $data['Name'],
					'version' => $data['Version'],
				);
			}
		}
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$components[] = array(
				'type'    => 'theme',
				'slug'    => $stylesheet,
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
			);
		}

		$items  = array();
		$failed = 0;
		foreach ( $components as $component ) {
			$advisories = self::fetch( $component['type'], $component['slug'] );
			if ( null === $advisories ) {
				$failed++;
				continue;
			}

			$vulns = self::matching( $advisories, $component['version'] );
			if ( $vulns ) {
				$items[] = $component + array( 'vulns' => $vulns );
			}
		}

		$results = array(
			'checked_at' => time(),
			'items'      => $items,
			'failed'     => $failed,
		);
		update_option( self::OPTION, $results, false );

		return $results;
	}

	/**
	 * Stored results of the last scan.
	 *
	 * @return array{checked_at:int,items:array[],failed:int} checked_at is 0 before the first scan.
	 */
	public static function results() {
		$results = get_option( self::OPTION );
		if ( ! is_array( $results ) ) {
			return array(
				'checked_at' => 0,
				'items'      => array(),
				'failed'     => 0,
			);
		}
		return $results;
	}

	/**
	 * Advisories that affect an installed version, simplified for display.
	 *
	 * @param array  $advisories Raw "vulnerability" list from the API.
	 * @param string $version    Installed version.
	 * @return array[] Each item: title, severity, fixed_in, link.
	 */
	public static function matching( array $advisories, $version ) {
		$out = array();
		foreach ( $advisories as $advisory ) {
			if ( ! is_array( $advisory ) ) {
				continue;
			}
			$operator = isset( $advisory['operator'] ) && is_array( $advisory['operator'] ) ? $advisory['operator'] : array();
			if ( ! self::affects( $operator, (string) $version ) ) {
				continue;
			}

			$source = self::best_source( isset( $advisory['source'] ) && is_array( $advisory['source'] ) ? $advisory['source'] : array() );
			$title  = ! empty( $source['name'] ) ? $source['name'] : ( isset( $advisory['name'] ) ? $advisory['name'] : '' );
			$letter = isset( $advisory['impact']['cvss']['severity'] ) ? $advisory['impact']['cvss']['severity'] : '';

			$fixed = ! empty( $operator['max_version'] )
				&& isset( $operator['max_operator'] ) && 'lt' === $operator['max_operator']
				&& empty( $operator['unfixed'] );

			$out[] = array(
				// Plain text (titles contain "<="); escaped on output.
				'title'    => trim( wp_check_invalid_utf8( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) ) ),
				'severity' => isset( self::SEVERITIES[ $letter ] ) ? self::SEVERITIES[ $letter ] : '',
				'fixed_in' => $fixed ? sanitize_text_field( $operator['max_version'] ) : '',
				'link'     => ! empty( $source['link'] ) ? esc_url_raw( $source['link'] ) : '',
			);
		}
		return $out;
	}

	/**
	 * The most descriptive source of an advisory.
	 *
	 * CVE entries are named after their id ("CVE-2020-35489"); sources from
	 * WPScan, Wordfence or Patchstack describe the issue instead.
	 *
	 * @param array $sources Advisory "source" list.
	 * @return array
	 */
	private static function best_source( array $sources ) {
		foreach ( $sources as $source ) {
			if ( is_array( $source ) && ! empty( $source['name'] ) && ( ! isset( $source['id'] ) || $source['name'] !== $source['id'] ) ) {
				return $source;
			}
		}
		return isset( $sources[0] ) && is_array( $sources[0] ) ? $sources[0] : array();
	}

	/**
	 * Whether a version falls in an advisory's affected range.
	 *
	 * Core advisories have no range: the API already returns only the ones
	 * for the requested version.
	 *
	 * @param array  $operator min/max version + operator (lt, le, gt, ge).
	 * @param string $version  Installed version.
	 * @return bool
	 */
	public static function affects( array $operator, $version ) {
		$allowed = array( 'lt', 'le', 'gt', 'ge' );

		if ( ! empty( $operator['min_version'] ) && isset( $operator['min_operator'] ) && in_array( $operator['min_operator'], $allowed, true )
			&& ! version_compare( $version, $operator['min_version'], $operator['min_operator'] ) ) {
			return false;
		}
		if ( ! empty( $operator['max_version'] ) && isset( $operator['max_operator'] ) && in_array( $operator['max_operator'], $allowed, true )
			&& ! version_compare( $version, $operator['max_version'], $operator['max_operator'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Advisories for one component.
	 *
	 * @param string $type core|plugin|theme.
	 * @param string $slug Slug, or the version for core.
	 * @return array|null Null when the request failed.
	 */
	private static function fetch( $type, $slug ) {
		$response = wp_remote_get( self::API . $type . '/' . rawurlencode( $slug ) . '/', array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! empty( $data['error'] ) ) {
			return null;
		}

		// Unknown slugs return "vulnerability": null.
		return isset( $data['data']['vulnerability'] ) && is_array( $data['data']['vulnerability'] ) ? $data['data']['vulnerability'] : array();
	}
}
