<?php
/**
 * Admin menu, assets and dashboard rendering.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Admin {

	const MENU_SLUG = 'wp-dashboard-management';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Add the top-level admin page.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'WP Dashboard Management', 'wp-dashboard-management' ),
			__( 'Management', 'wp-dashboard-management' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-dashboard',
			3
		);

		// Rename the auto-generated first submenu item to "Dashboard".
		add_submenu_page(
			self::MENU_SLUG,
			__( 'WP Dashboard Management', 'wp-dashboard-management' ),
			__( 'Dashboard', 'wp-dashboard-management' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Load CSS/JS on our page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		// File mtime as the version so browsers never serve stale assets.
		wp_enqueue_style(
			'wpdm-admin',
			WPDM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			(string) filemtime( WPDM_PLUGIN_DIR . 'assets/css/admin.css' )
		);

		wp_enqueue_script(
			'wpdm-admin',
			WPDM_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			(string) filemtime( WPDM_PLUGIN_DIR . 'assets/js/admin.js' ),
			true
		);

		$stats = null;
		if ( WPDM_Settings::enabled( 'activity' ) ) {
			$status = WPDM_Activity_Log::status();
			$stats  = $status['active'] ? WPDM_Activity_Log::stats( 14 ) : null;
		}

		wp_localize_script(
			'wpdm-admin',
			'wpdmData',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( WPDM_Ajax::NONCE_ACTION ),
				'updatesNonce' => wp_create_nonce( 'updates' ),
				'wpVersion'    => get_bloginfo( 'version' ),
				'activity'     => $stats,
				'i18n'         => array(
					'confirmClear'  => __( 'Empty the PHP log file? This cannot be undone.', 'wp-dashboard-management' ),
					/* translators: %s: theme name */
					'confirmSwitch' => __( 'Switch the active theme to "%s"? The current theme will be deactivated.', 'wp-dashboard-management' ),
					/* translators: %s: plugin name */
					'confirmDeactivate' => __( 'Deactivate "%s"?', 'wp-dashboard-management' ),
					/* translators: %s: WordPress version */
					'confirmCore'        => __( 'Install WordPress %s over the current installation? Core files will be replaced. Make sure you have a backup.', 'wp-dashboard-management' ),
					'installingCore'     => __( 'Installing…', 'wp-dashboard-management' ),
					/* translators: %s: snapshot filename */
					'confirmRestore' => __( 'Restore the database from "%s"? All tables in the snapshot will be replaced with their saved state. Changes made since then are lost.', 'wp-dashboard-management' ),
					/* translators: %s: WordPress version */
					'restoreVersionMismatch' => __( 'Note: this snapshot was taken on WordPress %s, which is not the version currently running. Consider switching to that version as well.', 'wp-dashboard-management' ),
					/* translators: %s: snapshot filename */
					'confirmDeleteBackup' => __( 'Delete snapshot "%s"? This cannot be undone.', 'wp-dashboard-management' ),
					'installingCoreLong' => __( 'Downloading and installing WordPress — this can take a minute, do not close the page.', 'wp-dashboard-management' ),
					'newAdminLogin' => __( 'New username for the "admin" account (you stay logged in, password unchanged):', 'wp-dashboard-management' ),
					'confirmXmlrpc' => __( 'Disable XML-RPC? Jetpack, the WordPress mobile app and remote publishing tools that rely on it will stop working.', 'wp-dashboard-management' ),
					'confirmIndexing' => __( 'Allow search engines to index this site? Do not do this on staging or development sites.', 'wp-dashboard-management' ),
					'confirmSalts'    => __( 'Generate new secret keys and salts in wp-config.php? Every user, including you, is logged out immediately.', 'wp-dashboard-management' ),
					/* translators: 1: plugin version, 2: plugin name */
					'confirmRollback' => __( 'Install version %1$s of "%2$s" over the current one? Plugin files are replaced; a database snapshot is taken first when snapshots are enabled.', 'wp-dashboard-management' ),
					'install'         => __( 'Install', 'wp-dashboard-management' ),
					'current'         => __( 'current', 'wp-dashboard-management' ),
					'scanning'        => __( 'Scanning — one request per plugin and theme, this can take a minute.', 'wp-dashboard-management' ),
					'working'      => __( 'Working…', 'wp-dashboard-management' ),
					'updating'     => __( 'Updating…', 'wp-dashboard-management' ),
					'updated'      => __( 'Updated to version %s.', 'wp-dashboard-management' ),
					'error'        => __( 'Request failed.', 'wp-dashboard-management' ),
				),
			)
		);
	}

	/**
	 * Render the dashboard page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-dashboard-management' ) );
		}

		$features = WPDM_Settings::get()['features'];

		$log_sources = WPDM_PHP_Logs::sources();
		$log_source  = isset( $_GET['log_source'] ) ? sanitize_key( wp_unslash( $_GET['log_source'] ) ) : 'wpdm'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! array_key_exists( $log_source, $log_sources ) ) {
			$log_source = 'wpdm';
		}

		$system          = WPDM_System_Info::get();
		$plugins         = $features['updates'] ? WPDM_Plugins_Status::get_all() : array();
		$plugin_summary  = $features['updates'] ? WPDM_Plugins_Status::summary() : array();
		$themes          = $features['updates'] ? WPDM_Themes_Status::get_all() : array();
		$theme_summary   = $features['updates'] ? WPDM_Themes_Status::summary() : array();
		$logs            = $features['php_logs'] ? WPDM_PHP_Logs::get_entries( 100, $log_source ) : array();
		$activity_status = $features['activity'] ? WPDM_Activity_Log::status() : array( 'installed' => false, 'active' => false, 'basename' => '' );
		$activity_stats  = $features['activity'] && $activity_status['active'] ? WPDM_Activity_Log::stats( 14 ) : null;
		$core_versions   = $features['core_switch'] && current_user_can( 'update_core' ) ? WPDM_Core_Manager::available_versions() : array();
		$snapshots       = $features['snapshots'] ? WPDM_DB_Backup::get_list() : array();
		$security        = $features['security'] ? WPDM_Security_Check::summary() : array();
		$report          = $features['email_reports'] ? WPDM_Settings::report_settings() : array();
		$report_next     = $features['email_reports'] ? WPDM_Email_Report::next_run() : 0;
		$health_refresh  = isset( $_GET['wpdm-health'] ) && 'refresh' === $_GET['wpdm-health']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$site_health     = $features['site_health'] ? WPDM_Site_Health::results( $health_refresh ) : array();
		$vulnerabilities = $features['vulnerabilities'] ? WPDM_Vulnerabilities::results() : null;
		$abandoned       = $features['abandoned'] ? WPDM_Abandoned_Plugins::results() : null;

		require WPDM_PLUGIN_DIR . 'includes/views/dashboard.php';
	}
}
