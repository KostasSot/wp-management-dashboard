<?php
/**
 * Setup wizard template.
 *
 * Variables provided by WPDM_Wizard::render(): $features, $settings
 *
 * @package WP_Dashboard_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpdm_groups = array(
	'monitoring' => array(
		'title' => __( 'Monitoring & Maintenance', 'wp-dashboard-management' ),
		'intro' => __( 'Everyday tools for keeping an eye on the site.', 'wp-dashboard-management' ),
	),
	'power'      => array(
		'title' => __( 'Power Tools', 'wp-dashboard-management' ),
		'intro' => __( 'Heavier tools meant for development and staging sites. Skip these on production websites.', 'wp-dashboard-management' ),
	),
);
?>
<div class="wrap wpdm-wizard-wrap">
	<div class="wpdm-wizard">
		<div class="wpdm-wizard-progress">
			<div class="wpdm-wizard-dot is-active" data-step="1"><span>1</span><em><?php esc_html_e( 'Welcome', 'wp-dashboard-management' ); ?></em></div>
			<div class="wpdm-wizard-line"></div>
			<div class="wpdm-wizard-dot" data-step="2"><span>2</span><em><?php esc_html_e( 'Monitoring', 'wp-dashboard-management' ); ?></em></div>
			<div class="wpdm-wizard-line"></div>
			<div class="wpdm-wizard-dot" data-step="3"><span>3</span><em><?php esc_html_e( 'Power tools', 'wp-dashboard-management' ); ?></em></div>
			<div class="wpdm-wizard-line"></div>
			<div class="wpdm-wizard-dot" data-step="4"><span>4</span><em><?php esc_html_e( 'Finish', 'wp-dashboard-management' ); ?></em></div>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpdm_save_wizard" />
			<?php wp_nonce_field( 'wpdm_save_wizard' ); ?>

			<!-- Step 1: welcome -->
			<section class="wpdm-wizard-step is-active" data-step="1">
				<h1><?php esc_html_e( 'Welcome to WP Dashboard Management', 'wp-dashboard-management' ); ?></h1>
				<p class="wpdm-wizard-lead">
					<?php esc_html_e( 'This short wizard lets you choose which modules appear on the Management dashboard for this website. Disabled modules are fully switched off — hidden from the page and blocked on the server.', 'wp-dashboard-management' ); ?>
				</p>
				<p><?php esc_html_e( 'You can re-run this wizard at any time from Management → Setup.', 'wp-dashboard-management' ); ?></p>
				<div class="wpdm-wizard-actions">
					<span></span>
					<button type="button" class="button button-primary button-hero wpdm-wizard-next"><?php esc_html_e( 'Let\'s go', 'wp-dashboard-management' ); ?> →</button>
				</div>
			</section>

			<!-- Steps 2 & 3: feature groups -->
			<?php $wpdm_step = 2; ?>
			<?php foreach ( $wpdm_groups as $wpdm_group_key => $wpdm_group ) : ?>
				<section class="wpdm-wizard-step" data-step="<?php echo esc_attr( $wpdm_step ); ?>">
					<h1><?php echo esc_html( $wpdm_group['title'] ); ?></h1>
					<p class="wpdm-wizard-lead"><?php echo esc_html( $wpdm_group['intro'] ); ?></p>

					<div class="wpdm-wizard-cards">
						<?php foreach ( $features as $wpdm_key => $wpdm_feature ) : ?>
							<?php
							if ( $wpdm_feature['group'] !== $wpdm_group_key ) {
								continue;
							}
							$wpdm_checked = ! empty( $settings['features'][ $wpdm_key ] );
							?>
							<label class="wpdm-wizard-card <?php echo $wpdm_checked ? 'is-checked' : ''; ?>">
								<input type="checkbox" name="features[]" value="<?php echo esc_attr( $wpdm_key ); ?>" <?php checked( $wpdm_checked ); ?> />
								<span class="wpdm-wizard-card-body">
									<strong><?php echo esc_html( $wpdm_feature['label'] ); ?></strong>
									<span><?php echo esc_html( $wpdm_feature['description'] ); ?></span>
								</span>
								<span class="wpdm-wizard-check dashicons dashicons-yes"></span>
							</label>
						<?php endforeach; ?>
					</div>

					<div class="wpdm-wizard-actions">
						<button type="button" class="button wpdm-wizard-prev">← <?php esc_html_e( 'Back', 'wp-dashboard-management' ); ?></button>
						<button type="button" class="button button-primary wpdm-wizard-next"><?php esc_html_e( 'Continue', 'wp-dashboard-management' ); ?> →</button>
					</div>
				</section>
				<?php $wpdm_step++; ?>
			<?php endforeach; ?>

			<!-- Step 4: review -->
			<section class="wpdm-wizard-step" data-step="4">
				<h1><?php esc_html_e( 'Review & finish', 'wp-dashboard-management' ); ?></h1>
				<p class="wpdm-wizard-lead"><?php esc_html_e( 'These modules will be enabled on the dashboard:', 'wp-dashboard-management' ); ?></p>
				<ul class="wpdm-wizard-summary" id="wpdm-wizard-summary"></ul>
				<p class="wpdm-muted" id="wpdm-wizard-summary-empty" hidden><?php esc_html_e( 'No modules selected — the dashboard will only show system information and the environment panel.', 'wp-dashboard-management' ); ?></p>
				<div class="wpdm-wizard-actions">
					<button type="button" class="button wpdm-wizard-prev">← <?php esc_html_e( 'Back', 'wp-dashboard-management' ); ?></button>
					<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save & open dashboard', 'wp-dashboard-management' ); ?></button>
				</div>
			</section>
		</form>
	</div>
</div>
