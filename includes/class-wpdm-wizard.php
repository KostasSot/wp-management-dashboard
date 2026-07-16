<?php
/**
 * Setup wizard: pick which dashboard modules are enabled.
 *
 * Runs automatically after activation and can be re-run any time from the
 * "Setup" submenu.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Wizard {

	const PAGE_SLUG = 'wpdm-setup';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
		add_action( 'admin_post_wpdm_save_wizard', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * "Setup" submenu under the Management menu.
	 */
	public static function register_page() {
		add_submenu_page(
			WPDM_Admin::MENU_SLUG,
			__( 'Setup Wizard', 'wp-dashboard-management' ),
			__( 'Setup', 'wp-dashboard-management' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * After activation, send the admin straight to the wizard (once).
	 */
	public static function maybe_redirect() {
		if ( ! get_transient( 'wpdm_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'wpdm_activation_redirect' );

		if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() || is_network_admin() ) {
			return;
		}
		if ( WPDM_Settings::setup_complete() ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Wizard assets, on the wizard page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'management_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wpdm-wizard',
			WPDM_PLUGIN_URL . 'assets/css/wizard.css',
			array(),
			(string) filemtime( WPDM_PLUGIN_DIR . 'assets/css/wizard.css' )
		);

		wp_enqueue_script(
			'wpdm-wizard',
			WPDM_PLUGIN_URL . 'assets/js/wizard.js',
			array(),
			(string) filemtime( WPDM_PLUGIN_DIR . 'assets/js/wizard.js' ),
			true
		);
	}

	/**
	 * Render the wizard.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-dashboard-management' ) );
		}

		$features = WPDM_Settings::features();
		$settings = WPDM_Settings::get();

		require WPDM_PLUGIN_DIR . 'includes/views/wizard.php';
	}

	/**
	 * Persist the wizard selection.
	 */
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wp-dashboard-management' ) );
		}
		check_admin_referer( 'wpdm_save_wizard' );

		$posted  = isset( $_POST['features'] ) && is_array( $_POST['features'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['features'] ) )
			: array();
		$allowed = array_keys( WPDM_Settings::features() );

		WPDM_Settings::save_features( array_intersect( $posted, $allowed ) );

		wp_safe_redirect( admin_url( 'admin.php?page=' . WPDM_Admin::MENU_SLUG . '&wpdm-setup=done' ) );
		exit;
	}
}
