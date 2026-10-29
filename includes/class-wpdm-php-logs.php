<?php
/**
 * PHP error log reader (WP debug.log and configured error_log).
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_PHP_Logs {

	/**
	 * Maximum bytes read from the end of a log file.
	 */
	const MAX_READ_BYTES = 262144; // 256 KB.

	/**
	 * Available log sources.
	 *
	 * 'wpdm'  — the plugin's own log file (written by WPDM_Error_Logger).
	 * 'debug' — WordPress/server debug.log.
	 *
	 * @return array id => array{label:string,path:string}
	 */
	public static function sources() {
		return array(
			'wpdm'  => array(
				'label' => __( 'Plugin log', 'wp-dashboard-management' ),
				'path'  => WPDM_Error_Logger::log_file(),
			),
			'debug' => array(
				'label' => __( 'WP debug.log', 'wp-dashboard-management' ),
				'path'  => self::log_path(),
			),
		);
	}

	/**
	 * Resolve a source id to its file path.
	 *
	 * @param string $source Source id; unknown ids fall back to 'wpdm'.
	 * @return string
	 */
	public static function source_path( $source ) {
		$sources = self::sources();
		$source  = isset( $sources[ $source ] ) ? $source : 'wpdm';
		return $sources[ $source ]['path'];
	}

	/**
	 * Resolve the WP/server debug log file path.
	 *
	 * Prefers WP_DEBUG_LOG (bool → wp-content/debug.log, string → custom path),
	 * falls back to PHP's error_log ini setting.
	 *
	 * @return string Path, or empty string if none configured.
	 */
	public static function log_path() {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			return is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
		}

		$ini_log = ini_get( 'error_log' );
		if ( $ini_log && 'syslog' !== $ini_log && 0 !== strpos( $ini_log, '/dev/' ) ) {
			return $ini_log;
		}

		// Last resort: the conventional location, in case logging was enabled previously.
		return WP_CONTENT_DIR . '/debug.log';
	}

	/**
	 * Tail the log file and parse entries.
	 *
	 * @param int    $max_entries Maximum parsed entries returned (newest first).
	 * @param string $source      Log source id (see sources()).
	 * @return array{path:string,exists:bool,size:int,truncated:bool,entries:array,counts:array}
	 */
	public static function get_entries( $max_entries = 100, $source = 'wpdm' ) {
		$path   = self::source_path( $source );
		$result = array(
			'path'      => $path,
			'exists'    => false,
			'size'      => 0,
			'truncated' => false,
			'entries'   => array(),
			'counts'    => array(
				'fatal'      => 0,
				'warning'    => 0,
				'notice'     => 0,
				'deprecated' => 0,
				'other'      => 0,
			),
		);

		if ( ! $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return $result;
		}

		$result['exists'] = true;
		$size             = (int) filesize( $path );
		$result['size']   = $size;

		if ( 0 === $size ) {
			return $result;
		}

		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $handle ) {
			return $result;
		}

		$read = min( $size, self::MAX_READ_BYTES );
		fseek( $handle, -$read, SEEK_END );
		$raw = fread( $handle, $read ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$result['truncated'] = $size > self::MAX_READ_BYTES;

		$entries = self::parse( $raw );

		// Count severities across everything we read, then trim for display.
		foreach ( $entries as $entry ) {
			$key = isset( $result['counts'][ $entry['level'] ] ) ? $entry['level'] : 'other';
			$result['counts'][ $key ]++;
		}

		$result['entries'] = array_slice( array_reverse( $entries ), 0, max( 1, (int) $max_entries ) );

		return $result;
	}

	/**
	 * Severity counts for entries logged since a point in time.
	 *
	 * Only the newest MAX_READ_BYTES of the file are considered.
	 *
	 * @param int    $since  Unix timestamp.
	 * @param string $source Log source id (see sources()).
	 * @return array level => count
	 */
	public static function counts_since( $since, $source = 'wpdm' ) {
		$log    = self::get_entries( PHP_INT_MAX, $source );
		$counts = array_fill_keys( array_keys( $log['counts'] ), 0 );

		foreach ( $log['entries'] as $entry ) {
			$time = strtotime( $entry['time'] );
			if ( $time && $time >= $since ) {
				$counts[ $entry['level'] ]++;
			}
		}

		return $counts;
	}

	/**
	 * Parse raw log text into structured entries.
	 *
	 * Lines look like: [16-Jul-2026 10:00:00 UTC] PHP Warning:  message in file on line N
	 * Continuation lines (stack traces) are appended to the previous entry.
	 *
	 * @param string $raw Raw log text.
	 * @return array[] Each item: time, level, label, message.
	 */
	private static function parse( $raw ) {
		$lines   = preg_split( '/\r\n|\r|\n/', (string) $raw );
		$entries = array();
		$current = null;

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}

			if ( preg_match( '/^\[([^\]]+)\]\s*(.*)$/', $line, $m ) ) {
				if ( null !== $current ) {
					$entries[] = $current;
				}
				$message = $m[2];
				$current = array(
					'time'    => $m[1],
					'level'   => self::classify( $message ),
					'label'   => self::label( $message ),
					'message' => $message,
				);
			} elseif ( null !== $current ) {
				// Stack trace / continuation line.
				$current['message'] .= "\n" . $line;
			}
		}

		if ( null !== $current ) {
			$entries[] = $current;
		}

		// Drop possibly-partial first entry when we started mid-file.
		return $entries;
	}

	/**
	 * Map a message to a severity bucket.
	 *
	 * @param string $message Log message.
	 * @return string fatal|warning|notice|deprecated|other
	 */
	private static function classify( $message ) {
		if ( preg_match( '/PHP (Fatal error|Parse error)/i', $message ) ) {
			return 'fatal';
		}
		if ( stripos( $message, 'PHP Warning' ) !== false ) {
			return 'warning';
		}
		if ( stripos( $message, 'PHP Deprecated' ) !== false ) {
			return 'deprecated';
		}
		if ( stripos( $message, 'PHP Notice' ) !== false ) {
			return 'notice';
		}
		return 'other';
	}

	/**
	 * Human label for the entry type.
	 *
	 * @param string $message Log message.
	 * @return string
	 */
	private static function label( $message ) {
		if ( preg_match( '/^PHP ([A-Za-z ]+?):/', $message, $m ) ) {
			return trim( $m[1] );
		}
		return __( 'Log', 'wp-dashboard-management' );
	}

	/**
	 * Truncate the log file to empty.
	 *
	 * @param string $source Log source id (see sources()).
	 * @return bool
	 */
	public static function clear( $source = 'wpdm' ) {
		$path = self::source_path( $source );
		if ( ! $path || ! is_file( $path ) || ! is_writable( $path ) ) {
			return false;
		}
		return false !== file_put_contents( $path, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
