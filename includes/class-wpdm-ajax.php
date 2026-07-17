<?php
/**
 * AJAX handlers for dashboard actions.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Ajax {

	const NONCE_ACTION = 'wpdm_dashboard';

	/**
	 * Register handlers.
	 */
	public static function init() {
		add_action( 'wp_ajax_wpdm_refresh_updates', array( __CLASS__, 'refresh_updates' ) );
		add_action( 'wp_ajax_wpdm_clear_log', array( __CLASS__, 'clear_log' ) );
		add_action( 'wp_ajax_wpdm_activate_beqlic', array( __CLASS__, 'activate_beqlic' ) );
		add_action( 'wp_ajax_wpdm_activate_theme', array( __CLASS__, 'activate_theme' ) );
		add_action( 'wp_ajax_wpdm_toggle_plugin', array( __CLASS__, 'toggle_plugin' ) );
		add_action( 'wp_ajax_wpdm_install_beqlic', array( __CLASS__, 'install_beqlic' ) );
		add_action( 'wp_ajax_wpdm_install_core', array( __CLASS__, 'install_core' ) );
		add_action( 'wp_ajax_wpdm_create_backup', array( __CLASS__, 'create_backup' ) );
		add_action( 'wp_ajax_wpdm_restore_backup', array( __CLASS__, 'restore_backup' ) );
		add_action( 'wp_ajax_wpdm_delete_backup', array( __CLASS__, 'delete_backup' ) );
		add_action( 'wp_ajax_wpdm_toggle_debug', array( __CLASS__, 'toggle_debug' ) );
		add_action( 'wp_ajax_wpdm_save_report_settings', array( __CLASS__, 'save_report_settings' ) );
		add_action( 'wp_ajax_wpdm_send_report_now', array( __CLASS__, 'send_report_now' ) );
	}

	/**
	 * Shared capability + nonce check, plus an optional feature-flag check.
	 *
	 * @param string $feature Feature key that must be enabled for this action.
	 */
	private static function guard( $feature = '' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'wp-dashboard-management' ) ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( '' !== $feature && ! WPDM_Settings::enabled( $feature ) ) {
			wp_send_json_error( array( 'message' => __( 'This module is disabled. Enable it in Management → Setup.', 'wp-dashboard-management' ) ), 403 );
		}
	}

	/**
	 * Force a plugin/core update re-check.
	 */
	public static function refresh_updates() {
		self::guard( 'updates' );
		WPDM_Plugins_Status::refresh_update_data();
		wp_send_json_success( array( 'message' => __( 'Update data refreshed.', 'wp-dashboard-management' ) ) );
	}

	/**
	 * Empty the PHP debug log.
	 */
	public static function clear_log() {
		self::guard( 'php_logs' );
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'wpdm';
		if ( ! array_key_exists( $source, WPDM_PHP_Logs::sources() ) ) {
			$source = 'wpdm';
		}
		if ( WPDM_PHP_Logs::clear( $source ) ) {
			wp_send_json_success( array( 'message' => __( 'Log cleared.', 'wp-dashboard-management' ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'Could not clear the log file (missing or not writable).', 'wp-dashboard-management' ) ) );
	}

	/**
	 * Activate or deactivate a plugin.
	 */
	public static function toggle_plugin() {
		self::guard( 'manage' );
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to manage plugins.', 'wp-dashboard-management' ) ), 403 );
		}

		$file   = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '';
		$enable = isset( $_POST['enable'] ) && '1' === $_POST['enable'];

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed = get_plugins();
		if ( '' === $file || ! isset( $installed[ $file ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Plugin not found.', 'wp-dashboard-management' ) ) );
		}

		$name = $installed[ $file ]['Name'];

		if ( $enable ) {
			$result = activate_plugin( $file );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			/* translators: %s: plugin name */
			wp_send_json_success( array( 'message' => sprintf( __( '%s activated.', 'wp-dashboard-management' ), $name ) ) );
		}

		deactivate_plugins( $file );
		/* translators: %s: plugin name */
		wp_send_json_success( array( 'message' => sprintf( __( '%s deactivated.', 'wp-dashboard-management' ), $name ) ) );
	}

	/**
	 * Switch the active theme (deactivates the current one by definition).
	 */
	public static function activate_theme() {
		self::guard( 'manage' );
		if ( ! current_user_can( 'switch_themes' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to switch themes.', 'wp-dashboard-management' ) ), 403 );
		}

		$stylesheet = isset( $_POST['stylesheet'] ) ? sanitize_text_field( wp_unslash( $_POST['stylesheet'] ) ) : '';
		$theme      = wp_get_theme( $stylesheet );

		if ( '' === $stylesheet || ! $theme->exists() ) {
			wp_send_json_error( array( 'message' => __( 'Theme not found.', 'wp-dashboard-management' ) ) );
		}
		if ( ! $theme->is_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'This theme is not allowed on this site.', 'wp-dashboard-management' ) ) );
		}
		if ( $theme->errors() ) {
			wp_send_json_error( array( 'message' => $theme->errors()->get_error_message() ) );
		}

		switch_theme( $stylesheet );

		wp_send_json_success(
			array(
				/* translators: %s: theme name */
				'message' => sprintf( __( '%s is now the active theme.', 'wp-dashboard-management' ), $theme->get( 'Name' ) ),
			)
		);
	}

	/**
	 * Toggle debug mode (WP_DEBUG + WP_DEBUG_LOG together) in wp-config.php.
	 */
	public static function toggle_debug() {
		self::guard( 'debug_toggle' );

		$on     = isset( $_POST['value'] ) && '1' === $_POST['value'];
		$result = WPDM_Config::set_debug_mode( $on );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => $on
					? __( 'Debug mode enabled — PHP errors are now written to the debug log.', 'wp-dashboard-management' )
					: __( 'Debug mode disabled.', 'wp-dashboard-management' ),
			)
		);
	}

	/**
	 * Save email-report settings and reschedule the cron event.
	 */
	public static function save_report_settings() {
		self::guard( 'email_reports' );

		$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$frequency = isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : 'disabled';

		if ( 'disabled' !== $frequency && ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'wp-dashboard-management' ) ) );
		}

		WPDM_Settings::save_report_settings( $email, $frequency );
		WPDM_Email_Report::reschedule();

		$next = WPDM_Email_Report::next_run();
		wp_send_json_success(
			array(
				'message' => $next
					/* translators: %s: date/time of next report */
					? sprintf( __( 'Report settings saved. Next report: %s.', 'wp-dashboard-management' ), wp_date( 'j F Y, H:i', $next ) )
					: __( 'Report settings saved. Scheduled reports are disabled.', 'wp-dashboard-management' ),
			)
		);
	}

	/**
	 * Send a report immediately (to the saved address).
	 */
	public static function send_report_now() {
		self::guard( 'email_reports' );

		$result = WPDM_Email_Report::send();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$report = WPDM_Settings::report_settings();
		/* translators: %s: email address */
		wp_send_json_success( array( 'message' => sprintf( __( 'Report sent to %s.', 'wp-dashboard-management' ), $report['email'] ) ) );
	}

	/**
	 * Create a database snapshot.
	 */
	public static function create_backup() {
		self::guard( 'snapshots' );
		$result = WPDM_DB_Backup::create( 'manual' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		/* translators: %s: snapshot filename */
		wp_send_json_success( array( 'message' => sprintf( __( 'Snapshot created: %s', 'wp-dashboard-management' ), $result ) ) );
	}

	/**
	 * Restore a database snapshot.
	 */
	public static function restore_backup() {
		self::guard( 'snapshots' );
		$file   = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';
		$result = WPDM_DB_Backup::restore( $file );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Database restored from snapshot.', 'wp-dashboard-management' ) ) );
	}

	/**
	 * Delete a database snapshot.
	 */
	public static function delete_backup() {
		self::guard( 'snapshots' );
		$file   = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';
		$result = WPDM_DB_Backup::delete( $file );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Snapshot deleted.', 'wp-dashboard-management' ) ) );
	}

	/**
	 * Install a specific WordPress core version (upgrade or rollback).
	 */
	public static function install_core() {
		self::guard( 'core_switch' );
		if ( ! current_user_can( 'update_core' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to update WordPress core.', 'wp-dashboard-management' ) ), 403 );
		}

		$version = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '';
		if ( ! preg_match( '/^\d+\.\d+(\.\d+)?$/', $version ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid version format.', 'wp-dashboard-management' ) ) );
		}

		$result = WPDM_Core_Manager::install( $version );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				/* translators: %s: WordPress version */
				'message' => sprintf( __( 'WordPress %s installed successfully.', 'wp-dashboard-management' ), $version ),
			)
		);
	}

	/**
	 * Install the Beqlic Activity Log plugin from GitHub and activate it.
	 */
	public static function install_beqlic() {
		self::guard( 'activity' );
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to install plugins.', 'wp-dashboard-management' ) ), 403 );
		}
		$result = WPDM_Activity_Log::install_from_github();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Beqlic Activity Log installed and activated.', 'wp-dashboard-management' ) ) );
	}

	/**
	 * Activate the Beqlic Activity Log plugin.
	 */
	public static function activate_beqlic() {
		self::guard( 'activity' );
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to activate plugins.', 'wp-dashboard-management' ) ), 403 );
		}
		$result = WPDM_Activity_Log::activate();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Beqlic Activity Log activated.', 'wp-dashboard-management' ) ) );
	}
}
