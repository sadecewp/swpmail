<?php
/**
 * Alarm notifications page — Tabbed UX redesign.
 *
 * Layout:
 *  Top card  — Events & throttle (always visible)
 *  Tabs      — One per notification channel
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enabled_channels = (array) get_option( 'swpm_alarm_enabled_channels', array() );
$enabled_events   = (array) get_option( 'swpm_alarm_events', array( 'mail_failed', 'failover_triggered' ) );
$cooldown         = (int) get_option( 'swpm_alarm_cooldown', 300 );

// Indicate whether credentials are stored (don't expose actual values).
$has_slack_webhook   = '' !== swpm_decrypt( get_option( 'swpm_alarm_slack_webhook_enc', '' ) );
$has_discord_webhook = '' !== swpm_decrypt( get_option( 'swpm_alarm_discord_webhook_enc', '' ) );
$has_teams_webhook   = '' !== swpm_decrypt( get_option( 'swpm_alarm_teams_webhook_enc', '' ) );
$has_twilio_sid      = '' !== swpm_decrypt( get_option( 'swpm_alarm_twilio_sid_enc', '' ) );
$has_twilio_token    = '' !== swpm_decrypt( get_option( 'swpm_alarm_twilio_token_enc', '' ) );
$twilio_from         = get_option( 'swpm_alarm_twilio_from', '' );
$twilio_to           = get_option( 'swpm_alarm_twilio_to', '' );
$has_custom_webhook  = '' !== swpm_decrypt( get_option( 'swpm_alarm_custom_webhook_enc', '' ) );
$has_custom_secret   = '' !== swpm_decrypt( get_option( 'swpm_alarm_custom_secret_enc', '' ) );

// Helper: build status dot markup for each channel tab.
function swpm_alarm_tab_dot( $channel, $enabled_channels ) {
	if ( in_array( $channel, $enabled_channels, true ) ) {
		return '<span class="swpm-ms-tab-dot swpm-ms-tab-dot--success"></span>';
	}
	return '';
}
?>

<div class="swpm-wrap">

	<!-- Page Header -->
	<div class="swpm-page-header">
		<div>
			<h1><span class="dashicons dashicons-bell"></span> <?php esc_html_e( 'Alarm Notifications', 'swpmail' ); ?></h1>
			<p class="swpm-page-subtitle"><?php esc_html_e( 'Get instant alerts when emails fail to send. Configure notification channels below.', 'swpmail' ); ?></p>
		</div>
	</div>

	<div id="swpm-alarm-message" class="swpm-alert" style="display:none;"></div>

	<!-- ── Global Settings (always visible, above tabs) ─────────────── -->
	<div class="swpm-card swpm-alarm-global" style="margin-bottom: 24px;">
		<h4 class="swpm-ms-config-title">
			<span class="dashicons dashicons-admin-settings"></span>
			<?php esc_html_e( 'Events & Throttle', 'swpmail' ); ?>
		</h4>
		<div class="swpm-ms-field-grid">

			<div class="swpm-ms-field">
				<label><?php esc_html_e( 'Monitored Events', 'swpmail' ); ?></label>
				<div class="swpm-settings-checks swpm-settings-checks--compact">
					<div class="swpm-settings-check">
						<label class="swpm-settings-check__label">
							<input type="checkbox" name="swpm_alarm_event[]" value="mail_failed"
								<?php checked( in_array( 'mail_failed', $enabled_events, true ) ); ?>>
							<div class="swpm-settings-check__content">
								<span class="swpm-settings-check__title"><?php esc_html_e( 'Email delivery failure', 'swpmail' ); ?></span>
							</div>
						</label>
					</div>
					<div class="swpm-settings-check">
						<label class="swpm-settings-check__label">
							<input type="checkbox" name="swpm_alarm_event[]" value="failover_triggered"
								<?php checked( in_array( 'failover_triggered', $enabled_events, true ) ); ?>>
							<div class="swpm-settings-check__content">
								<span class="swpm-settings-check__title"><?php esc_html_e( 'Provider failover activated', 'swpmail' ); ?></span>
							</div>
						</label>
					</div>
				</div>
			</div>

			<div class="swpm-ms-field">
				<label for="swpm-alarm-cooldown"><?php esc_html_e( 'Throttle (seconds)', 'swpmail' ); ?></label>
				<input type="number" id="swpm-alarm-cooldown" name="swpm_alarm_cooldown"
					value="<?php echo esc_attr( $cooldown ); ?>" min="0" max="86400" step="1" class="small-text">
				<p class="description"><?php esc_html_e( 'Minimum seconds between repeated notifications for the same event. 0 = no throttle.', 'swpmail' ); ?></p>
			</div>

		</div>
	</div>

	<!-- ── Channel Tabs ─────────────────────────────────────────────── -->
	<div class="swpm-ms-tabs" role="tablist">
		<button class="swpm-ms-tab active" role="tab" aria-selected="true"  data-tab="alarm-slack"   type="button">
			<span class="dashicons dashicons-format-chat"></span> Slack
			<?php echo swpm_alarm_tab_dot( 'slack', $enabled_channels ); // phpcs:ignore ?>
		</button>
		<button class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="alarm-discord" type="button">
			<span class="dashicons dashicons-share"></span> Discord
			<?php echo swpm_alarm_tab_dot( 'discord', $enabled_channels ); // phpcs:ignore ?>
		</button>
		<button class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="alarm-teams"   type="button">
			<span class="dashicons dashicons-groups"></span> Microsoft Teams
			<?php echo swpm_alarm_tab_dot( 'teams', $enabled_channels ); // phpcs:ignore ?>
		</button>
		<button class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="alarm-twilio"  type="button">
			<span class="dashicons dashicons-smartphone"></span> Twilio SMS
			<?php echo swpm_alarm_tab_dot( 'twilio', $enabled_channels ); // phpcs:ignore ?>
		</button>
		<button class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="alarm-custom"  type="button">
			<span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Custom Webhook', 'swpmail' ); ?>
			<?php echo swpm_alarm_tab_dot( 'custom', $enabled_channels ); // phpcs:ignore ?>
		</button>
	</div>

	<!-- ── Slack ────────────────────────────────────────────────────── -->
	<div class="swpm-ms-panel" id="swpm-tab-alarm-slack" role="tabpanel">
		<div class="swpm-card swpm-alarm-channel" data-channel="slack">
			<div class="swpm-alarm-channel__header">
				<h4 class="swpm-ms-config-title" style="margin:0;">
					<span class="dashicons dashicons-format-chat"></span> Slack
				</h4>
				<label class="swpm-toggle">
					<input type="checkbox" name="swpm_alarm_channel[]" value="slack"
						<?php checked( in_array( 'slack', $enabled_channels, true ) ); ?>>
					<span class="swpm-toggle__slider"></span>
				</label>
			</div>
			<div class="swpm-alarm-channel__body">
				<div class="swpm-ms-field-grid">
					<div class="swpm-ms-field swpm-ms-field--full">
						<label for="swpm-alarm-slack-webhook"><?php esc_html_e( 'Webhook URL', 'swpmail' ); ?></label>
						<input type="password" id="swpm-alarm-slack-webhook" name="slack_webhook"
							value="" class="regular-text" placeholder="https://hooks.slack.com/services/..." autocomplete="new-password">
						<?php if ( $has_slack_webhook ) : ?>
							<p class="description"><?php esc_html_e( 'A webhook URL is saved. Leave blank to keep the current value.', 'swpmail' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<div class="swpm-ms-save-bar">
					<button type="button" class="swpm-btn swpm-btn--secondary swpm-btn--sm swpm-alarm-test-btn" data-channel="slack">
						<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Send Test', 'swpmail' ); ?>
					</button>
					<span class="swpm-alarm-test-status" data-channel="slack"></span>
					<button type="button" id="swpm-alarm-save" class="swpm-btn swpm-btn--primary" style="margin-left:auto;">
						<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Alarm Settings', 'swpmail' ); ?>
					</button>
					<span class="spinner" id="swpm-alarm-spinner"></span>
				</div>
			</div>
		</div>
	</div>

	<!-- ── Discord ──────────────────────────────────────────────────── -->
	<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-alarm-discord" role="tabpanel">
		<div class="swpm-card swpm-alarm-channel" data-channel="discord">
			<div class="swpm-alarm-channel__header">
				<h4 class="swpm-ms-config-title" style="margin:0;">
					<span class="dashicons dashicons-share"></span> Discord
				</h4>
				<label class="swpm-toggle">
					<input type="checkbox" name="swpm_alarm_channel[]" value="discord"
						<?php checked( in_array( 'discord', $enabled_channels, true ) ); ?>>
					<span class="swpm-toggle__slider"></span>
				</label>
			</div>
			<div class="swpm-alarm-channel__body">
				<div class="swpm-ms-field-grid">
					<div class="swpm-ms-field swpm-ms-field--full">
						<label for="swpm-alarm-discord-webhook"><?php esc_html_e( 'Webhook URL', 'swpmail' ); ?></label>
						<input type="password" id="swpm-alarm-discord-webhook" name="discord_webhook"
							value="" class="regular-text" placeholder="https://discord.com/api/webhooks/..." autocomplete="new-password">
						<?php if ( $has_discord_webhook ) : ?>
							<p class="description"><?php esc_html_e( 'A webhook URL is saved. Leave blank to keep the current value.', 'swpmail' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<div class="swpm-ms-save-bar">
					<button type="button" class="swpm-btn swpm-btn--secondary swpm-btn--sm swpm-alarm-test-btn" data-channel="discord">
						<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Send Test', 'swpmail' ); ?>
					</button>
					<span class="swpm-alarm-test-status" data-channel="discord"></span>
					<button type="button" class="swpm-btn swpm-btn--primary swpm-alarm-save-btn" style="margin-left:auto;">
						<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Alarm Settings', 'swpmail' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- ── Microsoft Teams ──────────────────────────────────────────── -->
	<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-alarm-teams" role="tabpanel">
		<div class="swpm-card swpm-alarm-channel" data-channel="teams">
			<div class="swpm-alarm-channel__header">
				<h4 class="swpm-ms-config-title" style="margin:0;">
					<span class="dashicons dashicons-groups"></span> Microsoft Teams
				</h4>
				<label class="swpm-toggle">
					<input type="checkbox" name="swpm_alarm_channel[]" value="teams"
						<?php checked( in_array( 'teams', $enabled_channels, true ) ); ?>>
					<span class="swpm-toggle__slider"></span>
				</label>
			</div>
			<div class="swpm-alarm-channel__body">
				<div class="swpm-ms-field-grid">
					<div class="swpm-ms-field swpm-ms-field--full">
						<label for="swpm-alarm-teams-webhook"><?php esc_html_e( 'Webhook URL', 'swpmail' ); ?></label>
						<input type="password" id="swpm-alarm-teams-webhook" name="teams_webhook"
							value="" class="regular-text" placeholder="https://outlook.office.com/webhook/..." autocomplete="new-password">
						<?php if ( $has_teams_webhook ) : ?>
							<p class="description"><?php esc_html_e( 'A webhook URL is saved. Leave blank to keep the current value.', 'swpmail' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<div class="swpm-ms-save-bar">
					<button type="button" class="swpm-btn swpm-btn--secondary swpm-btn--sm swpm-alarm-test-btn" data-channel="teams">
						<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Send Test', 'swpmail' ); ?>
					</button>
					<span class="swpm-alarm-test-status" data-channel="teams"></span>
					<button type="button" class="swpm-btn swpm-btn--primary swpm-alarm-save-btn" style="margin-left:auto;">
						<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Alarm Settings', 'swpmail' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- ── Twilio SMS ────────────────────────────────────────────────── -->
	<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-alarm-twilio" role="tabpanel">
		<div class="swpm-card swpm-alarm-channel" data-channel="twilio">
			<div class="swpm-alarm-channel__header">
				<h4 class="swpm-ms-config-title" style="margin:0;">
					<span class="dashicons dashicons-smartphone"></span> Twilio SMS
				</h4>
				<label class="swpm-toggle">
					<input type="checkbox" name="swpm_alarm_channel[]" value="twilio"
						<?php checked( in_array( 'twilio', $enabled_channels, true ) ); ?>>
					<span class="swpm-toggle__slider"></span>
				</label>
			</div>
			<div class="swpm-alarm-channel__body">
				<div class="swpm-ms-field-grid">
					<div class="swpm-ms-field">
						<label for="swpm-alarm-twilio-sid"><?php esc_html_e( 'Account SID', 'swpmail' ); ?></label>
						<input type="password" id="swpm-alarm-twilio-sid" name="twilio_sid"
							value="" class="regular-text" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxx" autocomplete="new-password">
						<?php if ( $has_twilio_sid ) : ?>
							<p class="description"><?php esc_html_e( 'An Account SID is saved. Leave blank to keep.', 'swpmail' ); ?></p>
						<?php endif; ?>
					</div>
					<div class="swpm-ms-field">
						<label for="swpm-alarm-twilio-token"><?php esc_html_e( 'Auth Token', 'swpmail' ); ?></label>
						<input type="password" id="swpm-alarm-twilio-token" name="twilio_token"
							value="" class="regular-text" autocomplete="new-password">
						<?php if ( $has_twilio_token ) : ?>
							<p class="description"><?php esc_html_e( 'An Auth Token is saved. Leave blank to keep.', 'swpmail' ); ?></p>
						<?php endif; ?>
					</div>
					<div class="swpm-ms-field">
						<label for="swpm-alarm-twilio-from"><?php esc_html_e( 'From Number', 'swpmail' ); ?></label>
						<input type="tel" id="swpm-alarm-twilio-from" name="twilio_from"
							value="<?php echo esc_attr( $twilio_from ); ?>" class="regular-text" placeholder="+1234567890">
					</div>
					<div class="swpm-ms-field">
						<label for="swpm-alarm-twilio-to"><?php esc_html_e( 'To Number', 'swpmail' ); ?></label>
						<input type="tel" id="swpm-alarm-twilio-to" name="twilio_to"
							value="<?php echo esc_attr( $twilio_to ); ?>" class="regular-text" placeholder="+1234567890">
					</div>
				</div>
				<div class="swpm-ms-save-bar">
					<button type="button" class="swpm-btn swpm-btn--secondary swpm-btn--sm swpm-alarm-test-btn" data-channel="twilio">
						<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Send Test', 'swpmail' ); ?>
					</button>
					<span class="swpm-alarm-test-status" data-channel="twilio"></span>
					<button type="button" class="swpm-btn swpm-btn--primary swpm-alarm-save-btn" style="margin-left:auto;">
						<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Alarm Settings', 'swpmail' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- ── Custom Webhook ───────────────────────────────────────────── -->
	<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-alarm-custom" role="tabpanel">
		<div class="swpm-card swpm-alarm-channel" data-channel="custom">
			<div class="swpm-alarm-channel__header">
				<h4 class="swpm-ms-config-title" style="margin:0;">
					<span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Custom Webhook', 'swpmail' ); ?>
				</h4>
				<label class="swpm-toggle">
					<input type="checkbox" name="swpm_alarm_channel[]" value="custom"
						<?php checked( in_array( 'custom', $enabled_channels, true ) ); ?>>
					<span class="swpm-toggle__slider"></span>
				</label>
			</div>
			<div class="swpm-alarm-channel__body">
				<div class="swpm-ms-field-grid">
					<div class="swpm-ms-field swpm-ms-field--full">
						<label for="swpm-alarm-custom-webhook"><?php esc_html_e( 'Webhook URL', 'swpmail' ); ?></label>
						<input type="password" id="swpm-alarm-custom-webhook" name="custom_webhook"
							value="" class="regular-text" placeholder="https://example.com/webhook" autocomplete="new-password">
						<?php if ( $has_custom_webhook ) : ?>
							<p class="description"><?php esc_html_e( 'A webhook URL is saved. Leave blank to keep the current value.', 'swpmail' ); ?></p>
						<?php endif; ?>
					</div>
					<div class="swpm-ms-field swpm-ms-field--full">
						<label for="swpm-alarm-custom-secret"><?php esc_html_e( 'Signing Secret', 'swpmail' ); ?></label>
						<input type="password" id="swpm-alarm-custom-secret" name="custom_secret"
							value="" class="regular-text" autocomplete="new-password">
						<?php if ( $has_custom_secret ) : ?>
							<p class="description"><?php esc_html_e( 'A signing secret is saved. Leave blank to keep the current value.', 'swpmail' ); ?></p>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Optional. Generates an HMAC-SHA256 signature in the X-SWPMail-Signature header.', 'swpmail' ); ?></p>
					</div>
				</div>
				<div class="swpm-ms-save-bar">
					<button type="button" class="swpm-btn swpm-btn--secondary swpm-btn--sm swpm-alarm-test-btn" data-channel="custom">
						<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Send Test', 'swpmail' ); ?>
					</button>
					<span class="swpm-alarm-test-status" data-channel="custom"></span>
					<button type="button" class="swpm-btn swpm-btn--primary swpm-alarm-save-btn" style="margin-left:auto;">
						<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Alarm Settings', 'swpmail' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

</div>

<script>
(function() {
	/* ── Tab switching ─────────────────────────────────────── */
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

	/* ── Wire all save buttons to the single #swpm-alarm-save handler ── */
	document.querySelectorAll('.swpm-alarm-save-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			document.getElementById('swpm-alarm-save').click();
		});
	});
})();
</script>
