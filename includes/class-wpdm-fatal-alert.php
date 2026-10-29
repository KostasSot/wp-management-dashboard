<?php
/**
 * Email alert when a PHP fatal error is caught.
 *
 * Called from WPDM_Error_Logger's shutdown handler. Throttled to one email
 * per hour so an error on every request cannot flood the inbox.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Fatal_Alert {

	const THROTTLE_KEY = 'wpdm_fatal_alert_sent';
	const THROTTLE_TTL = HOUR_IN_SECONDS;

	/**
	 * Send the alert unless disabled or throttled.
	 *
	 * @param array $error error_get_last() result.
	 */
	public static function maybe_send( array $error ) {
		// The site is already failing; the alert must never add a second error.
		try {
			if ( ! function_exists( 'wp_mail' ) || ! WPDM_Settings::enabled( 'fatal_alerts' ) || get_transient( self::THROTTLE_KEY ) ) {
				return;
			}

			$report = WPDM_Settings::report_settings();
			if ( ! is_email( $report['email'] ) ) {
				return;
			}

			// Throttle before sending so an error inside wp_mail() cannot loop.
			set_transient( self::THROTTLE_KEY, time(), self::THROTTLE_TTL );

			$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$request = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

			$lines = array(
				/* translators: %s: site URL */
				sprintf( __( 'A PHP fatal error occurred on %s.', 'wp-dashboard-management' ), home_url() ),
				'',
				/* translators: %s: error message */
				sprintf( __( 'Error: %s', 'wp-dashboard-management' ), $error['message'] ),
				/* translators: 1: file path, 2: line number */
				sprintf( __( 'File: %1$s:%2$d', 'wp-dashboard-management' ), $error['file'], $error['line'] ),
				/* translators: %s: request path */
				sprintf( __( 'Request: %s', 'wp-dashboard-management' ), $request ? $request : '-' ),
				/* translators: %s: date and time */
				sprintf( __( 'Time: %s', 'wp-dashboard-management' ), wp_date( 'Y-m-d H:i:s T' ) ),
				'',
				/* translators: %s: dashboard URL */
				sprintf( __( 'Full log: %s', 'wp-dashboard-management' ), admin_url( 'admin.php?page=' . WPDM_Admin::MENU_SLUG ) ),
				__( 'Further fatal errors within the next hour are logged but not emailed.', 'wp-dashboard-management' ),
			);

			wp_mail(
				$report['email'],
				/* translators: %s: site name */
				sprintf( __( '[%s] PHP fatal error detected', 'wp-dashboard-management' ), $site ),
				implode( "\n", $lines )
			);
		} catch ( Throwable $e ) {
			return;
		}
	}
}
