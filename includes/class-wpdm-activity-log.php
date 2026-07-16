<?php
/**
 * Integration with the Beqlic Activity Log plugin.
 *
 * Detects install/active state, can activate it on request, and reads
 * summary data from its log table for the dashboard charts.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Activity_Log {

	/**
	 * Plugin header name we look for among installed plugins.
	 */
	const PLUGIN_NAME = 'Beqlic Activity Log';

	/**
	 * Find the Beqlic plugin's basename (dir/file.php) among installed plugins.
	 *
	 * Matched by plugin Name header so the folder name doesn't matter.
	 *
	 * @return string Empty string when not installed.
	 */
	public static function plugin_basename() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $file => $data ) {
			if ( 0 === stripos( $data['Name'], self::PLUGIN_NAME ) ) {
				return $file;
			}
		}
		return '';
	}

	/**
	 * Install/active status.
	 *
	 * @return array{installed:bool,active:bool,basename:string}
	 */
	public static function status() {
		$basename = self::plugin_basename();
		return array(
			'installed' => '' !== $basename,
			'active'    => '' !== $basename && is_plugin_active( $basename ),
			'basename'  => $basename,
		);
	}

	/**
	 * The GitHub zip URL used to install the plugin when missing.
	 *
	 * @return string
	 */
	public static function repo_zip_url() {
		/**
		 * Filter the download URL for the Beqlic Activity Log package.
		 *
		 * @param string $url Zip URL (GitHub branch or release asset).
		 */
		return apply_filters( 'wpdm_beqlic_repo_zip', WPDM_BEQLIC_REPO_ZIP );
	}

	/**
	 * Download and install the plugin from GitHub, then activate it.
	 *
	 * @return true|WP_Error
	 */
	public static function install_from_github() {
		$url = self::repo_zip_url();
		if ( '' === $url ) {
			return new WP_Error( 'wpdm_no_repo', __( 'No repository URL is configured (WPDM_BEQLIC_REPO_ZIP).', 'wp-dashboard-management' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( is_wp_error( $skin->result ) ) {
			return $skin->result;
		}
		if ( ! $result ) {
			return new WP_Error( 'wpdm_install_failed', __( 'The plugin package could not be installed.', 'wp-dashboard-management' ) );
		}

		// Refresh the plugin cache so the new folder is picked up.
		wp_clean_plugins_cache( true );

		return self::activate();
	}

	/**
	 * Activate the Beqlic plugin.
	 *
	 * @return true|WP_Error
	 */
	public static function activate() {
		$basename = self::plugin_basename();
		if ( '' === $basename ) {
			return new WP_Error( 'wpdm_not_installed', __( 'Beqlic Activity Log is not installed.', 'wp-dashboard-management' ) );
		}
		$result = activate_plugin( $basename );
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * The activity log table name.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bal_activity_log';
	}

	/**
	 * Whether the log table exists.
	 *
	 * @return bool
	 */
	private static function table_exists() {
		global $wpdb;
		$table = self::table();
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Chart + list data for the dashboard.
	 *
	 * @param int $days Window size in days.
	 * @return array|null Null when the table is unavailable.
	 */
	public static function stats( $days = 14 ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return null;
		}

		$days  = max( 1, (int) $days );
		$table = self::table();
		$since = gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp' ) - ( ( $days - 1 ) * DAY_IN_SECONDS ) );

		// Events per day.
		$per_day_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS day, COUNT(*) AS c FROM {$table} WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY day ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			),
			OBJECT_K
		);

		$per_day = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day             = gmdate( 'Y-m-d', current_time( 'timestamp' ) - ( $i * DAY_IN_SECONDS ) );
			$per_day[ $day ] = isset( $per_day_rows[ $day ] ) ? (int) $per_day_rows[ $day ]->c : 0;
		}

		// Counts by severity.
		$severity_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT severity, COUNT(*) AS c FROM {$table} WHERE created_at >= %s GROUP BY severity", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			)
		);
		$by_severity   = array();
		foreach ( (array) $severity_rows as $row ) {
			$by_severity[ $row->severity ] = (int) $row->c;
		}

		// Counts by action (top 6).
		$action_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT action, COUNT(*) AS c FROM {$table} WHERE created_at >= %s GROUP BY action ORDER BY c DESC LIMIT 6", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			)
		);
		$by_action   = array();
		foreach ( (array) $action_rows as $row ) {
			$label               = '' !== $row->action ? $row->action : __( '(none)', 'wp-dashboard-management' );
			$by_action[ $label ] = (int) $row->c;
		}

		// Top users.
		$top_users = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_name, COUNT(*) AS c FROM {$table} WHERE created_at >= %s AND user_id > 0 GROUP BY user_id, user_name ORDER BY c DESC LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			)
		);

		// Latest events for the list.
		$recent = (array) $wpdb->get_results(
			"SELECT created_at, user_name, action, severity, object_type, object_title, message FROM {$table} ORDER BY id DESC LIMIT 10" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return array(
			'days'        => $days,
			'total'       => array_sum( $per_day ),
			'per_day'     => $per_day,
			'by_severity' => $by_severity,
			'by_action'   => $by_action,
			'top_users'   => $top_users,
			'recent'      => $recent,
		);
	}
}
