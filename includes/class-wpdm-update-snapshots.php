<?php
/**
 * Automatic database snapshot before plugin and theme updates.
 *
 * Hooks the upgrader so manual, bulk, and automatic updates are all covered.
 * One snapshot per request (a bulk update is one request), and only the
 * newest few are kept so auto-updates cannot fill the disk.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Update_Snapshots {

	const LABEL = 'pre-update';
	const KEEP  = 5;

	/**
	 * Whether this request already took its snapshot.
	 *
	 * @var bool
	 */
	private static $taken = false;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'upgrader_pre_install', array( __CLASS__, 'before_install' ), 10, 2 );
	}

	/**
	 * Snapshot the database before an update replaces plugin or theme files.
	 *
	 * A failed snapshot never blocks the update: holding back a security
	 * release is worse than updating without a backup.
	 *
	 * @param bool|WP_Error $response   Upgrader response so far.
	 * @param array         $hook_extra Upgrader context; updates carry 'plugin' or 'theme'.
	 * @return bool|WP_Error Unchanged response.
	 */
	public static function before_install( $response, $hook_extra ) {
		if ( is_wp_error( $response ) || self::$taken ) {
			return $response;
		}
		if ( empty( $hook_extra['plugin'] ) && empty( $hook_extra['theme'] ) ) {
			return $response;
		}
		if ( ! WPDM_Settings::enabled( 'update_snapshots' ) ) {
			return $response;
		}

		self::$taken = true;

		$snapshot = WPDM_DB_Backup::create( self::LABEL );
		if ( is_wp_error( $snapshot ) ) {
			error_log( 'WPDM: pre-update snapshot failed: ' . $snapshot->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return $response;
		}

		WPDM_DB_Backup::prune( self::LABEL, self::KEEP );

		return $response;
	}
}
