<?php
/**
 * Safe wp-config.php editing for the debug constants.
 *
 * Only whitelisted constants can be changed; the edit rewrites a
 * single define() line and a backup copy is kept next to the file.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Config {

	/**
	 * Constants this class is allowed to touch.
	 *
	 * @var string[]
	 */
	const ALLOWED = array( 'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY' );

	/**
	 * Locate wp-config.php (it may live one level above ABSPATH).
	 *
	 * @return string Path, or empty string if not found.
	 */
	public static function path() {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}
		$parent = dirname( ABSPATH ) . '/wp-config.php';
		if ( file_exists( $parent ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return $parent;
		}
		return '';
	}

	/**
	 * Option storing the chosen debug log path, so it stays stable across toggles.
	 */
	const LOG_PATH_OPTION = 'wpdm_debug_log_path';

	/**
	 * Switch debug mode as a whole.
	 *
	 * Enabled: WP_DEBUG on, WP_DEBUG_LOG pointed at a private file (see
	 * debug_log_path()), WP_DEBUG_DISPLAY off (errors are logged for the
	 * dashboard, never printed on the site).
	 * Disabled: all three off.
	 *
	 * @param bool $on Whether debug mode should be on.
	 * @return true|WP_Error
	 */
	public static function set_debug_mode( $on ) {
		$log_path = $on ? self::debug_log_path() : false;

		$flags = array(
			'WP_DEBUG'         => (bool) $on,
			'WP_DEBUG_LOG'     => $log_path,
			'WP_DEBUG_DISPLAY' => false,
		);

		foreach ( $flags as $name => $value ) {
			$result = self::set_constant( $name, $value );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( $on ) {
			self::move_public_debug_log( $log_path );
		}

		return true;
	}

	/**
	 * Default WP debug.log location, publicly reachable at /wp-content/debug.log.
	 *
	 * @return string
	 */
	public static function public_debug_log_path() {
		return WP_CONTENT_DIR . '/debug.log';
	}

	/**
	 * Pick a debug log file that has no guessable public URL.
	 *
	 * Prefers a folder above ABSPATH that is outside the document root; falls
	 * back to the protected uploads log folder. The file name is random in both
	 * cases because .htaccess does nothing on Nginx.
	 *
	 * @return string
	 */
	public static function debug_log_path() {
		$saved = get_option( self::LOG_PATH_OPTION );
		if ( is_string( $saved ) && '' !== $saved && is_dir( dirname( $saved ) ) && is_writable( dirname( $saved ) ) ) {
			return $saved;
		}

		$dir = self::private_log_dir();
		if ( '' === $dir ) {
			$dir = WPDM_Error_Logger::log_dir();
		}

		$path = $dir . '/debug-' . wp_generate_password( 20, false ) . '.log';
		update_option( self::LOG_PATH_OPTION, $path, false );

		return $path;
	}

	/**
	 * A writable log folder above ABSPATH, outside the web server's document root.
	 *
	 * @return string Directory, or empty string if none is usable.
	 */
	private static function private_log_dir() {
		$dir = dirname( ABSPATH ) . '/wpdm-logs';

		// WordPress in a subfolder: the parent of ABSPATH is still served.
		$doc_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$parent   = realpath( dirname( ABSPATH ) );
		if ( ! $doc_root || ! $parent || 0 === strpos( trailingslashit( $parent ), trailingslashit( $doc_root ) ) ) {
			return '';
		}

		// open_basedir or ownership may forbid this; the caller falls back.
		if ( ! @wp_mkdir_p( $dir ) || ! is_writable( $dir ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return '';
		}

		WPDM_Error_Logger::protect_dir( $dir );

		return $dir;
	}

	/**
	 * Move an existing wp-content/debug.log to the private location so it is no
	 * longer downloadable. Left in place if the target already has content.
	 *
	 * @param string $target New log path.
	 */
	private static function move_public_debug_log( $target ) {
		$public = self::public_debug_log_path();
		if ( $public === $target || ! is_file( $public ) || file_exists( $target ) ) {
			return;
		}

		if ( @rename( $public, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			// This request still logs to the old path; repoint it so it is not recreated.
			ini_set( 'error_log', $target ); // phpcs:ignore WordPress.PHP.IniSet
		}
	}

	/**
	 * Set a whitelisted constant in wp-config.php.
	 *
	 * Values are booleans; WP_DEBUG_LOG also accepts a file path.
	 *
	 * @param string      $name  Constant name (must be in ALLOWED).
	 * @param bool|string $value New value.
	 * @return true|WP_Error
	 */
	public static function set_constant( $name, $value ) {
		if ( ! in_array( $name, self::ALLOWED, true ) ) {
			return new WP_Error( 'wpdm_const_not_allowed', __( 'This constant cannot be changed from the dashboard.', 'wp-dashboard-management' ) );
		}
		if ( ! is_bool( $value ) && ! ( 'WP_DEBUG_LOG' === $name && is_string( $value ) && '' !== $value ) ) {
			return new WP_Error( 'wpdm_const_invalid', __( 'Invalid value for this constant.', 'wp-dashboard-management' ) );
		}

		$path = self::path();
		if ( '' === $path ) {
			return new WP_Error( 'wpdm_config_missing', __( 'wp-config.php could not be located.', 'wp-dashboard-management' ) );
		}
		if ( ! is_writable( $path ) ) {
			return new WP_Error( 'wpdm_config_readonly', __( 'wp-config.php is not writable.', 'wp-dashboard-management' ) );
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $contents ) {
			return new WP_Error( 'wpdm_config_unreadable', __( 'wp-config.php could not be read.', 'wp-dashboard-management' ) );
		}

		$literal = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : var_export( $value, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$line    = "define( '{$name}', {$literal} );";
		$pattern = '/define\(\s*([\'"])' . preg_quote( $name, '/' ) . '\1\s*,\s*.+?\s*\)\s*;/';
		// Callbacks keep "$1" or "\" in a path from being read as backreferences.
		$replace = static function () use ( $line ) {
			return $line;
		};

		if ( preg_match( $pattern, $contents ) ) {
			$updated = preg_replace_callback( $pattern, $replace, $contents, 1 );
		} else {
			// Constant not defined yet: insert it before the stop-editing marker,
			// or after the opening tag as a fallback.
			$marker = '/* That\'s all, stop editing!';
			$pos    = strpos( $contents, $marker );
			if ( false !== $pos ) {
				$updated = substr_replace( $contents, $line . "\n\n", $pos, 0 );
			} else {
				$updated = preg_replace_callback(
					'/^<\?php\s*/',
					static function () use ( $line ) {
						return "<?php\n" . $line . "\n";
					},
					$contents,
					1
				);
			}
		}

		if ( null === $updated || '' === $updated ) {
			return new WP_Error( 'wpdm_config_edit_failed', __( 'The configuration edit failed.', 'wp-dashboard-management' ) );
		}

		// Keep a backup of the pre-edit file.
		@copy( $path, $path . '.wpdm-bak' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions

		if ( false === file_put_contents( $path, $updated ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'wpdm_config_write_failed', __( 'wp-config.php could not be written.', 'wp-dashboard-management' ) );
		}

		// Evict the stale compiled copy so the very next request sees the
		// change (opcache.revalidate_freq would otherwise delay it).
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $path, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		return true;
	}
}
