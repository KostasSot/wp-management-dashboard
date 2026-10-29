<?php
/**
 * Plugin-owned PHP error logger.
 *
 * Captures PHP warnings/notices/deprecations via set_error_handler() and
 * fatals via a shutdown handler, and appends them to the plugin's own log
 * file — independent of WP_DEBUG_LOG or the server's error_log setting.
 * Lines use the same format as debug.log so WPDM_PHP_Logs can parse both.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Error_Logger {

	/**
	 * Rotate when the log grows beyond this size.
	 */
	const MAX_FILE_BYTES = 5242880; // 5 MB.

	/**
	 * Register the error and shutdown handlers.
	 */
	public static function init() {
		set_error_handler( array( __CLASS__, 'handle_error' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		register_shutdown_function( array( __CLASS__, 'handle_shutdown' ) );
	}

	/**
	 * Directory holding the log, protected from direct web access.
	 *
	 * @return string
	 */
	public static function log_dir() {
		$uploads = wp_upload_dir( null, false );
		$dir     = trailingslashit( $uploads['basedir'] ) . 'wpdm-logs';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			self::protect_dir( $dir );
		}

		return $dir;
	}

	/**
	 * Block direct web access to a directory (Apache; Nginx ignores .htaccess).
	 *
	 * @param string $dir Existing directory.
	 */
	public static function protect_dir( $dir ) {
		@file_put_contents( $dir . '/.htaccess', "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
	}

	/**
	 * Full path to the plugin's PHP error log file.
	 *
	 * @return string
	 */
	public static function log_file() {
		return self::log_dir() . '/php-errors.log';
	}

	/**
	 * Error handler: record the error, then let PHP handle it normally.
	 *
	 * @param int    $errno   Error level.
	 * @param string $message Error message.
	 * @param string $file    File the error occurred in.
	 * @param int    $line    Line number.
	 * @return bool Always false so PHP's own handling still runs.
	 */
	public static function handle_error( $errno, $message, $file = '', $line = 0 ) {
		// Respect the current error_reporting level (including @-suppression).
		if ( ! ( error_reporting() & $errno ) ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return false;
		}

		self::write( self::level_label( $errno ), $message, $file, $line );

		return false;
	}

	/**
	 * Shutdown handler: catch fatal errors the error handler never sees.
	 */
	public static function handle_shutdown() {
		$error  = error_get_last();
		$fatals = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );

		if ( $error && in_array( $error['type'], $fatals, true ) ) {
			self::write( self::level_label( $error['type'] ), $error['message'], $error['file'], $error['line'] );
		}
	}

	/**
	 * Append one entry to the log file (debug.log-compatible format).
	 *
	 * @param string $label   Error label, e.g. "Warning".
	 * @param string $message Error message.
	 * @param string $file    Source file.
	 * @param int    $line    Source line.
	 */
	private static function write( $label, $message, $file, $line ) {
		$path = self::log_file();

		self::maybe_rotate( $path );

		$entry = sprintf(
			'[%s UTC] PHP %s:  %s in %s on line %d' . PHP_EOL,
			gmdate( 'd-M-Y H:i:s' ),
			$label,
			$message,
			$file,
			$line
		);

		@file_put_contents( $path, $entry, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
	}

	/**
	 * Keep the file from growing unbounded: rotate to .1 when too large.
	 *
	 * @param string $path Log file path.
	 */
	private static function maybe_rotate( $path ) {
		if ( is_file( $path ) && filesize( $path ) > self::MAX_FILE_BYTES ) {
			@rename( $path, $path . '.1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		}
	}

	/**
	 * Map a PHP error constant to the label used in log lines.
	 *
	 * @param int $errno Error level constant.
	 * @return string
	 */
	private static function level_label( $errno ) {
		switch ( $errno ) {
			case E_ERROR:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				return 'Fatal error';
			case E_PARSE:
				return 'Parse error';
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				return 'Warning';
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				return 'Deprecated';
			case E_NOTICE:
			case E_USER_NOTICE:
			default:
				return 'Notice';
		}
	}
}
