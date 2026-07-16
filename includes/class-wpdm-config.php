<?php
/**
 * Safe wp-config.php editing for the debug constants.
 *
 * Only whitelisted boolean constants can be changed; the edit rewrites a
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
	 * Switch debug mode as a whole.
	 *
	 * Enabled: WP_DEBUG + WP_DEBUG_LOG on, WP_DEBUG_DISPLAY off (errors are
	 * logged for the dashboard, never printed on the site).
	 * Disabled: all three off.
	 *
	 * @param bool $on Whether debug mode should be on.
	 * @return true|WP_Error
	 */
	public static function set_debug_mode( $on ) {
		$flags = array(
			'WP_DEBUG'         => (bool) $on,
			'WP_DEBUG_LOG'     => (bool) $on,
			'WP_DEBUG_DISPLAY' => false,
		);

		foreach ( $flags as $name => $value ) {
			$result = self::set_bool_constant( $name, $value );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Set a whitelisted boolean constant in wp-config.php.
	 *
	 * @param string $name  Constant name (must be in ALLOWED).
	 * @param bool   $value New value.
	 * @return true|WP_Error
	 */
	public static function set_bool_constant( $name, $value ) {
		if ( ! in_array( $name, self::ALLOWED, true ) ) {
			return new WP_Error( 'wpdm_const_not_allowed', __( 'This constant cannot be changed from the dashboard.', 'wp-dashboard-management' ) );
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

		$literal = $value ? 'true' : 'false';
		$line    = "define( '{$name}', {$literal} );";
		$pattern = '/define\(\s*([\'"])' . preg_quote( $name, '/' ) . '\1\s*,\s*.+?\s*\)\s*;/';

		if ( preg_match( $pattern, $contents ) ) {
			$updated = preg_replace( $pattern, $line, $contents, 1 );
		} else {
			// Constant not defined yet: insert it before the stop-editing marker,
			// or after the opening tag as a fallback.
			$marker = '/* That\'s all, stop editing!';
			$pos    = strpos( $contents, $marker );
			if ( false !== $pos ) {
				$updated = substr_replace( $contents, $line . "\n\n", $pos, 0 );
			} else {
				$updated = preg_replace( '/^<\?php\s*/', "<?php\n" . $line . "\n", $contents, 1 );
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
