<?php
/**
 * Dashboard page template.
 *
 * Variables provided by WPDM_Admin::render_page():
 * $system, $plugins, $plugin_summary, $logs, $log_sources, $log_source,
 * $activity_status, $activity_stats
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpdm-wrap">
	<h1 class="wpdm-title">
		<span class="dashicons dashicons-dashboard"></span>
		<?php esc_html_e( 'WP Dashboard Management', 'wp-dashboard-management' ); ?>
	</h1>
	<div class="wpdm-notice-area" id="wpdm-notices" aria-live="polite">
		<?php if ( isset( $_GET['wpdm-setup'] ) && 'done' === $_GET['wpdm-setup'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Setup saved — the dashboard now shows your selected modules.', 'wp-dashboard-management' ); ?></p></div>
		<?php endif; ?>
	</div>

	<!-- ===== Stat cards ===== -->
	<div class="wpdm-cards">
		<div class="wpdm-card <?php echo $system['wp_needs_update'] ? 'is-warning' : 'is-good'; ?>">
			<span class="wpdm-card-label"><?php esc_html_e( 'WordPress', 'wp-dashboard-management' ); ?></span>
			<span class="wpdm-card-value"><?php echo esc_html( $system['wp_version'] ); ?></span>
			<span class="wpdm-card-sub">
				<?php
				if ( $system['wp_needs_update'] ) {
					/* translators: %s: version number */
					printf( esc_html__( 'Update available: %s', 'wp-dashboard-management' ), esc_html( $system['wp_latest'] ) );
				} else {
					esc_html_e( 'Up to date', 'wp-dashboard-management' );
				}
				?>
			</span>
		</div>

		<div class="wpdm-card is-info">
			<span class="wpdm-card-label"><?php esc_html_e( 'PHP', 'wp-dashboard-management' ); ?></span>
			<span class="wpdm-card-value"><?php echo esc_html( $system['php_version'] ); ?></span>
			<span class="wpdm-card-sub"><?php echo esc_html( $system['server_software'] ); ?></span>
		</div>

		<?php if ( $features['updates'] ) : ?>
		<div class="wpdm-card <?php echo ( $plugin_summary['updates'] + $theme_summary['updates'] ) ? 'is-warning' : 'is-good'; ?>">
			<span class="wpdm-card-label"><?php esc_html_e( 'Updates pending', 'wp-dashboard-management' ); ?></span>
			<span class="wpdm-card-value"><?php echo esc_html( $plugin_summary['updates'] + $theme_summary['updates'] ); ?></span>
			<span class="wpdm-card-sub">
				<?php
				/* translators: 1: plugin update count, 2: theme update count */
				printf( esc_html__( '%1$d plugins / %2$d themes', 'wp-dashboard-management' ), (int) $plugin_summary['updates'], (int) $theme_summary['updates'] );
				?>
			</span>
		</div>

		<?php endif; ?>

		<?php if ( $features['php_logs'] ) : ?>
		<div class="wpdm-card <?php echo $logs['counts']['fatal'] ? 'is-error' : ( $logs['counts']['warning'] ? 'is-warning' : 'is-good' ); ?>">
			<span class="wpdm-card-label"><?php esc_html_e( 'PHP log', 'wp-dashboard-management' ); ?></span>
			<span class="wpdm-card-value"><?php echo esc_html( count( $logs['entries'] ) ); ?></span>
			<span class="wpdm-card-sub">
				<?php
				/* translators: 1: fatal count, 2: warning count */
				printf( esc_html__( '%1$d fatal / %2$d warnings', 'wp-dashboard-management' ), (int) $logs['counts']['fatal'], (int) $logs['counts']['warning'] );
				?>
			</span>
		</div>

		<?php endif; ?>

		<div class="wpdm-card is-info">
			<span class="wpdm-card-label"><?php esc_html_e( 'Database', 'wp-dashboard-management' ); ?></span>
			<span class="wpdm-card-value"><?php echo esc_html( $system['db_version'] ); ?></span>
			<span class="wpdm-card-sub"><?php echo esc_html( $system['db_server_info'] ); ?></span>
		</div>
	</div>

	<div class="wpdm-columns">
		<!-- ===== Plugins ===== -->
		<?php if ( $features['updates'] || $features['export'] ) : ?>
		<div class="wpdm-panel wpdm-panel-plugins">
			<div class="wpdm-panel-head">
				<h2><?php esc_html_e( 'Plugins', 'wp-dashboard-management' ); ?></h2>
				<?php if ( $features['export'] ) : ?>
					<a class="button" href="<?php echo esc_url( WPDM_Export::url() ); ?>">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export updates (XLSX)', 'wp-dashboard-management' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $features['updates'] ) : ?>
				<button type="button" class="button" id="wpdm-refresh-updates">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Check for updates', 'wp-dashboard-management' ); ?>
				</button>
				<?php endif; ?>
			</div>

			<?php if ( ! $features['updates'] ) : ?>
				<p class="wpdm-muted"><?php esc_html_e( 'The overview tables are disabled — use the button above to export the pending-updates report.', 'wp-dashboard-management' ); ?></p>
			<?php else : ?>
			<table class="widefat striped wpdm-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Plugin', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'Version', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'Update', 'wp-dashboard-management' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plugins as $plugin ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $plugin['name'] ); ?></strong>
								<div class="wpdm-muted"><?php echo esc_html( $plugin['author'] ); ?></div>
							</td>
							<td><?php echo esc_html( $plugin['version'] ); ?></td>
							<td>
								<?php if ( $plugin['active'] ) : ?>
									<span class="wpdm-badge wpdm-badge-green"><?php esc_html_e( 'Active', 'wp-dashboard-management' ); ?></span>
									<?php if ( $features['manage'] && current_user_can( 'activate_plugins' ) && plugin_basename( WPDM_PLUGIN_FILE ) !== $plugin['file'] ) : ?>
										<button type="button" class="button button-small wpdm-toggle-plugin-btn"
											data-plugin="<?php echo esc_attr( $plugin['file'] ); ?>"
											data-name="<?php echo esc_attr( $plugin['name'] ); ?>"
											data-enable="0">
											<?php esc_html_e( 'Deactivate', 'wp-dashboard-management' ); ?>
										</button>
									<?php endif; ?>
								<?php else : ?>
									<span class="wpdm-badge wpdm-badge-gray"><?php esc_html_e( 'Inactive', 'wp-dashboard-management' ); ?></span>
									<?php if ( $features['manage'] && current_user_can( 'activate_plugins' ) ) : ?>
										<button type="button" class="button button-small wpdm-toggle-plugin-btn"
											data-plugin="<?php echo esc_attr( $plugin['file'] ); ?>"
											data-name="<?php echo esc_attr( $plugin['name'] ); ?>"
											data-enable="1">
											<?php esc_html_e( 'Activate', 'wp-dashboard-management' ); ?>
										</button>
									<?php endif; ?>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $plugin['update_available'] ) : ?>
									<span class="wpdm-badge wpdm-badge-orange">
										<?php
										/* translators: %s: version number */
										printf( esc_html__( '%s available', 'wp-dashboard-management' ), esc_html( $plugin['new_version'] ) );
										?>
									</span>
									<?php if ( $features['manage'] && current_user_can( 'update_plugins' ) ) : ?>
										<button type="button" class="button button-small wpdm-update-btn"
											data-type="plugin"
											data-plugin="<?php echo esc_attr( $plugin['file'] ); ?>"
											data-slug="<?php echo esc_attr( dirname( $plugin['file'] ) ); ?>">
											<?php esc_html_e( 'Update now', 'wp-dashboard-management' ); ?>
										</button>
									<?php endif; ?>
								<?php else : ?>
									<span class="wpdm-muted">&mdash;</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h3 class="wpdm-subsection-title"><?php esc_html_e( 'Themes', 'wp-dashboard-management' ); ?></h3>
			<table class="widefat striped wpdm-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Theme', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'Version', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'Update', 'wp-dashboard-management' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $themes as $theme ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $theme['name'] ); ?></strong>
								<div class="wpdm-muted"><?php echo esc_html( $theme['author'] ); ?></div>
							</td>
							<td><?php echo esc_html( $theme['version'] ); ?></td>
							<td>
								<?php if ( $theme['active'] ) : ?>
									<span class="wpdm-badge wpdm-badge-green"><?php esc_html_e( 'Active', 'wp-dashboard-management' ); ?></span>
								<?php else : ?>
									<span class="wpdm-badge wpdm-badge-gray"><?php esc_html_e( 'Inactive', 'wp-dashboard-management' ); ?></span>
									<?php if ( $features['manage'] && current_user_can( 'switch_themes' ) ) : ?>
										<button type="button" class="button button-small wpdm-activate-theme-btn"
											data-stylesheet="<?php echo esc_attr( $theme['stylesheet'] ); ?>"
											data-name="<?php echo esc_attr( $theme['name'] ); ?>">
											<?php esc_html_e( 'Activate', 'wp-dashboard-management' ); ?>
										</button>
									<?php endif; ?>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $theme['update_available'] ) : ?>
									<span class="wpdm-badge wpdm-badge-orange">
										<?php
										/* translators: %s: version number */
										printf( esc_html__( '%s available', 'wp-dashboard-management' ), esc_html( $theme['new_version'] ) );
										?>
									</span>
									<?php if ( $features['manage'] && current_user_can( 'update_themes' ) ) : ?>
										<button type="button" class="button button-small wpdm-update-btn"
											data-type="theme"
											data-slug="<?php echo esc_attr( $theme['stylesheet'] ); ?>">
											<?php esc_html_e( 'Update now', 'wp-dashboard-management' ); ?>
										</button>
									<?php endif; ?>
								<?php else : ?>
									<span class="wpdm-muted">&mdash;</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>

		<?php endif; ?>

		<!-- ===== Environment ===== -->
		<div class="wpdm-panel wpdm-panel-env">
			<div class="wpdm-panel-head">
				<h2><?php esc_html_e( 'Environment', 'wp-dashboard-management' ); ?></h2>
			</div>
			<table class="widefat striped wpdm-table wpdm-env-table">
				<tbody>
					<tr><td><?php esc_html_e( 'Site URL', 'wp-dashboard-management' ); ?></td><td><?php echo esc_html( $system['site_url'] ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Active theme', 'wp-dashboard-management' ); ?></td><td><?php echo esc_html( $system['active_theme'] ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Timezone', 'wp-dashboard-management' ); ?></td><td><?php echo esc_html( $system['timezone'] ); ?></td></tr>
					<tr><td><?php esc_html_e( 'PHP memory limit', 'wp-dashboard-management' ); ?></td><td><?php echo esc_html( $system['memory_limit'] ); ?></td></tr>
					<tr><td><?php esc_html_e( 'WP memory limit', 'wp-dashboard-management' ); ?></td><td><?php echo esc_html( $system['wp_memory_limit'] ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Max execution time', 'wp-dashboard-management' ); ?></td><td><?php echo esc_html( $system['max_execution'] ); ?>s</td></tr>
					<tr><td><?php esc_html_e( 'Upload max filesize', 'wp-dashboard-management' ); ?></td><td><?php echo esc_html( $system['upload_max'] ); ?></td></tr>
					<?php if ( $features['debug_toggle'] ) : ?>
					<tr>
						<td><?php esc_html_e( 'Debug mode (WP_DEBUG)', 'wp-dashboard-management' ); ?></td>
						<td class="wpdm-flag-cell">
							<span class="wpdm-badge <?php echo $system['debug_enabled'] ? 'wpdm-badge-orange' : 'wpdm-badge-gray'; ?>">
								<?php echo $system['debug_enabled'] ? esc_html__( 'Enabled', 'wp-dashboard-management' ) : esc_html__( 'Disabled', 'wp-dashboard-management' ); ?>
							</span>
							<button type="button" class="button button-small" id="wpdm-toggle-debug"
								data-value="<?php echo $system['debug_enabled'] ? '0' : '1'; ?>">
								<?php echo $system['debug_enabled'] ? esc_html__( 'Disable', 'wp-dashboard-management' ) : esc_html__( 'Enable', 'wp-dashboard-management' ); ?>
							</button>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<td><?php esc_html_e( 'Multisite', 'wp-dashboard-management' ); ?></td>
						<td><?php echo $system['is_multisite'] ? esc_html__( 'Yes', 'wp-dashboard-management' ) : esc_html__( 'No', 'wp-dashboard-management' ); ?></td>
					</tr>
				</tbody>
			</table>

			<?php if ( ! empty( $core_versions ) ) : ?>
				<h3 class="wpdm-subsection-title"><?php esc_html_e( 'Switch WordPress version', 'wp-dashboard-management' ); ?></h3>
				<p class="wpdm-muted"><?php esc_html_e( 'Installs the selected core release directly — works for rollbacks too. Take a backup first on anything that matters.', 'wp-dashboard-management' ); ?></p>
				<div class="wpdm-core-switcher">
					<select id="wpdm-core-version">
						<?php foreach ( $core_versions as $release ) : ?>
							<option value="<?php echo esc_attr( $release['version'] ); ?>" <?php disabled( $release['version'], $system['wp_version'] ); ?>>
								<?php
								echo esc_html( $release['version'] );
								if ( $release['version'] === $system['wp_version'] ) {
									echo ' — ' . esc_html__( 'current', 'wp-dashboard-management' );
								} elseif ( 'latest' === $release['status'] ) {
									echo ' — ' . esc_html__( 'latest', 'wp-dashboard-management' );
								} elseif ( 'insecure' === $release['status'] ) {
									echo ' — ' . esc_html__( 'insecure', 'wp-dashboard-management' );
								}
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button button-primary" id="wpdm-install-core">
						<?php esc_html_e( 'Install version', 'wp-dashboard-management' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $features['security'] || $features['site_health'] ) : ?>
	<div class="wpdm-columns wpdm-columns-even">
		<!-- ===== Security health check ===== -->
		<?php if ( $features['security'] ) : ?>
		<div class="wpdm-panel">
			<div class="wpdm-panel-head">
				<h2><?php esc_html_e( 'Security Health Check', 'wp-dashboard-management' ); ?></h2>
				<span class="wpdm-badge <?php echo $security['passed'] === $security['total'] ? 'wpdm-badge-green' : 'wpdm-badge-orange'; ?>">
					<?php
					/* translators: 1: passed count, 2: total count */
					printf( esc_html__( '%1$d / %2$d passed', 'wp-dashboard-management' ), (int) $security['passed'], (int) $security['total'] );
					?>
				</span>
			</div>
			<ul class="wpdm-check-list">
				<?php foreach ( $security['checks'] as $check ) : ?>
					<li class="<?php echo $check['pass'] ? 'is-pass' : 'is-fail'; ?>">
						<span class="dashicons <?php echo $check['pass'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
						<span class="wpdm-check-body">
							<strong><?php echo esc_html( $check['label'] ); ?></strong>
							<?php if ( ! $check['pass'] ) : ?>
								<span class="wpdm-check-detail"><?php echo esc_html( $check['detail'] ); ?></span>
							<?php endif; ?>
						</span>
						<?php if ( ! $check['pass'] && $check['fixable'] ) { ?>
							<button type="button" class="button button-small wpdm-fix-btn" data-action="wpdm_security_fix" data-check="<?php echo esc_attr( $check['id'] ); ?>"><?php esc_html_e( 'Fix', 'wp-dashboard-management' ); ?></button>
						<?php } ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>

		<!-- ===== Site health summary ===== -->
		<?php if ( $features['site_health'] ) : ?>
		<div class="wpdm-panel">
			<div class="wpdm-panel-head">
				<h2><?php esc_html_e( 'Site Health', 'wp-dashboard-management' ); ?></h2>
				<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => WPDM_Admin::MENU_SLUG, 'wpdm-health' => 'refresh' ), admin_url( 'admin.php' ) ) ); ?>">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Re-run tests', 'wp-dashboard-management' ); ?>
				</a>
			</div>
			<div class="wpdm-health-counts">
				<span class="wpdm-badge wpdm-badge-green"><?php printf( esc_html__( '%d good', 'wp-dashboard-management' ), (int) $site_health['counts']['good'] ); ?></span>
				<span class="wpdm-badge wpdm-badge-orange"><?php printf( esc_html__( '%d recommended', 'wp-dashboard-management' ), (int) $site_health['counts']['recommended'] ); ?></span>
				<span class="wpdm-badge wpdm-severity-critical"><?php printf( esc_html__( '%d critical', 'wp-dashboard-management' ), (int) $site_health['counts']['critical'] ); ?></span>
				<span class="wpdm-muted">
					<?php
					/* translators: %s: human time diff */
					printf( esc_html__( 'checked %s ago', 'wp-dashboard-management' ), esc_html( human_time_diff( $site_health['checked_at'] ) ) );
					?>
				</span>
			</div>
			<?php if ( empty( $site_health['issues'] ) ) : ?>
				<div class="wpdm-empty"><p><?php esc_html_e( 'All Site Health tests pass. Nothing to fix!', 'wp-dashboard-management' ); ?></p></div>
			<?php else : ?>
				<ul class="wpdm-check-list">
					<?php foreach ( $site_health['issues'] as $issue ) : ?>
						<li class="<?php echo 'critical' === $issue['status'] ? 'is-fail' : 'is-warn'; ?>">
							<span class="dashicons <?php echo 'critical' === $issue['status'] ? 'dashicons-dismiss' : 'dashicons-warning'; ?>"></span>
							<span class="wpdm-check-body">
								<strong><?php echo esc_html( $issue['label'] ); ?></strong>
								<span class="wpdm-check-detail"><?php echo esc_html( wp_trim_words( $issue['description'], 30 ) ); ?></span>
							</span>
							<?php if ( ! empty( $issue['fixable'] ) ) { ?>
								<button type="button" class="button button-small wpdm-fix-btn" data-action="wpdm_site_health_fix" data-check="<?php echo esc_attr( $issue['test'] ); ?>"><?php esc_html_e( 'Fix', 'wp-dashboard-management' ); ?></button>
							<?php } ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- ===== Email reports ===== -->
	<?php if ( $features['email_reports'] ) : ?>
	<div class="wpdm-panel">
		<div class="wpdm-panel-head">
			<h2><?php esc_html_e( 'Email Update Reports', 'wp-dashboard-management' ); ?></h2>
			<button type="button" class="button" id="wpdm-send-report">
				<span class="dashicons dashicons-email-alt"></span>
				<?php esc_html_e( 'Send report now', 'wp-dashboard-management' ); ?>
			</button>
		</div>
		<p class="wpdm-muted">
			<?php esc_html_e( 'A styled email with the WordPress core version and the pending updates for active plugins and themes.', 'wp-dashboard-management' ); ?>
			<?php if ( $report_next ) : ?>
				<?php
				/* translators: %s: date/time of next report */
				printf( esc_html__( 'Next scheduled report: %s.', 'wp-dashboard-management' ), esc_html( wp_date( 'j F Y, H:i', $report_next ) ) );
				?>
			<?php endif; ?>
		</p>
		<div class="wpdm-report-form">
			<label for="wpdm-report-email"><?php esc_html_e( 'Send to', 'wp-dashboard-management' ); ?></label>
			<input type="email" id="wpdm-report-email" class="regular-text" value="<?php echo esc_attr( $report['email'] ); ?>" placeholder="reports@example.com" />
			<label for="wpdm-report-frequency"><?php esc_html_e( 'Frequency', 'wp-dashboard-management' ); ?></label>
			<select id="wpdm-report-frequency">
				<option value="disabled" <?php selected( $report['frequency'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'wp-dashboard-management' ); ?></option>
				<option value="daily" <?php selected( $report['frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'wp-dashboard-management' ); ?></option>
				<option value="weekly" <?php selected( $report['frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'wp-dashboard-management' ); ?></option>
				<option value="monthly" <?php selected( $report['frequency'], 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'wp-dashboard-management' ); ?></option>
			</select>
			<button type="button" class="button button-primary" id="wpdm-save-report"><?php esc_html_e( 'Save settings', 'wp-dashboard-management' ); ?></button>
		</div>
	</div>
	<?php endif; ?>

	<!-- ===== Database snapshots ===== -->
	<?php if ( $features['snapshots'] ) : ?>
	<div class="wpdm-panel">
		<div class="wpdm-panel-head">
			<h2><?php esc_html_e( 'Database Snapshots', 'wp-dashboard-management' ); ?></h2>
			<button type="button" class="button" id="wpdm-create-backup">
				<span class="dashicons dashicons-database-add"></span>
				<?php esc_html_e( 'Back up now', 'wp-dashboard-management' ); ?>
			</button>
		</div>
		<p class="wpdm-muted"><?php esc_html_e( 'A snapshot is taken automatically before every WordPress version switch. Restoring replaces all tables in the snapshot — restore the snapshot taken on a version together with installing that version for a clean rollback.', 'wp-dashboard-management' ); ?></p>

		<?php if ( empty( $snapshots ) ) : ?>
			<div class="wpdm-empty">
				<p><?php esc_html_e( 'No snapshots yet.', 'wp-dashboard-management' ); ?></p>
			</div>
		<?php else : ?>
			<table class="widefat striped wpdm-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Snapshot', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'Taken on', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'WordPress', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'Size', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wp-dashboard-management' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $snapshots as $snapshot ) : ?>
						<tr>
							<td><code><?php echo esc_html( $snapshot['file'] ); ?></code></td>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i', $snapshot['created'] ) ); ?></td>
							<td>
								<?php if ( $snapshot['wp_version'] ) : ?>
									<span class="wpdm-badge <?php echo $snapshot['wp_version'] === $system['wp_version'] ? 'wpdm-badge-green' : 'wpdm-badge-gray'; ?>">
										<?php echo esc_html( $snapshot['wp_version'] ); ?>
									</span>
								<?php else : ?>
									<span class="wpdm-muted">&mdash;</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( size_format( $snapshot['size'] ) ); ?></td>
							<td>
								<button type="button" class="button button-small wpdm-restore-backup-btn"
									data-file="<?php echo esc_attr( $snapshot['file'] ); ?>"
									data-wp="<?php echo esc_attr( $snapshot['wp_version'] ); ?>">
									<?php esc_html_e( 'Restore', 'wp-dashboard-management' ); ?>
								</button>
								<button type="button" class="button button-small wpdm-delete-backup-btn"
									data-file="<?php echo esc_attr( $snapshot['file'] ); ?>">
									<?php esc_html_e( 'Delete', 'wp-dashboard-management' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<?php endif; ?>

	<!-- ===== Activity log ===== -->
	<?php if ( $features['activity'] ) : ?>
	<div class="wpdm-panel">
		<div class="wpdm-panel-head">
			<h2><?php esc_html_e( 'Activity Log (Beqlic)', 'wp-dashboard-management' ); ?></h2>
			<?php if ( $activity_status['active'] ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=beqlic-activity-log' ) ); ?>">
					<?php esc_html_e( 'Open full log', 'wp-dashboard-management' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( ! $activity_status['installed'] ) : ?>
			<div class="wpdm-empty">
				<p><?php esc_html_e( 'Beqlic Activity Log is not installed.', 'wp-dashboard-management' ); ?></p>
				<?php if ( current_user_can( 'install_plugins' ) && '' !== WPDM_Activity_Log::repo_zip_url() ) : ?>
					<button type="button" class="button button-primary" id="wpdm-install-beqlic">
						<?php esc_html_e( 'Install now from GitHub', 'wp-dashboard-management' ); ?>
					</button>
				<?php else : ?>
					<p class="wpdm-muted"><?php esc_html_e( 'Set WPDM_BEQLIC_REPO_ZIP to the GitHub zip URL to enable one-click install.', 'wp-dashboard-management' ); ?></p>
				<?php endif; ?>
			</div>
		<?php elseif ( ! $activity_status['active'] ) : ?>
			<div class="wpdm-empty">
				<p><?php esc_html_e( 'Beqlic Activity Log is installed but not active.', 'wp-dashboard-management' ); ?></p>
				<button type="button" class="button button-primary" id="wpdm-activate-beqlic">
					<?php esc_html_e( 'Activate Beqlic Activity Log', 'wp-dashboard-management' ); ?>
				</button>
			</div>
		<?php elseif ( null === $activity_stats ) : ?>
			<div class="wpdm-empty">
				<p><?php esc_html_e( 'The activity log table was not found yet. It is created on plugin activation.', 'wp-dashboard-management' ); ?></p>
			</div>
		<?php else : ?>
			<div class="wpdm-activity-grid">
				<div class="wpdm-chart-box wpdm-chart-wide">
					<h3>
						<?php
						/* translators: %d: number of days */
						printf( esc_html__( 'Events per day (last %d days)', 'wp-dashboard-management' ), (int) $activity_stats['days'] );
						?>
					</h3>
					<canvas id="wpdm-chart-per-day" height="180"></canvas>
				</div>
				<div class="wpdm-chart-box">
					<h3><?php esc_html_e( 'By severity', 'wp-dashboard-management' ); ?></h3>
					<canvas id="wpdm-chart-severity" height="180"></canvas>
					<div class="wpdm-legend" id="wpdm-legend-severity"></div>
				</div>
				<div class="wpdm-chart-box">
					<h3><?php esc_html_e( 'Top actions', 'wp-dashboard-management' ); ?></h3>
					<canvas id="wpdm-chart-actions" height="180"></canvas>
					<div class="wpdm-legend" id="wpdm-legend-actions"></div>
				</div>
				<div class="wpdm-chart-box">
					<h3><?php esc_html_e( 'Most active users', 'wp-dashboard-management' ); ?></h3>
					<?php if ( empty( $activity_stats['top_users'] ) ) : ?>
						<p class="wpdm-muted"><?php esc_html_e( 'No user activity in this period.', 'wp-dashboard-management' ); ?></p>
					<?php else : ?>
						<ul class="wpdm-user-list">
							<?php
							$max_user_count = max( wp_list_pluck( $activity_stats['top_users'], 'c' ) );
							foreach ( $activity_stats['top_users'] as $user ) :
								$pct = $max_user_count > 0 ? round( ( (int) $user->c / $max_user_count ) * 100 ) : 0;
								?>
								<li>
									<span class="wpdm-user-name"><?php echo esc_html( $user->user_name ); ?></span>
									<span class="wpdm-user-bar"><span style="width:<?php echo esc_attr( $pct ); ?>%"></span></span>
									<span class="wpdm-user-count"><?php echo esc_html( $user->c ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>

			<h3 class="wpdm-recent-title"><?php esc_html_e( 'Latest events', 'wp-dashboard-management' ); ?></h3>
			<table class="widefat striped wpdm-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'User', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'Action', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'Severity', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'Object', 'wp-dashboard-management' ); ?></th>
						<th><?php esc_html_e( 'Message', 'wp-dashboard-management' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $activity_stats['recent'] ) ) : ?>
						<tr><td colspan="6" class="wpdm-muted"><?php esc_html_e( 'No events logged yet.', 'wp-dashboard-management' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $activity_stats['recent'] as $event ) : ?>
							<tr>
								<td><?php echo esc_html( $event->created_at ); ?></td>
								<td><?php echo esc_html( $event->user_name ); ?></td>
								<td><?php echo esc_html( $event->action ); ?></td>
								<td><span class="wpdm-badge wpdm-severity-<?php echo esc_attr( $event->severity ); ?>"><?php echo esc_html( $event->severity ); ?></span></td>
								<td><?php echo esc_html( trim( $event->object_type . ' ' . $event->object_title ) ); ?></td>
								<td class="wpdm-msg"><?php echo esc_html( $event->message ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<?php endif; ?>

	<!-- ===== PHP logs ===== -->
	<?php if ( $features['php_logs'] ) : ?>
	<div class="wpdm-panel">
		<div class="wpdm-panel-head">
			<h2><?php esc_html_e( 'PHP Error Log', 'wp-dashboard-management' ); ?></h2>
			<div class="wpdm-panel-actions">
				<span class="wpdm-source-tabs">
					<?php foreach ( $log_sources as $source_id => $source ) : ?>
						<a class="button button-small <?php echo $source_id === $log_source ? 'wpdm-source-active' : ''; ?>"
							href="<?php echo esc_url( add_query_arg( array( 'page' => WPDM_Admin::MENU_SLUG, 'log_source' => $source_id ), admin_url( 'admin.php' ) ) ); ?>">
							<?php echo esc_html( $source['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</span>
				<?php if ( $logs['exists'] && $logs['size'] > 0 ) : ?>
					<button type="button" class="button" id="wpdm-clear-log" data-source="<?php echo esc_attr( $log_source ); ?>">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Clear log', 'wp-dashboard-management' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>

		<p class="wpdm-muted wpdm-log-meta">
			<code><?php echo esc_html( $logs['path'] ); ?></code>
			<?php if ( $logs['exists'] ) : ?>
				&middot; <?php echo esc_html( size_format( $logs['size'] ) ); ?>
				<?php if ( $logs['truncated'] ) : ?>
					&middot; <?php esc_html_e( 'showing the newest entries only', 'wp-dashboard-management' ); ?>
				<?php endif; ?>
			<?php endif; ?>
		</p>

		<?php if ( ! $logs['exists'] ) : ?>
			<div class="wpdm-empty">
				<?php if ( 'wpdm' === $log_source ) : ?>
					<p><?php esc_html_e( 'No errors captured yet. The plugin logs PHP errors here automatically as they happen.', 'wp-dashboard-management' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'No log file found. Enable WP_DEBUG and WP_DEBUG_LOG in wp-config.php to start logging PHP errors.', 'wp-dashboard-management' ); ?></p>
				<?php endif; ?>
			</div>
		<?php elseif ( empty( $logs['entries'] ) ) : ?>
			<div class="wpdm-empty">
				<p><?php esc_html_e( 'The log file is empty. Nice and clean!', 'wp-dashboard-management' ); ?></p>
			</div>
		<?php else : ?>
			<div class="wpdm-log-filters">
				<button type="button" class="button button-small wpdm-log-filter is-active" data-level="all"><?php esc_html_e( 'All', 'wp-dashboard-management' ); ?></button>
				<button type="button" class="button button-small wpdm-log-filter" data-level="fatal"><?php printf( esc_html__( 'Fatal (%d)', 'wp-dashboard-management' ), (int) $logs['counts']['fatal'] ); ?></button>
				<button type="button" class="button button-small wpdm-log-filter" data-level="warning"><?php printf( esc_html__( 'Warnings (%d)', 'wp-dashboard-management' ), (int) $logs['counts']['warning'] ); ?></button>
				<button type="button" class="button button-small wpdm-log-filter" data-level="notice"><?php printf( esc_html__( 'Notices (%d)', 'wp-dashboard-management' ), (int) $logs['counts']['notice'] ); ?></button>
				<button type="button" class="button button-small wpdm-log-filter" data-level="deprecated"><?php printf( esc_html__( 'Deprecated (%d)', 'wp-dashboard-management' ), (int) $logs['counts']['deprecated'] ); ?></button>
			</div>
			<div class="wpdm-log-list">
				<?php foreach ( $logs['entries'] as $entry ) : ?>
					<div class="wpdm-log-entry wpdm-log-<?php echo esc_attr( $entry['level'] ); ?>" data-level="<?php echo esc_attr( $entry['level'] ); ?>">
						<div class="wpdm-log-entry-head">
							<span class="wpdm-badge wpdm-log-badge-<?php echo esc_attr( $entry['level'] ); ?>"><?php echo esc_html( $entry['label'] ); ?></span>
							<span class="wpdm-log-time"><?php echo esc_html( $entry['time'] ); ?></span>
						</div>
						<pre class="wpdm-log-message"><?php echo esc_html( $entry['message'] ); ?></pre>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
