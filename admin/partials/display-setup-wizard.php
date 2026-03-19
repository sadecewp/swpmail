<?php
/**
 * Setup Wizard template.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$img_url = SWPM_PLUGIN_URL . 'public/img/';

// Add body class for CSS targeting.
add_filter(
	'admin_body_class',
	function ( $classes ) {
		return $classes . ' swpm-wizard-page';
	}
);
?>
<div class="swpm-wizard-wrap">

	<!-- ─── Header ─── -->
	<div class="swpm-wizard-header">
		<div class="swpm-wizard-logo">
			<span class="dashicons dashicons-email"></span>
			<span>SWPMail</span>
		</div>

		<div class="swpm-wizard-steps">
			<div class="swpm-wizard-step active" data-step="1">
				<span class="swpm-wizard-step__number"><span>1</span></span>
				<span class="swpm-wizard-step__label"><?php esc_html_e( 'Choose Mailer', 'swpmail' ); ?></span>
			</div>
			<div class="swpm-wizard-step-connector" data-after="1"></div>
			<div class="swpm-wizard-step" data-step="2">
				<span class="swpm-wizard-step__number"><span>2</span></span>
				<span class="swpm-wizard-step__label"><?php esc_html_e( 'Configuration', 'swpmail' ); ?></span>
			</div>
			<div class="swpm-wizard-step-connector" data-after="2"></div>
			<div class="swpm-wizard-step" data-step="3">
				<span class="swpm-wizard-step__number"><span>3</span></span>
				<span class="swpm-wizard-step__label"><?php esc_html_e( 'Test & Finish', 'swpmail' ); ?></span>
			</div>
		</div>
	</div>

	<!-- ═══════════════════════════════════════════════════════════════
		STEP 1 — Choose Mailer
		═══════════════════════════════════════════════════════════════ -->
	<div class="swpm-wizard-panel" id="swpm-wizard-step-1">
		<h2><?php esc_html_e( 'Choose Your SMTP Mailer', 'swpmail' ); ?></h2>
		<p><?php esc_html_e( 'Select the mailer you would like to use to send emails from your WordPress site.', 'swpmail' ); ?></p>

		<?php
		$swpm_grid_active_key  = '';
		$swpm_grid_extra_class = 'swpm-wizard-provider-grid';
		require __DIR__ . '/provider-grid.php';
		?>

		<div class="swpm-wizard-actions">
			<button type="button" class="swpm-wizard-skip"><?php esc_html_e( 'Skip Setup', 'swpmail' ); ?></button>
			<button type="button" class="swpm-btn swpm-btn--primary" id="swpm-wizard-next-1">
				<?php esc_html_e( 'Continue', 'swpmail' ); ?> &rarr;
			</button>
		</div>
	</div>

	<!-- ═══════════════════════════════════════════════════════════════
		STEP 2 — Configuration
		═══════════════════════════════════════════════════════════════ -->
	<div class="swpm-wizard-panel" id="swpm-wizard-step-2" style="display:none">
		<h2><?php esc_html_e( 'Configure Your Mailer', 'swpmail' ); ?></h2>
		<p><?php esc_html_e( 'Enter your sender identity and provider credentials below.', 'swpmail' ); ?></p>

		<!-- Sender Identity -->
		<div class="swpm-wizard-section-label">
			<span class="dashicons dashicons-admin-users"></span>
			<?php esc_html_e( 'Sender Identity', 'swpmail' ); ?>
		</div>

		<div class="swpm-wizard-field-row">
			<div class="swpm-wizard-field-group">
				<label for="swpm-wizard-from-name"><?php esc_html_e( 'From Name', 'swpmail' ); ?></label>
				<input type="text" id="swpm-wizard-from-name" value="<?php echo esc_attr( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) ); ?>">
			</div>
			<div class="swpm-wizard-field-group">
				<label for="swpm-wizard-from-email"><?php esc_html_e( 'From Email', 'swpmail' ); ?></label>
				<input type="email" id="swpm-wizard-from-email" value="<?php echo esc_attr( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) ); ?>">
			</div>
		</div>

		<!-- Provider-specific fields -->
		<div class="swpm-wizard-fields">

			<!-- ── SMTP ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-smtp" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'SMTP Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-row">
					<div class="swpm-wizard-field-group">
						<label><?php esc_html_e( 'SMTP Host', 'swpmail' ); ?></label>
						<input type="text" name="swpm_smtp_host" placeholder="smtp.example.com">
					</div>
					<div class="swpm-wizard-field-group">
						<label><?php esc_html_e( 'SMTP Port', 'swpmail' ); ?></label>
						<input type="number" name="swpm_smtp_port" value="587" min="1" max="65535">
					</div>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'Encryption', 'swpmail' ); ?></label>
					<select name="swpm_smtp_encryption">
						<option value="tls"><?php esc_html_e( 'TLS', 'swpmail' ); ?></option>
						<option value="ssl"><?php esc_html_e( 'SSL', 'swpmail' ); ?></option>
						<option value=""><?php esc_html_e( 'None', 'swpmail' ); ?></option>
					</select>
				</div>
				<div class="swpm-wizard-field-row">
					<div class="swpm-wizard-field-group">
						<label><?php esc_html_e( 'Username', 'swpmail' ); ?></label>
						<input type="text" name="swpm_smtp_username">
					</div>
					<div class="swpm-wizard-field-group">
						<label><?php esc_html_e( 'Password', 'swpmail' ); ?></label>
						<input type="password" name="swpm_smtp_password_enc" autocomplete="new-password">
					</div>
				</div>
			</div>

			<!-- ── Mailgun ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-mailgun" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Mailgun Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
					<input type="password" name="swpm_mailgun_api_key_enc" autocomplete="new-password">
				</div>
				<div class="swpm-wizard-field-row">
					<div class="swpm-wizard-field-group">
						<label><?php esc_html_e( 'Domain', 'swpmail' ); ?></label>
						<input type="text" name="swpm_mailgun_domain" placeholder="mg.example.com">
					</div>
					<div class="swpm-wizard-field-group">
						<label><?php esc_html_e( 'Region', 'swpmail' ); ?></label>
						<select name="swpm_mailgun_region">
							<option value="us"><?php esc_html_e( 'US', 'swpmail' ); ?></option>
							<option value="eu"><?php esc_html_e( 'EU', 'swpmail' ); ?></option>
						</select>
					</div>
				</div>
			</div>

			<!-- ── SendGrid ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-sendgrid" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'SendGrid Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
					<input type="password" name="swpm_sendgrid_api_key_enc" autocomplete="new-password">
				</div>
			</div>

			<!-- ── Postmark ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-postmark" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Postmark Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'Server API Token', 'swpmail' ); ?></label>
					<input type="password" name="swpm_postmark_server_token_enc" autocomplete="new-password">
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'Message Stream', 'swpmail' ); ?></label>
					<input type="text" name="swpm_postmark_message_stream" value="outbound">
				</div>
			</div>

			<!-- ── Brevo ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-brevo" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Brevo Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
					<input type="password" name="swpm_brevo_api_key_enc" autocomplete="new-password">
				</div>
			</div>

			<!-- ── Amazon SES ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-ses" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Amazon SES Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-row">
					<div class="swpm-wizard-field-group">
						<label><?php esc_html_e( 'Access Key ID', 'swpmail' ); ?></label>
						<input type="password" name="swpm_ses_access_key_enc" autocomplete="new-password">
					</div>
					<div class="swpm-wizard-field-group">
						<label><?php esc_html_e( 'Secret Access Key', 'swpmail' ); ?></label>
						<input type="password" name="swpm_ses_secret_key_enc" autocomplete="new-password">
					</div>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'Region', 'swpmail' ); ?></label>
					<input type="text" name="swpm_ses_region" value="us-east-1" placeholder="us-east-1">
				</div>
			</div>

			<!-- ── Resend ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-resend" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Resend Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
					<input type="password" name="swpm_resend_api_key_enc" autocomplete="new-password">
				</div>
			</div>

			<!-- ── SendLayer ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-sendlayer" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'SendLayer Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
					<input type="password" name="swpm_sendlayer_api_key_enc" autocomplete="new-password">
				</div>
			</div>

			<!-- ── SMTP.com ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-smtpcom" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'SMTP.com Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
					<input type="password" name="swpm_smtpcom_api_key_enc" autocomplete="new-password">
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'Sender / Channel', 'swpmail' ); ?></label>
					<input type="text" name="swpm_smtpcom_channel">
				</div>
			</div>

			<!-- ── Gmail ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-gmail" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Gmail Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'Gmail Address', 'swpmail' ); ?></label>
					<input type="email" name="swpm_gmail_username" placeholder="you@gmail.com">
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'App Password', 'swpmail' ); ?></label>
					<input type="password" name="swpm_gmail_app_password_enc" autocomplete="new-password">
					<p class="description"><?php esc_html_e( 'Generate an App Password from your Google Account security settings.', 'swpmail' ); ?></p>
				</div>
			</div>

			<!-- ── Outlook ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-outlook" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( '365 / Outlook Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'Email Address', 'swpmail' ); ?></label>
					<input type="email" name="swpm_outlook_username" placeholder="you@outlook.com">
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'Password', 'swpmail' ); ?></label>
					<input type="password" name="swpm_outlook_password_enc" autocomplete="new-password">
				</div>
			</div>

			<!-- ── Elastic Email ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-elasticemail" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Elastic Email Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
					<input type="password" name="swpm_elasticemail_api_key_enc" autocomplete="new-password">
				</div>
			</div>

			<!-- ── Mailjet ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-mailjet" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Mailjet Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-row">
					<div class="swpm-wizard-field-group">
						<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
						<input type="password" name="swpm_mailjet_api_key_enc" autocomplete="new-password">
					</div>
					<div class="swpm-wizard-field-group">
						<label><?php esc_html_e( 'Secret Key', 'swpmail' ); ?></label>
						<input type="password" name="swpm_mailjet_secret_key_enc" autocomplete="new-password">
					</div>
				</div>
			</div>

			<!-- ── MailerSend ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-mailersend" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'MailerSend Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'API Token', 'swpmail' ); ?></label>
					<input type="password" name="swpm_mailersend_api_token_enc" autocomplete="new-password">
				</div>
			</div>

			<!-- ── SMTP2GO ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-smtp2go" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'SMTP2GO Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
					<input type="password" name="swpm_smtp2go_api_key_enc" autocomplete="new-password">
				</div>
			</div>

			<!-- ── SparkPost ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-sparkpost" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'SparkPost Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
					<input type="password" name="swpm_sparkpost_api_key_enc" autocomplete="new-password">
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'Region', 'swpmail' ); ?></label>
					<select name="swpm_sparkpost_region">
						<option value="us"><?php esc_html_e( 'US', 'swpmail' ); ?></option>
						<option value="eu"><?php esc_html_e( 'EU', 'swpmail' ); ?></option>
					</select>
				</div>
			</div>

			<!-- ── Zoho Mail ── -->
			<div class="swpm-wizard-provider-fields swpm-wizard-pf-zoho" style="display:none">
				<hr class="swpm-wizard-separator">
				<div class="swpm-wizard-section-label">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Zoho Mail Configuration', 'swpmail' ); ?>
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'Email Address', 'swpmail' ); ?></label>
					<input type="email" name="swpm_zoho_username" placeholder="you@zoho.com">
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'Password', 'swpmail' ); ?></label>
					<input type="password" name="swpm_zoho_password_enc" autocomplete="new-password">
				</div>
				<div class="swpm-wizard-field-group">
					<label><?php esc_html_e( 'Region', 'swpmail' ); ?></label>
					<select name="swpm_zoho_region">
						<option value="com"><?php esc_html_e( 'Global (.com)', 'swpmail' ); ?></option>
						<option value="eu"><?php esc_html_e( 'Europe (.eu)', 'swpmail' ); ?></option>
						<option value="in"><?php esc_html_e( 'India (.in)', 'swpmail' ); ?></option>
						<option value="com.au"><?php esc_html_e( 'Australia (.com.au)', 'swpmail' ); ?></option>
						<option value="jp"><?php esc_html_e( 'Japan (.jp)', 'swpmail' ); ?></option>
					</select>
				</div>
			</div>

		</div><!-- .swpm-wizard-fields -->

		<div class="swpm-wizard-actions">
			<button type="button" class="swpm-btn swpm-btn--secondary" id="swpm-wizard-back-2">
				&larr; <?php esc_html_e( 'Back', 'swpmail' ); ?>
			</button>
			<button type="button" class="swpm-btn swpm-btn--primary" id="swpm-wizard-next-2">
				<?php esc_html_e( 'Continue', 'swpmail' ); ?> &rarr;
			</button>
		</div>
	</div>

	<!-- ═══════════════════════════════════════════════════════════════
		STEP 3 — Test & Finish
		═══════════════════════════════════════════════════════════════ -->
	<div class="swpm-wizard-panel" id="swpm-wizard-step-3" style="display:none">
		<h2><?php esc_html_e( 'Test & Complete Setup', 'swpmail' ); ?></h2>
		<p><?php esc_html_e( 'We will save your settings and send a test email to verify everything works.', 'swpmail' ); ?></p>

		<!-- Summary -->
		<div class="swpm-wizard-summary">
			<div class="swpm-wizard-summary-row">
				<span><?php esc_html_e( 'Mailer', 'swpmail' ); ?></span>
				<span id="swpm-wizard-summary-provider">—</span>
			</div>
			<div class="swpm-wizard-summary-row">
				<span><?php esc_html_e( 'From', 'swpmail' ); ?></span>
				<span id="swpm-wizard-summary-from">—</span>
			</div>
			<div class="swpm-wizard-summary-row">
				<span><?php esc_html_e( 'Test Email To', 'swpmail' ); ?></span>
				<span><?php echo esc_html( get_option( 'admin_email' ) ); ?></span>
			</div>
		</div>

		<!-- Test area -->
		<div class="swpm-wizard-test-area">
			<div class="swpm-wizard-test-icon" id="swpm-wizard-test-icon">
				<span class="dashicons dashicons-email"></span>
			</div>

			<div id="swpm-wizard-test-result"></div>
		</div>

		<div class="swpm-wizard-actions">
			<button type="button" class="swpm-btn swpm-btn--secondary" id="swpm-wizard-back-3">
				&larr; <?php esc_html_e( 'Back', 'swpmail' ); ?>
			</button>
			<button type="button" class="swpm-btn swpm-btn--primary" id="swpm-wizard-test-btn" data-label="<?php esc_attr_e( 'Save & Send Test Email', 'swpmail' ); ?>">
				<span class="dashicons dashicons-email"></span>
				<?php esc_html_e( 'Save & Send Test Email', 'swpmail' ); ?>
			</button>
			<button type="button" class="swpm-btn swpm-btn--success" id="swpm-wizard-finish" style="display:none">
				<?php esc_html_e( 'Finish Setup', 'swpmail' ); ?> &rarr;
			</button>
		</div>
	</div>

</div><!-- .swpm-wizard-wrap -->
