<?php
/**
 * Must-use drop-in that starts error logging before regular plugins load.
 *
 * WPDM_Error_Logger is otherwise registered on plugins_loaded, so a fatal
 * error inside another plugin's main file (a broken update, a PHP upgrade)
 * would never be logged or emailed. Mu-plugins load first; the drop-in
 * loads the logger classes from this plugin and registers the handlers.
 *
 * The drop-in is generated (it embeds this plugin's path), written
 * atomically, kept in sync on admin_init, and removed on deactivation.
 * If mu-plugins is not writable, logging simply starts on plugins_loaded
 * as before.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Early_Logger {

	const FILENAME = '0-wpdm-error-logger.php';

	/**
	 * Identifies the file as ours, so a foreign file is never overwritten or deleted.
	 */
	const MARKER = 'WPDM early error logger';

	/**
	 * Class files the drop-in loads, in dependency order.
	 */
	const FILES = array(
		'class-wpdm-settings.php',
		'class-wpdm-admin.php',
		'class-wpdm-fatal-alert.php',
		'class-wpdm-error-logger.php',
	);

	/**
	 * Keep the drop-in present and current (covers sites updated without
	 * re-activation, and moved installs).
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'install' ) );
	}

	/**
	 * Full path of the drop-in.
	 *
	 * @return string
	 */
	public static function path() {
		return WPMU_PLUGIN_DIR . '/' . self::FILENAME;
	}

	/**
	 * Whether the drop-in is in place and matches the current code.
	 *
	 * @return bool
	 */
	public static function is_current() {
		$path = self::path();
		return is_file( $path ) && file_get_contents( $path ) === self::code(); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Write the drop-in unless it is already current or the file is not ours.
	 *
	 * @return bool Whether the drop-in is in place and current.
	 */
	public static function install() {
		$path = self::path();
		$code = self::code();

		if ( is_file( $path ) ) {
			$current = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( $current === $code ) {
				return true;
			}
			if ( false === strpos( $current, self::MARKER ) ) {
				return false;
			}
		}

		if ( ! wp_mkdir_p( WPMU_PLUGIN_DIR ) ) {
			return false;
		}

		// Write then rename: a half-written PHP file in mu-plugins would take
		// the whole site down. ".tmp" is not loaded (only *.php files are).
		$tmp = $path . '.tmp';
		if ( false === @file_put_contents( $tmp, $code, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
			return false;
		}
		if ( ! @rename( $tmp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
			@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
			return false;
		}

		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $path, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		return true;
	}

	/**
	 * Delete the drop-in if it is ours.
	 */
	public static function remove() {
		$path = self::path();
		if ( is_file( $path ) && false !== strpos( (string) file_get_contents( $path ), self::MARKER ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			@unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		}
	}

	/**
	 * Drop-in source.
	 *
	 * It does nothing unless this plugin is active and every class file is
	 * readable, so a deleted, renamed, or half-updated plugin can never
	 * break the site through it.
	 *
	 * @return string
	 */
	private static function code() {
		$includes = var_export( WPDM_PLUGIN_DIR . 'includes/', true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$basename = var_export( plugin_basename( WPDM_PLUGIN_FILE ), true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$files    = "array( '" . implode( "', '", self::FILES ) . "' )";
		$marker   = self::MARKER;

		return <<<PHP
<?php
/**
 * Plugin Name: WP Dashboard Management — Early Error Logger
 * Description: Starts WP Dashboard Management's PHP error logging before regular plugins load, so their fatal errors are logged and emailed. Managed by that plugin and removed when it is deactivated.
 *
 * {$marker}. Generated file, do not edit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

call_user_func(
	static function () {
		\$basename = {$basename};
		\$includes = {$includes};
		\$files    = {$files};

		\$active = in_array( \$basename, (array) get_option( 'active_plugins', array() ), true )
			|| ( is_multisite() && array_key_exists( \$basename, (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		if ( ! \$active ) {
			return;
		}

		foreach ( \$files as \$file ) {
			if ( ! is_readable( \$includes . \$file ) ) {
				return;
			}
		}
		foreach ( \$files as \$file ) {
			require_once \$includes . \$file;
		}

		WPDM_Error_Logger::init();
	}
);

PHP;
	}
}
