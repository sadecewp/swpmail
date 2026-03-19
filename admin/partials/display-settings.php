<?php
/**
 * General settings page — Tabbed UX redesign.
 *
 * Tabs:
 *  1 — General      : wp_mail override, failure notify, data deletion
 *  2 — Subscription : double opt-in, form title, frequency choice
 *  3 — Privacy      : GDPR checkbox
 *  4 — Schedule     : daily/weekly digest times
 *  5 — Tracking     : open + click tracking
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="swpm-wrap">

	<!-- Page Header -->
	<div class="swpm-page-header">
		<div>
			<h1><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Settings', 'swpmail' ); ?></h1>
			<p class="swpm-page-subtitle"><?php esc_html_e( 'Configure general plugin behaviour, subscription options, and scheduling.', 'swpmail' ); ?></p>
		</div>
	</div>

	<!-- Tab Navigation -->
	<div class="swpm-ms-tabs" role="tablist">
		<button class="swpm-ms-tab active" role="tab" aria-selected="true"  data-tab="general"      type="button">
			<span class="dashicons dashicons-admin-tools"></span>
			<?php esc_html_e( 'General', 'swpmail' ); ?>
		</button>
		<button class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="subscription"  type="button">
			<span class="dashicons dashicons-forms"></span>
			<?php esc_html_e( 'Subscription', 'swpmail' ); ?>
		</button>
		<button class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="privacy"       type="button">
			<span class="dashicons dashicons-shield"></span>
			<?php esc_html_e( 'Privacy & GDPR', 'swpmail' ); ?>
		</button>
		<button class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="schedule"      type="button">
			<span class="dashicons dashicons-clock"></span>
			<?php esc_html_e( 'Digest Schedule', 'swpmail' ); ?>
		</button>
		<button class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="tracking"      type="button">
			<span class="dashicons dashicons-chart-area"></span>
			<?php esc_html_e( 'Tracking', 'swpmail' ); ?>
		</button>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'swpm_general_settings_group' ); ?>

		<!-- ═══════════════════════════════ TAB 1 — GENERAL ═══════════════════════════════ -->
		<div class="swpm-ms-panel" id="swpm-tab-general" role="tabpanel">
			<div class="swpm-card">
				<div class="swpm-settings-checks">

					<div class="swpm-settings-check">
						<label class="swpm-settings-check__label">
							<input type="checkbox" name="swpm_override_wp_mail" value="1" <?php checked( get_option( 'swpm_override_wp_mail', true ) ); ?>>
							<div class="swpm-settings-check__content">
								<span class="swpm-settings-check__title"><?php esc_html_e( 'Override wp_mail()', 'swpmail' ); ?></span>
								<span class="swpm-settings-check__desc"><?php esc_html_e( 'Route all WordPress emails through the configured provider.', 'swpmail' ); ?></span>
							</div>
						</label>
					</div>

					<div class="swpm-settings-check">
						<label class="swpm-settings-check__label">
							<input type="checkbox" name="swpm_notify_admin_on_failure" value="1" <?php checked( get_option( 'swpm_notify_admin_on_failure', true ) ); ?>>
							<div class="swpm-settings-check__content">
								<span class="swpm-settings-check__title"><?php esc_html_e( 'Notify on Failure', 'swpmail' ); ?></span>
								<span class="swpm-settings-check__desc"><?php esc_html_e( 'Send admin notification when mail delivery fails.', 'swpmail' ); ?></span>
							</div>
						</label>
					</div>

					<div class="swpm-settings-check swpm-settings-check--danger">
						<label class="swpm-settings-check__label">
							<input type="checkbox" name="swpm_delete_data_on_uninstall" value="1" <?php checked( get_option( 'swpm_delete_data_on_uninstall', false ) ); ?>>
							<div class="swpm-settings-check__content">
								<span class="swpm-settings-check__title"><?php esc_html_e( 'Delete Data on Uninstall', 'swpmail' ); ?></span>
								<span class="swpm-settings-check__desc swpm-settings-check__desc--danger"><?php esc_html_e( 'Permanently removes all tables, options, and cron jobs when the plugin is deleted. This is irreversible.', 'swpmail' ); ?></span>
							</div>
						</label>
					</div>

				</div>
				<div class="swpm-ms-save-bar">
					<button type="submit" name="submit" class="swpm-btn swpm-btn--primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Settings', 'swpmail' ); ?></button>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════ TAB 2 — SUBSCRIPTION ═══════════════════════════════ -->
		<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-subscription" role="tabpanel">
			<div class="swpm-card">
				<div class="swpm-ms-field-grid">

					<div class="swpm-ms-field swpm-ms-field--full">
						<label for="swpm_form_title"><?php esc_html_e( 'Form Title', 'swpmail' ); ?></label>
						<input type="text" id="swpm_form_title" name="swpm_form_title"
							value="<?php echo esc_attr( get_option( 'swpm_form_title', __( 'Subscribe to Newsletter', 'swpmail' ) ) ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'Subscribe to Newsletter', 'swpmail' ); ?>">
						<p class="description"><?php esc_html_e( 'Displayed at the top of the subscription widget.', 'swpmail' ); ?></p>
					</div>

				</div>

				<div class="swpm-settings-checks" style="margin-top: 20px;">

					<div class="swpm-settings-check">
						<label class="swpm-settings-check__label">
							<input type="checkbox" name="swpm_double_opt_in" value="1" <?php checked( get_option( 'swpm_double_opt_in', true ) ); ?>>
							<div class="swpm-settings-check__content">
								<span class="swpm-settings-check__title"><?php esc_html_e( 'Double Opt-In', 'swpmail' ); ?></span>
								<span class="swpm-settings-check__desc"><?php esc_html_e( 'Require email confirmation before activating a new subscription.', 'swpmail' ); ?></span>
							</div>
						</label>
					</div>

					<div class="swpm-settings-check">
						<label class="swpm-settings-check__label">
							<input type="checkbox" name="swpm_show_frequency_choice" value="1" <?php checked( get_option( 'swpm_show_frequency_choice', true ) ); ?>>
							<div class="swpm-settings-check__content">
								<span class="swpm-settings-check__title"><?php esc_html_e( 'Show Frequency Choice', 'swpmail' ); ?></span>
								<span class="swpm-settings-check__desc"><?php esc_html_e( 'Allow subscribers to choose instant, daily, or weekly delivery.', 'swpmail' ); ?></span>
							</div>
						</label>
					</div>

				</div>

				<div class="swpm-ms-save-bar">
					<button type="submit" name="submit" class="swpm-btn swpm-btn--primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Settings', 'swpmail' ); ?></button>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════ TAB 3 — PRIVACY ═══════════════════════════════ -->
		<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-privacy" role="tabpanel">
			<div class="swpm-card">
				<div class="swpm-settings-checks">

					<div class="swpm-settings-check">
						<label class="swpm-settings-check__label">
							<input type="checkbox" name="swpm_gdpr_checkbox" value="1" <?php checked( get_option( 'swpm_gdpr_checkbox', true ) ); ?>>
							<div class="swpm-settings-check__content">
								<span class="swpm-settings-check__title"><?php esc_html_e( 'GDPR Consent Checkbox', 'swpmail' ); ?></span>
								<span class="swpm-settings-check__desc"><?php esc_html_e( 'Show privacy policy consent checkbox on the subscribe form.', 'swpmail' ); ?></span>
							</div>
						</label>
					</div>

				</div>

				<?php if ( get_privacy_policy_url() ) : ?>
					<div class="swpm-ms-config-notice" style="margin-top: 16px;">
						<span class="dashicons dashicons-info-outline"></span>
						<?php
						printf(
							/* translators: %s: privacy policy URL */
							esc_html__( 'Privacy policy page: %s', 'swpmail' ),
							'<a href="' . esc_url( get_privacy_policy_url() ) . '" target="_blank">' . esc_html( get_privacy_policy_url() ) . '</a>'
						);
						?>
					</div>
				<?php endif; ?>

				<div class="swpm-ms-save-bar">
					<button type="submit" name="submit" class="swpm-btn swpm-btn--primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Settings', 'swpmail' ); ?></button>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════ TAB 4 — SCHEDULE ═══════════════════════════════ -->
		<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-schedule" role="tabpanel">
			<div class="swpm-card">

				<div class="swpm-ms-config-notice" style="margin-bottom: 24px;">
					<span class="dashicons dashicons-info-outline"></span>
					<?php esc_html_e( 'Subscribers who choose "daily" or "weekly" frequency receive a single bundled email at the configured time instead of individual notifications.', 'swpmail' ); ?>
				</div>

				<div class="swpm-ms-field-grid">

					<div class="swpm-ms-field">
						<label for="swpm_daily_send_hour"><?php esc_html_e( 'Daily Digest Hour', 'swpmail' ); ?></label>
						<select id="swpm_daily_send_hour" name="swpm_daily_send_hour">
							<?php for ( $h = 0; $h < 24; $h++ ) : ?>
								<option value="<?php echo esc_attr( $h ); ?>" <?php selected( (int) get_option( 'swpm_daily_send_hour', 9 ), $h ); ?>>
									<?php echo esc_html( sprintf( '%02d:00', $h ) ); ?>
								</option>
							<?php endfor; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Server time zone.', 'swpmail' ); ?></p>
					</div>

					<div class="swpm-ms-field">
						<label for="swpm_weekly_send_day"><?php esc_html_e( 'Weekly Digest Day', 'swpmail' ); ?></label>
						<?php
						$days        = array(
							'monday'    => __( 'Monday', 'swpmail' ),
							'tuesday'   => __( 'Tuesday', 'swpmail' ),
							'wednesday' => __( 'Wednesday', 'swpmail' ),
							'thursday'  => __( 'Thursday', 'swpmail' ),
							'friday'    => __( 'Friday', 'swpmail' ),
							'saturday'  => __( 'Saturday', 'swpmail' ),
							'sunday'    => __( 'Sunday', 'swpmail' ),
						);
						$current_day = get_option( 'swpm_weekly_send_day', 'monday' );
						?>
						<select id="swpm_weekly_send_day" name="swpm_weekly_send_day">
							<?php foreach ( $days as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_day, $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

				</div>

				<div class="swpm-ms-save-bar">
					<button type="submit" name="submit" class="swpm-btn swpm-btn--primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Settings', 'swpmail' ); ?></button>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════ TAB 5 — TRACKING ═══════════════════════════════ -->
		<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-tracking" role="tabpanel">
			<div class="swpm-card">
				<div class="swpm-settings-checks">

					<div class="swpm-settings-check">
						<label class="swpm-settings-check__label">
							<input type="checkbox" name="swpm_enable_open_tracking" value="1" <?php checked( get_option( 'swpm_enable_open_tracking', true ) ); ?>>
							<div class="swpm-settings-check__content">
								<span class="swpm-settings-check__title"><?php esc_html_e( 'Open Tracking', 'swpmail' ); ?></span>
								<span class="swpm-settings-check__desc"><?php esc_html_e( 'Embed an invisible tracking pixel to detect when emails are opened.', 'swpmail' ); ?></span>
							</div>
						</label>
					</div>

					<div class="swpm-settings-check">
						<label class="swpm-settings-check__label">
							<input type="checkbox" name="swpm_enable_click_tracking" value="1" <?php checked( get_option( 'swpm_enable_click_tracking', true ) ); ?>>
							<div class="swpm-settings-check__content">
								<span class="swpm-settings-check__title"><?php esc_html_e( 'Click Tracking', 'swpmail' ); ?></span>
								<span class="swpm-settings-check__desc"><?php esc_html_e( 'Rewrite links through a redirect proxy to track clicks.', 'swpmail' ); ?></span>
							</div>
						</label>
					</div>

				</div>

				<div class="swpm-ms-save-bar">
					<button type="submit" name="submit" class="swpm-btn swpm-btn--primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Settings', 'swpmail' ); ?></button>
				</div>
			</div>
		</div>

	</form>

</div>

<script>
(function() {
	var tabs   = document.querySelectorAll('.swpm-ms-tab');
	var panels = document.querySelectorAll('.swpm-ms-panel');
	tabs.forEach(function(tab) {
		tab.addEventListener('click', function() {
			var target = this.dataset.tab;
			tabs.forEach(function(t) { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
			panels.forEach(function(p) { p.classList.add('swpm-ms-panel--hidden'); });
			this.classList.add('active');
			this.setAttribute('aria-selected', 'true');
			var panel = document.getElementById('swpm-tab-' + target);
			if (panel) { panel.classList.remove('swpm-ms-panel--hidden'); }
		});
	});
})();
</script>
