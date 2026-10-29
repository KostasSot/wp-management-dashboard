<?php
/**
 * Scheduled email update reports.
 *
 * Sends an HTML report — WordPress core version (and pending core update),
 * plus available updates for active plugins and all themes — to a
 * configurable address on a daily/weekly/monthly schedule via WP-Cron.
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPDM_Email_Report {

	const CRON_HOOK = 'wpdm_send_report';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'send' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'add_monthly_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval
	}

	/**
	 * Valid frequencies (value => cron recurrence).
	 *
	 * @return array
	 */
	public static function frequencies() {
		return array(
			'disabled' => '',
			'daily'    => 'daily',
			'weekly'   => 'weekly',
			'monthly'  => 'wpdm_monthly',
		);
	}

	/**
	 * Add a ~monthly recurrence (WP-Cron has none built in).
	 *
	 * @param array $schedules Registered schedules.
	 * @return array
	 */
	public static function add_monthly_schedule( $schedules ) {
		$schedules['wpdm_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Once a month (every 30 days)', 'wp-dashboard-management' ),
		);
		return $schedules;
	}

	/**
	 * (Re)schedule the cron event to match the saved settings.
	 */
	public static function reschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );

		$report = WPDM_Settings::report_settings();
		$map    = self::frequencies();

		if ( 'disabled' === $report['frequency'] || empty( $map[ $report['frequency'] ] ) || ! is_email( $report['email'] ) ) {
			return;
		}

		$intervals = array(
			'daily'   => DAY_IN_SECONDS,
			'weekly'  => WEEK_IN_SECONDS,
			'monthly' => 30 * DAY_IN_SECONDS,
		);

		wp_schedule_event(
			time() + $intervals[ $report['frequency'] ],
			$map[ $report['frequency'] ],
			self::CRON_HOOK
		);
	}

	/**
	 * Next scheduled run, if any.
	 *
	 * @return int 0 when nothing is scheduled.
	 */
	public static function next_run() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		return $ts ? (int) $ts : 0;
	}

	/**
	 * Collect the report data.
	 *
	 * @return array
	 */
	public static function data() {
		// Make sure update info is reasonably fresh before reporting.
		if ( ! function_exists( 'wp_version_check' ) ) {
			require_once ABSPATH . 'wp-includes/update.php';
		}
		wp_version_check();
		wp_update_plugins();
		wp_update_themes();

		$system = WPDM_System_Info::get();

		$plugins = array();
		foreach ( WPDM_Plugins_Status::get_all() as $plugin ) {
			if ( $plugin['active'] && $plugin['update_available'] ) {
				$plugins[] = $plugin;
			}
		}

		$themes = array();
		foreach ( WPDM_Themes_Status::get_all() as $theme ) {
			if ( $theme['update_available'] ) {
				$themes[] = $theme;
			}
		}

		return array(
			'site'            => get_bloginfo( 'name' ),
			'url'             => site_url(),
			'system'          => $system,
			'plugins'         => $plugins,
			'themes'          => $themes,
			// Optional sections: null when their module is disabled.
			'security'        => WPDM_Settings::enabled( 'security' ) ? WPDM_Security_Check::summary() : null,
			'site_health'     => WPDM_Settings::enabled( 'site_health' ) ? WPDM_Site_Health::results() : null,
			'php_errors'      => WPDM_Settings::enabled( 'php_logs' ) ? WPDM_PHP_Logs::counts_since( time() - DAY_IN_SECONDS ) : null,
			'vulnerabilities' => WPDM_Settings::enabled( 'vulnerabilities' ) ? WPDM_Vulnerabilities::results() : null,
			'abandoned'       => WPDM_Settings::enabled( 'abandoned' ) ? WPDM_Abandoned_Plugins::results() : null,
		);
	}

	/**
	 * Send the report to the configured address.
	 *
	 * @param string $override_email Optional recipient override (test sends).
	 * @return true|WP_Error
	 */
	public static function send( $override_email = '' ) {
		if ( ! WPDM_Settings::enabled( 'email_reports' ) ) {
			return new WP_Error( 'wpdm_reports_disabled', __( 'The email reports module is disabled.', 'wp-dashboard-management' ) );
		}

		$report = WPDM_Settings::report_settings();
		$email  = ( is_string( $override_email ) && is_email( $override_email ) ) ? $override_email : $report['email'];

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'wpdm_no_email', __( 'No valid report email address is configured.', 'wp-dashboard-management' ) );
		}

		$data    = self::data();
		$pending = count( $data['plugins'] ) + count( $data['themes'] ) + ( $data['system']['wp_needs_update'] ? 1 : 0 );

		$subject = sprintf(
			/* translators: 1: site name, 2: pending update count */
			__( '[%1$s] Update report — %2$d update(s) pending', 'wp-dashboard-management' ),
			$data['site'],
			$pending
		);

		$vulnerable = null === $data['vulnerabilities'] ? 0 : count( $data['vulnerabilities']['items'] );
		if ( $vulnerable ) {
			/* translators: %d: number of vulnerable components */
			$subject .= ' · ' . sprintf( __( '%d vulnerable component(s)', 'wp-dashboard-management' ), $vulnerable );
		}

		$sent = wp_mail(
			$email,
			$subject,
			self::render_html( $data ),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);

		if ( ! $sent ) {
			return new WP_Error( 'wpdm_mail_failed', __( 'wp_mail() could not send the report (check the site\'s mail configuration).', 'wp-dashboard-management' ) );
		}

		return true;
	}

	/**
	 * The HTML report body (inline styles — email clients ignore stylesheets).
	 *
	 * @param array $data Report data from data().
	 * @return string
	 */
	public static function render_html( array $data ) {
		$system = $data['system'];

		$table_open = '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 24px;">';
		$th         = 'style="background:#365F91;color:#ffffff;text-align:left;padding:8px 12px;font-size:13px;"';
		$td         = 'style="padding:8px 12px;border-bottom:1px solid #e8ebee;font-size:13px;color:#333333;"';
		$badge_ok   = 'style="color:#00801a;font-weight:bold;"';
		$badge_warn = 'style="color:#b45309;font-weight:bold;"';

		$html  = '<div style="background:#f4f5f7;padding:24px;font-family:Arial,Helvetica,sans-serif;">';
		$html .= '<div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:8px;padding:32px;">';

		$html .= '<h1 style="color:#1E395B;font-size:20px;margin:0 0 4px;">' . esc_html__( 'WordPress Update Report', 'wp-dashboard-management' ) . '</h1>';
		$html .= '<p style="color:#666666;font-size:13px;margin:0 0 24px;">'
			. esc_html( $data['site'] ) . ' &middot; <a href="' . esc_url( $data['url'] ) . '" style="color:#365F91;">' . esc_html( $data['url'] ) . '</a>'
			. ' &middot; ' . esc_html( wp_date( 'j F Y, H:i' ) ) . '</p>';

		// Core.
		$html .= '<h2 style="color:#1E395B;font-size:15px;margin:0 0 8px;">' . esc_html__( 'WordPress Core', 'wp-dashboard-management' ) . '</h2>';
		$html .= $table_open;
		$html .= '<tr><th ' . $th . '>' . esc_html__( 'Current Version', 'wp-dashboard-management' ) . '</th><th ' . $th . '>' . esc_html__( 'Status', 'wp-dashboard-management' ) . '</th></tr>';
		$html .= '<tr><td ' . $td . '>' . esc_html( $system['wp_version'] ) . '</td><td ' . $td . '>';
		if ( $system['wp_needs_update'] ) {
			/* translators: %s: version number */
			$html .= '<span ' . $badge_warn . '>' . sprintf( esc_html__( 'Update available: %s', 'wp-dashboard-management' ), esc_html( $system['wp_latest'] ) ) . '</span>';
		} else {
			$html .= '<span ' . $badge_ok . '>' . esc_html__( 'Up to date', 'wp-dashboard-management' ) . '</span>';
		}
		$html .= '</td></tr></table>';

		// Plugins & themes sections share a layout.
		$sections = array(
			array(
				'title' => __( 'Active Plugins With Updates', 'wp-dashboard-management' ),
				'first' => __( 'Plugin', 'wp-dashboard-management' ),
				'items' => $data['plugins'],
				'empty' => __( 'All active plugins are up to date.', 'wp-dashboard-management' ),
			),
			array(
				'title' => __( 'Themes With Updates', 'wp-dashboard-management' ),
				'first' => __( 'Theme', 'wp-dashboard-management' ),
				'items' => $data['themes'],
				'empty' => __( 'All themes are up to date.', 'wp-dashboard-management' ),
			),
		);

		foreach ( $sections as $section ) {
			$html .= '<h2 style="color:#1E395B;font-size:15px;margin:0 0 8px;">' . esc_html( $section['title'] ) . '</h2>';
			if ( empty( $section['items'] ) ) {
				$html .= '<p style="font-size:13px;margin:0 0 24px;"><span ' . $badge_ok . '>' . esc_html( $section['empty'] ) . '</span></p>';
				continue;
			}
			$html .= $table_open;
			$html .= '<tr><th ' . $th . '>' . esc_html( $section['first'] ) . '</th><th ' . $th . '>' . esc_html__( 'Current', 'wp-dashboard-management' ) . '</th><th ' . $th . '>' . esc_html__( 'Available', 'wp-dashboard-management' ) . '</th></tr>';
			foreach ( $section['items'] as $item ) {
				$html .= '<tr><td ' . $td . '>' . esc_html( $item['name'] ) . '</td>'
					. '<td ' . $td . '>' . esc_html( $item['version'] ) . '</td>'
					. '<td ' . $td . '><span ' . $badge_warn . '>' . esc_html( $item['new_version'] ) . '</span></td></tr>';
			}
			$html .= '</table>';
		}

		$html .= self::render_health_sections( $data, $th, $td, $table_open, $badge_ok, $badge_warn );

		$html .= '<p style="color:#999999;font-size:11px;margin:24px 0 0;">'
			. esc_html__( 'Sent by the WP Dashboard Management plugin.', 'wp-dashboard-management' ) . '</p>';
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Security, Site Health, PHP error, vulnerability, and abandoned-plugin
	 * sections. Each appears only when its module is enabled.
	 *
	 * @param array  $data       Report data from data().
	 * @param string $th         Header cell style attribute.
	 * @param string $td         Body cell style attribute.
	 * @param string $table_open Opening table tag.
	 * @param string $badge_ok   "Good" text style attribute.
	 * @param string $badge_warn "Attention" text style attribute.
	 * @return string
	 */
	private static function render_health_sections( array $data, $th, $td, $table_open, $badge_ok, $badge_warn ) {
		$h2   = '<h2 style="color:#1E395B;font-size:15px;margin:0 0 8px;">';
		$p    = '<p style="font-size:13px;margin:0 0 24px;">';
		$html = '';

		// Cells are pre-escaped HTML; headers are plain text.
		$table = static function ( array $headers, array $rows ) use ( $th, $td, $table_open ) {
			$out = $table_open . '<tr>';
			foreach ( $headers as $header ) {
				$out .= '<th ' . $th . '>' . esc_html( $header ) . '</th>';
			}
			$out .= '</tr>';
			foreach ( $rows as $row ) {
				$out .= '<tr>';
				foreach ( $row as $cell ) {
					$out .= '<td ' . $td . '>' . $cell . '</td>';
				}
				$out .= '</tr>';
			}
			return $out . '</table>';
		};

		if ( null !== $data['security'] ) {
			$security = $data['security'];
			$html    .= $h2 . esc_html__( 'Security Health Check', 'wp-dashboard-management' ) . '</h2>';
			$failing  = wp_list_filter( $security['checks'], array( 'pass' => false ) );
			if ( empty( $failing ) ) {
				/* translators: %d: number of checks */
				$html .= $p . '<span ' . $badge_ok . '>' . esc_html( sprintf( __( 'All %d checks passed.', 'wp-dashboard-management' ), $security['total'] ) ) . '</span></p>';
			} else {
				$rows = array();
				foreach ( $failing as $check ) {
					$rows[] = array( '<span ' . $badge_warn . '>' . esc_html( $check['label'] ) . '</span>' );
				}
				/* translators: 1: passed count, 2: total count */
				$html .= $table( array( sprintf( __( 'Failing checks (%1$d / %2$d passed)', 'wp-dashboard-management' ), $security['passed'], $security['total'] ) ), $rows );
			}
		}

		if ( null !== $data['site_health'] ) {
			$counts = $data['site_health']['counts'];
			$style  = $counts['critical'] ? $badge_warn : $badge_ok;
			$html  .= $h2 . esc_html__( 'Site Health', 'wp-dashboard-management' ) . '</h2>';
			/* translators: 1: good count, 2: recommended count, 3: critical count */
			$html .= $p . '<span ' . $style . '>' . esc_html( sprintf( __( '%1$d good · %2$d recommended · %3$d critical', 'wp-dashboard-management' ), $counts['good'], $counts['recommended'], $counts['critical'] ) ) . '</span></p>';
		}

		if ( null !== $data['php_errors'] ) {
			$errors = $data['php_errors'];
			$style  = $errors['fatal'] ? $badge_warn : $badge_ok;
			$html  .= $h2 . esc_html__( 'PHP Errors (last 24 hours)', 'wp-dashboard-management' ) . '</h2>';
			/* translators: 1: fatal count, 2: warning count, 3: notice count, 4: deprecated count */
			$html .= $p . '<span ' . $style . '>' . esc_html( sprintf( __( '%1$d fatal · %2$d warnings · %3$d notices · %4$d deprecated', 'wp-dashboard-management' ), $errors['fatal'], $errors['warning'], $errors['notice'], $errors['deprecated'] ) ) . '</span></p>';
		}

		if ( null !== $data['vulnerabilities'] ) {
			$html .= $h2 . esc_html__( 'Known Vulnerabilities', 'wp-dashboard-management' ) . '</h2>';
			if ( ! $data['vulnerabilities']['checked_at'] ) {
				$html .= $p . esc_html__( 'Not scanned yet.', 'wp-dashboard-management' ) . '</p>';
			} elseif ( empty( $data['vulnerabilities']['items'] ) ) {
				$html .= $p . '<span ' . $badge_ok . '>' . esc_html__( 'No known vulnerabilities in installed versions.', 'wp-dashboard-management' ) . '</span></p>';
			} else {
				$rows = array();
				foreach ( $data['vulnerabilities']['items'] as $item ) {
					foreach ( $item['vulns'] as $vuln ) {
						$rows[] = array(
							esc_html( $item['name'] . ' ' . $item['version'] ),
							'<span ' . $badge_warn . '>' . esc_html( $vuln['title'] ) . '</span>',
							esc_html( $vuln['fixed_in'] ? $vuln['fixed_in'] : __( 'No fix yet', 'wp-dashboard-management' ) ),
						);
					}
				}
				$html .= $table( array( __( 'Component', 'wp-dashboard-management' ), __( 'Issue', 'wp-dashboard-management' ), __( 'Fixed in', 'wp-dashboard-management' ) ), $rows );
			}
		}

		if ( null !== $data['abandoned'] ) {
			$html .= $h2 . esc_html__( 'Abandoned Plugins', 'wp-dashboard-management' ) . '</h2>';
			if ( ! $data['abandoned']['checked_at'] ) {
				$html .= $p . esc_html__( 'Not scanned yet.', 'wp-dashboard-management' ) . '</p>';
			} elseif ( empty( $data['abandoned']['plugins'] ) ) {
				$html .= $p . '<span ' . $badge_ok . '>' . esc_html__( 'No closed or abandoned plugins.', 'wp-dashboard-management' ) . '</span></p>';
			} else {
				$rows = array();
				foreach ( $data['abandoned']['plugins'] as $plugin ) {
					$rows[] = array(
						esc_html( $plugin['name'] ),
						'<span ' . $badge_warn . '>' . esc_html( WPDM_Abandoned_Plugins::label( $plugin ) ) . '</span>',
					);
				}
				$html .= $table( array( __( 'Plugin', 'wp-dashboard-management' ), __( 'Status', 'wp-dashboard-management' ) ), $rows );
			}
		}

		return $html;
	}
}
