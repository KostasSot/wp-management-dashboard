<?php
/**
 * WP-CLI commands: `wp wpdm ...`.
 *
 * Thin wrappers around the module classes. Disabled modules stay disabled
 * here too, matching the dashboard and AJAX behaviour.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_CLI {

	/**
	 * Register the commands.
	 */
	public static function register() {
		WP_CLI::add_command( 'wpdm backup create', array( __CLASS__, 'backup_create' ) );
		WP_CLI::add_command( 'wpdm backup list', array( __CLASS__, 'backup_list' ) );
		WP_CLI::add_command( 'wpdm backup restore', array( __CLASS__, 'backup_restore' ) );
		WP_CLI::add_command( 'wpdm backup delete', array( __CLASS__, 'backup_delete' ) );
		WP_CLI::add_command( 'wpdm report send', array( __CLASS__, 'report_send' ) );
		WP_CLI::add_command( 'wpdm security', array( __CLASS__, 'security' ) );
		WP_CLI::add_command( 'wpdm scan', array( __CLASS__, 'scan' ) );
	}

	/**
	 * Create a database snapshot.
	 *
	 * ## OPTIONS
	 *
	 * [--label=<label>]
	 * : Short label added to the filename.
	 * ---
	 * default: cli
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpdm backup create --label=before-migration
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function backup_create( $args, $assoc_args ) {
		self::require_feature( 'snapshots' );
		$file = self::ok( WPDM_DB_Backup::create( $assoc_args['label'] ) );
		WP_CLI::success( "Snapshot created: {$file}" );
	}

	/**
	 * List database snapshots, newest first.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function backup_list( $args, $assoc_args ) {
		self::require_feature( 'snapshots' );
		$rows = array();
		foreach ( WPDM_DB_Backup::get_list() as $snapshot ) {
			$rows[] = array(
				'file'       => $snapshot['file'],
				'created'    => wp_date( 'Y-m-d H:i', $snapshot['created'] ),
				'wp_version' => $snapshot['wp_version'],
				'size'       => size_format( $snapshot['size'] ),
			);
		}
		WP_CLI\Utils\format_items( $assoc_args['format'], $rows, array( 'file', 'created', 'wp_version', 'size' ) );
	}

	/**
	 * Restore a database snapshot. Every table in the snapshot is replaced.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Snapshot filename (see `wp wpdm backup list`).
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function backup_restore( $args, $assoc_args ) {
		self::require_feature( 'snapshots' );
		WP_CLI::confirm( "Restore the database from {$args[0]}? Changes made since then are lost.", $assoc_args );
		self::ok( WPDM_DB_Backup::restore( $args[0] ) );
		WP_CLI::success( 'Database restored.' );
	}

	/**
	 * Delete a database snapshot.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Snapshot filename.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function backup_delete( $args, $assoc_args ) {
		self::require_feature( 'snapshots' );
		WP_CLI::confirm( "Delete snapshot {$args[0]}?", $assoc_args );
		self::ok( WPDM_DB_Backup::delete( $args[0] ) );
		WP_CLI::success( 'Snapshot deleted.' );
	}

	/**
	 * Send the email update report now.
	 *
	 * ## OPTIONS
	 *
	 * [--to=<email>]
	 * : Recipient; defaults to the saved report address.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function report_send( $args, $assoc_args ) {
		$to = isset( $assoc_args['to'] ) ? $assoc_args['to'] : '';
		if ( '' !== $to && ! is_email( $to ) ) {
			WP_CLI::error( 'Invalid --to email address.' );
		}
		self::ok( WPDM_Email_Report::send( $to ) );
		WP_CLI::success( 'Report sent.' );
	}

	/**
	 * Run the security health check.
	 *
	 * Exits with status 1 when a check fails, for use in CI or monitoring.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function security( $args, $assoc_args ) {
		self::require_feature( 'security' );
		$summary = WPDM_Security_Check::summary();
		$rows    = array();
		foreach ( $summary['checks'] as $check ) {
			$rows[] = array(
				'check'  => $check['id'],
				'status' => $check['pass'] ? 'pass' : 'FAIL',
				'label'  => $check['label'],
			);
		}
		WP_CLI\Utils\format_items( $assoc_args['format'], $rows, array( 'check', 'status', 'label' ) );

		if ( $summary['passed'] < $summary['total'] ) {
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * Scan for known vulnerabilities and abandoned plugins (enabled modules only).
	 *
	 * Exits with status 1 when anything is found.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function scan( $args, $assoc_args ) {
		$vulns     = WPDM_Settings::enabled( 'vulnerabilities' );
		$abandoned = WPDM_Settings::enabled( 'abandoned' );
		if ( ! $vulns && ! $abandoned ) {
			WP_CLI::error( 'The Vulnerability Scan and Abandoned Plugin Detection modules are both disabled. Enable them in Management → Setup.' );
		}

		$rows = array();
		if ( $vulns ) {
			$results = WPDM_Vulnerabilities::scan();
			foreach ( $results['items'] as $item ) {
				foreach ( $item['vulns'] as $vuln ) {
					$rows[] = array(
						'component' => "{$item['type']}: {$item['name']} {$item['version']}",
						'issue'     => $vuln['title'],
						'fix'       => $vuln['fixed_in'] ? "update to {$vuln['fixed_in']}" : 'no fix yet',
					);
				}
			}
			if ( $results['failed'] ) {
				WP_CLI::warning( "{$results['failed']} component(s) could not be checked (network error)." );
			}
		}
		if ( $abandoned ) {
			foreach ( WPDM_Abandoned_Plugins::scan()['plugins'] as $plugin ) {
				$rows[] = array(
					'component' => "plugin: {$plugin['name']} {$plugin['version']}",
					'issue'     => WPDM_Abandoned_Plugins::label( $plugin ),
					'fix'       => 'replace or remove',
				);
			}
		}

		if ( empty( $rows ) ) {
			WP_CLI::success( 'Nothing found.' );
			return;
		}

		WP_CLI\Utils\format_items( $assoc_args['format'], $rows, array( 'component', 'issue', 'fix' ) );
		WP_CLI::halt( 1 );
	}

	/**
	 * Stop when a module is disabled.
	 *
	 * @param string $feature Feature key.
	 */
	private static function require_feature( $feature ) {
		if ( ! WPDM_Settings::enabled( $feature ) ) {
			WP_CLI::error( "The '{$feature}' module is disabled. Enable it in Management → Setup." );
		}
	}

	/**
	 * Unwrap a module result, exiting on WP_Error.
	 *
	 * @param mixed $result Result or WP_Error.
	 * @return mixed
	 */
	private static function ok( $result ) {
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		return $result;
	}
}
