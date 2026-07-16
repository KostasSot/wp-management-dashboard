<?php
/**
 * Database snapshots: create, list, restore, delete.
 *
 * Pure-PHP SQL dumps (no mysqldump dependency) stored in a web-protected
 * uploads directory. Restoring replaces every table in the snapshot, which
 * makes core-version rollbacks schema-safe.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_DB_Backup {

	/**
	 * Rows fetched per SELECT while dumping.
	 */
	const CHUNK_ROWS = 500;

	/**
	 * Rows per generated INSERT statement.
	 */
	const INSERT_ROWS = 100;

	/**
	 * Snapshot directory, protected from direct web access.
	 *
	 * @return string
	 */
	public static function dir() {
		$uploads = wp_upload_dir( null, false );
		$dir     = trailingslashit( $uploads['basedir'] ) . 'wpdm-backups';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			@file_put_contents( $dir . '/.htaccess', "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
			@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		}

		return $dir;
	}

	/**
	 * Create a snapshot of every table in the database.
	 *
	 * @param string $label Optional short label stored in the filename.
	 * @return string|WP_Error Snapshot filename on success.
	 */
	public static function create( $label = '' ) {
		global $wpdb, $wp_version;

		$tables = $wpdb->get_col( 'SHOW TABLES' );
		if ( empty( $tables ) ) {
			return new WP_Error( 'wpdm_no_tables', __( 'No database tables found.', 'wp-dashboard-management' ) );
		}

		$label    = sanitize_key( $label );
		$filename = sprintf(
			'snapshot-%s-wp%s%s.sql',
			gmdate( 'Ymd-His' ),
			$wp_version,
			$label ? '-' . $label : ''
		);
		$path     = self::dir() . '/' . $filename;

		$handle = fopen( $path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $handle ) {
			return new WP_Error( 'wpdm_fs', __( 'Could not create the snapshot file.', 'wp-dashboard-management' ) );
		}

		fwrite( $handle, "-- WPDM snapshot\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $handle, '-- wp_version: ' . $wp_version . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $handle, '-- created: ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		foreach ( $tables as $table ) {
			$create = $wpdb->get_row( 'SHOW CREATE TABLE `' . str_replace( '`', '', $table ) . '`', ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! $create || ! isset( $create[1] ) ) {
				continue;
			}

			fwrite( $handle, "DROP TABLE IF EXISTS `{$table}`;\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			fwrite( $handle, str_replace( "\n", ' ', $create[1] ) . ";\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions

			$offset = 0;
			while ( true ) {
				$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$wpdb->prepare( 'SELECT * FROM `' . str_replace( '`', '', $table ) . '` LIMIT %d OFFSET %d', self::CHUNK_ROWS, $offset ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					ARRAY_A
				);
				if ( empty( $rows ) ) {
					break;
				}

				foreach ( array_chunk( $rows, self::INSERT_ROWS ) as $chunk ) {
					$values = array();
					foreach ( $chunk as $row ) {
						$cells = array();
						foreach ( $row as $cell ) {
							if ( null === $cell ) {
								$cells[] = 'NULL';
							} else {
								// _real_escape encodes newlines, so statements stay single-line.
								$cells[] = "'" . $wpdb->_real_escape( $cell ) . "'";
							}
						}
						$values[] = '(' . implode( ',', $cells ) . ')';
					}
					fwrite( $handle, "INSERT INTO `{$table}` VALUES " . implode( ',', $values ) . ";\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}

				$offset += self::CHUNK_ROWS;
			}

			fwrite( $handle, "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return $filename;
	}

	/**
	 * List snapshots, newest first.
	 *
	 * @return array[] Each item: file, size, created, wp_version.
	 */
	public static function get_list() {
		$out   = array();
		$files = glob( self::dir() . '/snapshot-*.sql' );

		foreach ( (array) $files as $path ) {
			$wp_ver = '';
			$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( $handle ) {
				for ( $i = 0; $i < 3; $i++ ) {
					$line = fgets( $handle );
					if ( $line && preg_match( '/^-- wp_version: (.+)$/', trim( $line ), $m ) ) {
						$wp_ver = $m[1];
					}
				}
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}

			$out[] = array(
				'file'       => basename( $path ),
				'size'       => (int) filesize( $path ),
				'created'    => (int) filemtime( $path ),
				'wp_version' => $wp_ver,
			);
		}

		usort(
			$out,
			function ( $a, $b ) {
				return $b['created'] - $a['created'];
			}
		);

		return $out;
	}

	/**
	 * Validate a snapshot filename and return its full path.
	 *
	 * @param string $file Snapshot filename (no path).
	 * @return string|WP_Error
	 */
	private static function resolve( $file ) {
		if ( ! preg_match( '/^snapshot-[A-Za-z0-9.\-]+\.sql$/', $file ) ) {
			return new WP_Error( 'wpdm_bad_name', __( 'Invalid snapshot name.', 'wp-dashboard-management' ) );
		}
		$path = self::dir() . '/' . $file;
		if ( ! is_file( $path ) ) {
			return new WP_Error( 'wpdm_missing', __( 'Snapshot not found.', 'wp-dashboard-management' ) );
		}
		return $path;
	}

	/**
	 * Restore a snapshot: every table in the dump is dropped and re-created.
	 *
	 * @param string $file Snapshot filename.
	 * @return true|WP_Error
	 */
	public static function restore( $file ) {
		global $wpdb;

		$path = self::resolve( $file );
		if ( is_wp_error( $path ) ) {
			return $path;
		}

		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $handle ) {
			return new WP_Error( 'wpdm_fs', __( 'Could not open the snapshot file.', 'wp-dashboard-management' ) );
		}

		// Statements are written one per line, so restore is line-by-line.
		while ( ( $line = fgets( $handle ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			$line = rtrim( $line, "\r\n" );
			if ( '' === $line || 0 === strpos( $line, '--' ) ) {
				continue;
			}
			$result = $wpdb->query( rtrim( $line, ';' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false === $result ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return new WP_Error(
					'wpdm_restore_failed',
					sprintf(
						/* translators: %s: database error */
						__( 'Restore stopped on a failed statement: %s', 'wp-dashboard-management' ),
						$wpdb->last_error
					)
				);
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		wp_cache_flush();

		return true;
	}

	/**
	 * Delete a snapshot file.
	 *
	 * @param string $file Snapshot filename.
	 * @return true|WP_Error
	 */
	public static function delete( $file ) {
		$path = self::resolve( $file );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! unlink( $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'wpdm_fs', __( 'Could not delete the snapshot file.', 'wp-dashboard-management' ) );
		}
		return true;
	}
}
