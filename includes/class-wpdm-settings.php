<?php
/**
 * Plugin settings and feature flags.
 *
 * Which dashboard modules are active is chosen in the setup wizard and
 * stored in one option. Every module checks WPDM_Settings::enabled() both
 * when rendering and in its AJAX handlers.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Settings {

	const OPTION = 'wpdm_settings';

	/**
	 * All selectable features.
	 *
	 * @return array key => array{label:string,description:string,group:string}
	 */
	public static function features() {
		return array(
			'updates'      => array(
				'label'       => __( 'Plugins & Themes Overview', 'wp-dashboard-management' ),
				'description' => __( 'The plugins and themes tables with versions, statuses, and available updates.', 'wp-dashboard-management' ),
				'group'       => 'monitoring',
			),
			'manage'       => array(
				'label'       => __( 'Plugin & Theme Actions', 'wp-dashboard-management' ),
				'description' => __( 'The action buttons: one-click updates, activate/deactivate plugins, and switch themes. Without this the overview is read-only.', 'wp-dashboard-management' ),
				'group'       => 'monitoring',
			),
			'export'       => array(
				'label'       => __( 'XLSX Update Report', 'wp-dashboard-management' ),
				'description' => __( 'The "Export updates (XLSX)" report of pending plugin and theme updates. Works on its own, even with the overview disabled.', 'wp-dashboard-management' ),
				'group'       => 'monitoring',
			),
			'php_logs'     => array(
				'label'       => __( 'PHP Error Logs', 'wp-dashboard-management' ),
				'description' => __( 'The plugin\'s own PHP error log plus the WP debug.log viewer with severity filters.', 'wp-dashboard-management' ),
				'group'       => 'monitoring',
			),
			'activity'     => array(
				'label'       => __( 'Activity Log (Beqlic)', 'wp-dashboard-management' ),
				'description' => __( 'Charts and latest events from the Beqlic Activity Log plugin, with one-click install from GitHub.', 'wp-dashboard-management' ),
				'group'       => 'monitoring',
			),
			'security'     => array(
				'label'       => __( 'Security Health Check', 'wp-dashboard-management' ),
				'description' => __( 'Hardening checklist with one-click fixes: HTTPS, file editing, admin username, insecure core versions, and more.', 'wp-dashboard-management' ),
				'group'       => 'monitoring',
			),
			'site_health'  => array(
				'label'       => __( 'Site Health Summary', 'wp-dashboard-management' ),
				'description' => __( 'WordPress core\'s own Site Health tests summarized on the dashboard, critical issues first.', 'wp-dashboard-management' ),
				'group'       => 'monitoring',
			),
			'email_reports' => array(
				'label'       => __( 'Email Update Reports', 'wp-dashboard-management' ),
				'description' => __( 'Scheduled email report of the core version and pending plugin/theme updates, sent daily, weekly, or monthly.', 'wp-dashboard-management' ),
				'group'       => 'monitoring',
			),
			'debug_toggle' => array(
				'label'       => __( 'Debug Mode Toggle', 'wp-dashboard-management' ),
				'description' => __( 'Switch WP_DEBUG logging on/off from the Environment panel (edits wp-config.php).', 'wp-dashboard-management' ),
				'group'       => 'monitoring',
			),
			'core_switch'  => array(
				'label'       => __( 'WordPress Version Switcher', 'wp-dashboard-management' ),
				'description' => __( 'Install any WordPress 6.0+ release for upgrades and rollbacks. Recommended for development sites only.', 'wp-dashboard-management' ),
				'group'       => 'power',
			),
			'snapshots'    => array(
				'label'       => __( 'Database Snapshots', 'wp-dashboard-management' ),
				'description' => __( 'Create, restore, and delete full database snapshots; automatic snapshot before version switches.', 'wp-dashboard-management' ),
				'group'       => 'power',
			),
		);
	}

	/**
	 * Saved settings merged with defaults (all features on, setup pending).
	 *
	 * @return array{features:array,setup_complete:bool}
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$defaults = array(
			'features'       => array_fill_keys( array_keys( self::features() ), true ),
			'setup_complete' => false,
			'report'         => array(
				'email'     => get_option( 'admin_email' ),
				'frequency' => 'disabled',
			),
		);

		$settings             = wp_parse_args( $saved, $defaults );
		$settings['features'] = wp_parse_args(
			is_array( $settings['features'] ) ? $settings['features'] : array(),
			$defaults['features']
		);
		$settings['report']   = wp_parse_args(
			is_array( $settings['report'] ) ? $settings['report'] : array(),
			$defaults['report']
		);

		return $settings;
	}

	/**
	 * Report settings (email + frequency).
	 *
	 * @return array{email:string,frequency:string}
	 */
	public static function report_settings() {
		$settings = self::get();
		return $settings['report'];
	}

	/**
	 * Save the report settings, preserving everything else.
	 *
	 * @param string $email     Recipient address.
	 * @param string $frequency One of WPDM_Email_Report::frequencies() keys.
	 */
	public static function save_report_settings( $email, $frequency ) {
		$settings           = self::get();
		$settings['report'] = array(
			'email'     => sanitize_email( $email ),
			'frequency' => array_key_exists( $frequency, WPDM_Email_Report::frequencies() ) ? $frequency : 'disabled',
		);
		update_option( self::OPTION, $settings );
	}

	/**
	 * Whether a feature is enabled.
	 *
	 * @param string $key Feature key.
	 * @return bool
	 */
	public static function enabled( $key ) {
		$settings = self::get();
		return ! empty( $settings['features'][ $key ] );
	}

	/**
	 * Whether the wizard has been completed at least once.
	 *
	 * @return bool
	 */
	public static function setup_complete() {
		$settings = self::get();
		return ! empty( $settings['setup_complete'] );
	}

	/**
	 * Persist the wizard's feature selection.
	 *
	 * @param string[] $enabled_keys Feature keys to enable; everything else is disabled.
	 */
	public static function save_features( array $enabled_keys ) {
		$features = array();
		foreach ( array_keys( self::features() ) as $key ) {
			$features[ $key ] = in_array( $key, $enabled_keys, true );
		}

		$settings                   = self::get();
		$settings['features']       = $features;
		$settings['setup_complete'] = true;

		update_option( self::OPTION, $settings );
	}
}
