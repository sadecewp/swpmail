<?php
/**
 * Mail settings page — Tabbed UX redesign.
 *
 * Layout:
 *  Tab 1 — Provider  : provider grid + inline config panel
 *  Tab 2 — Sender    : from name / from email
 *  Tab 3 — Failover  : backup provider + health dashboard
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$provider_key = get_option( 'swpm_mail_provider', 'phpmail' );

// Display OAuth callback notice if present.
$oauth_notice = get_transient( 'swpm_oauth_notice' );
if ( $oauth_notice ) {
	delete_transient( 'swpm_oauth_notice' );
}

// Display DNS auto-check notice if present.
$dns_notice = get_transient( 'swpm_dns_notice' );
if ( $dns_notice ) {
	delete_transient( 'swpm_dns_notice' );
}

// Failover data.
$connections     = swpm( 'connections' );
$backup_key      = get_option( 'swpm_backup_provider', '' );
$failover_status = $connections ? $connections->get_status_summary() : null;
$all_providers   = swpm( 'provider_factory' ) ? swpm( 'provider_factory' )->get_all() : array();

// OAuth data.
$gmail_oauth             = swpm( 'oauth' );
$gmail_oauth_connected   = $gmail_oauth && $gmail_oauth->is_connected( 'gmail' );
$gmail_oauth_email       = $gmail_oauth_connected ? $gmail_oauth->get_authenticated_email( 'gmail' ) : '';
$gmail_has_oauth_creds   = $gmail_oauth && $gmail_oauth->has_credentials( 'gmail' );

$outlook_oauth           = swpm( 'oauth' );
$outlook_oauth_connected = $outlook_oauth && $outlook_oauth->is_connected( 'outlook' );
$outlook_oauth_email     = $outlook_oauth_connected ? $outlook_oauth->get_authenticated_email( 'outlook' ) : '';
$outlook_has_oauth_creds = $outlook_oauth && $outlook_oauth->has_credentials( 'outlook' );

$img_url = plugin_dir_url( dirname( __DIR__ ) ) . 'public/img/';
?>

<div class="swpm-wrap">

	<?php if ( $oauth_notice ) : ?>
		<div class="swpm-notice swpm-notice--<?php echo esc_attr( $oauth_notice['type'] ); ?>">
			<span class="dashicons dashicons-<?php echo 'success' === $oauth_notice['type'] ? 'yes-alt' : 'warning'; ?>"></span>
			<?php echo esc_html( $oauth_notice['message'] ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $dns_notice ) : ?>
		<div class="swpm-notice swpm-notice--<?php echo esc_attr( $dns_notice['type'] ); ?>">
			<span class="dashicons dashicons-<?php echo 'success' === $dns_notice['type'] ? 'yes-alt' : 'shield'; ?>"></span>
			<?php echo esc_html( $dns_notice['message'] ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=swpmail-dns-checker' ) ); ?>" style="margin-left: 8px; font-weight: 700;"><?php esc_html_e( 'View Details →', 'swpmail' ); ?></a>
		</div>
	<?php endif; ?>

	<!-- Page Header -->
	<div class="swpm-page-header">
		<div>
			<h1><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Mail Settings', 'swpmail' ); ?></h1>
			<p class="swpm-page-subtitle"><?php esc_html_e( 'Configure your mail provider, sender identity, and failover settings.', 'swpmail' ); ?></p>
		</div>
		<!-- Floating save / test bar in header -->
		<div class="swpm-ms-header-actions" id="swpm-ms-header-actions">
			<input type="email" id="swpm-test-recipient" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="swpm-ms-test-input" placeholder="<?php esc_attr_e( 'test@example.com', 'swpmail' ); ?>">
			<button type="button" id="swpm-test-connection" class="swpm-btn swpm-btn--secondary swpm-btn--sm">
				<span class="dashicons dashicons-email"></span> <?php esc_html_e( 'Send Test', 'swpmail' ); ?>
			</button>
			<span id="swpm-test-result"></span>
		</div>
	</div>

	<!-- ─────────────────────────────────────────────────────────────
	     TAB NAVIGATION
	     ───────────────────────────────────────────────────────────── -->
	<div class="swpm-ms-tabs" role="tablist">
		<button class="swpm-ms-tab active" role="tab" aria-selected="true"  data-tab="provider"  type="button">
			<span class="dashicons dashicons-cloud"></span>
			<?php esc_html_e( 'Provider', 'swpmail' ); ?>
		</button>
		<button class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="sender"    type="button">
			<span class="dashicons dashicons-businessman"></span>
			<?php esc_html_e( 'Sender Identity', 'swpmail' ); ?>
		</button>
		<button class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="failover"  type="button">
			<span class="dashicons dashicons-shield-alt"></span>
			<?php esc_html_e( 'Failover', 'swpmail' ); ?>
			<?php if ( $failover_status && $failover_status['backup'] ) : ?>
				<span class="swpm-ms-tab-dot swpm-ms-tab-dot--success"></span>
			<?php endif; ?>
		</button>
	</div>

	<!-- ─────────────────────────────────────────────────────────────
	     MAIN FORM — wraps Provider + Sender tabs
	     ───────────────────────────────────────────────────────────── -->
	<form method="post" action="options.php" id="swpm-main-settings-form">
		<?php settings_fields( 'swpm_mail_settings_group' ); ?>
		<input type="hidden" name="swpm_mail_provider" id="swpm-provider-select" value="<?php echo esc_attr( $provider_key ); ?>">

		<!-- ═══════════════════════════════════════════════════════════
		     TAB 1 — PROVIDER
		     ═══════════════════════════════════════════════════════════ -->
		<div class="swpm-ms-panel" id="swpm-tab-provider" role="tabpanel">

			<div class="swpm-card swpm-ms-provider-card">

				<!-- Provider grid -->
				<div class="swpm-ms-provider-section">
					<p class="swpm-ms-provider-hint"><?php esc_html_e( 'Select your mail provider. SMTP works with any mail server; dedicated transactional services (Mailgun, SendGrid, Postmark…) offer higher deliverability.', 'swpmail' ); ?></p>

					<?php
					$swpm_grid_active_key  = $provider_key;
					$swpm_grid_extra_class = '';
					include __DIR__ . '/provider-grid.php';
					?>
				</div>

				<!-- Provider config — inline, appears below the grid -->
				<div class="swpm-ms-config-panel" id="swpm-ms-config-panel">

					<!-- phpmail (no config needed) -->
					<div class="swpm-provider-fields swpm-phpmail-fields<?php echo 'phpmail' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<div class="swpm-ms-config-notice">
							<span class="dashicons dashicons-info-outline"></span>
							<?php esc_html_e( 'PHP Mail uses your server\'s built-in mail function. No extra configuration required, but deliverability may be poor. Consider switching to a dedicated provider.', 'swpmail' ); ?>
						</div>
					</div>

					<!-- SMTP -->
					<div class="swpm-provider-fields swpm-smtp-fields<?php echo 'smtp' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'SMTP Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'SMTP Host', 'swpmail' ); ?></label>
								<input type="text" name="swpm_smtp_host" value="<?php echo esc_attr( get_option( 'swpm_smtp_host' ) ); ?>" class="regular-text" placeholder="smtp.example.com">
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Port', 'swpmail' ); ?></label>
								<input type="number" name="swpm_smtp_port" value="<?php echo esc_attr( get_option( 'swpm_smtp_port', 587 ) ); ?>" class="small-text">
								<p class="description"><?php esc_html_e( '587 (TLS) · 465 (SSL) · 25 (none)', 'swpmail' ); ?></p>
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Encryption', 'swpmail' ); ?></label>
								<select name="swpm_smtp_encryption">
									<option value="tls" <?php selected( get_option( 'swpm_smtp_encryption', 'tls' ), 'tls' ); ?>>TLS</option>
									<option value="ssl" <?php selected( get_option( 'swpm_smtp_encryption' ), 'ssl' ); ?>>SSL</option>
									<option value=""    <?php selected( get_option( 'swpm_smtp_encryption' ), '' ); ?>><?php esc_html_e( 'None', 'swpmail' ); ?></option>
								</select>
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Username', 'swpmail' ); ?></label>
								<input type="text" name="swpm_smtp_username" value="<?php echo esc_attr( get_option( 'swpm_smtp_username' ) ); ?>" class="regular-text" autocomplete="off">
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Password', 'swpmail' ); ?></label>
								<input type="password" name="swpm_smtp_password_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
								<p class="description"><?php esc_html_e( 'Stored encrypted. Leave blank to keep current.', 'swpmail' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Mailgun -->
					<div class="swpm-provider-fields swpm-mailgun-fields<?php echo 'mailgun' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Mailgun Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field swpm-ms-field--full">
								<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
								<input type="password" name="swpm_mailgun_api_key_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Domain', 'swpmail' ); ?></label>
								<input type="text" name="swpm_mailgun_domain" value="<?php echo esc_attr( get_option( 'swpm_mailgun_domain' ) ); ?>" class="regular-text" placeholder="mg.yourdomain.com">
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Region', 'swpmail' ); ?></label>
								<select name="swpm_mailgun_region">
									<option value="us" <?php selected( get_option( 'swpm_mailgun_region', 'us' ), 'us' ); ?>><?php esc_html_e( 'US', 'swpmail' ); ?></option>
									<option value="eu" <?php selected( get_option( 'swpm_mailgun_region' ), 'eu' ); ?>><?php esc_html_e( 'EU', 'swpmail' ); ?></option>
								</select>
							</div>
						</div>
					</div>

					<!-- SendGrid -->
					<div class="swpm-provider-fields swpm-sendgrid-fields<?php echo 'sendgrid' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'SendGrid Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field swpm-ms-field--full">
								<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
								<input type="password" name="swpm_sendgrid_api_key_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
						</div>
					</div>

					<!-- Postmark -->
					<div class="swpm-provider-fields swpm-postmark-fields<?php echo 'postmark' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Postmark Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Server Token', 'swpmail' ); ?></label>
								<input type="password" name="swpm_postmark_server_token_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Message Stream', 'swpmail' ); ?></label>
								<input type="text" name="swpm_postmark_message_stream" value="<?php echo esc_attr( get_option( 'swpm_postmark_message_stream', 'outbound' ) ); ?>" class="regular-text">
							</div>
						</div>
					</div>

					<!-- Brevo -->
					<div class="swpm-provider-fields swpm-brevo-fields<?php echo 'brevo' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Brevo Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field swpm-ms-field--full">
								<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
								<input type="password" name="swpm_brevo_api_key_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
						</div>
					</div>

					<!-- Amazon SES -->
					<div class="swpm-provider-fields swpm-ses-fields<?php echo 'ses' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Amazon SES Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Access Key ID', 'swpmail' ); ?></label>
								<input type="password" name="swpm_ses_access_key_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Secret Access Key', 'swpmail' ); ?></label>
								<input type="password" name="swpm_ses_secret_key_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Region', 'swpmail' ); ?></label>
								<select name="swpm_ses_region">
									<?php
									$ses_regions    = array( 'us-east-1', 'us-east-2', 'us-west-2', 'eu-west-1', 'eu-central-1', 'ap-south-1', 'ap-southeast-2' );
									$current_region = get_option( 'swpm_ses_region', 'us-east-1' );
									foreach ( $ses_regions as $region ) :
									?>
										<option value="<?php echo esc_attr( $region ); ?>" <?php selected( $current_region, $region ); ?>><?php echo esc_html( $region ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</div>

					<!-- Resend -->
					<div class="swpm-provider-fields swpm-resend-fields<?php echo 'resend' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Resend Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field swpm-ms-field--full">
								<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
								<input type="password" name="swpm_resend_api_key_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
						</div>
					</div>

					<!-- SendLayer -->
					<div class="swpm-provider-fields swpm-sendlayer-fields<?php echo 'sendlayer' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'SendLayer Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field swpm-ms-field--full">
								<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
								<input type="password" name="swpm_sendlayer_api_key_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
								<p class="description"><?php esc_html_e( 'Get your API key from the SendLayer dashboard.', 'swpmail' ); ?></p>
							</div>
						</div>
					</div>

					<!-- SMTP.com -->
					<div class="swpm-provider-fields swpm-smtpcom-fields<?php echo 'smtpcom' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'SMTP.com Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
								<input type="password" name="swpm_smtpcom_api_key_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Sender Channel', 'swpmail' ); ?></label>
								<input type="text" name="swpm_smtpcom_channel" value="<?php echo esc_attr( get_option( 'swpm_smtpcom_channel' ) ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'The sender channel name from your SMTP.com account.', 'swpmail' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Elastic Email -->
					<div class="swpm-provider-fields swpm-elasticemail-fields<?php echo 'elasticemail' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Elastic Email Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field swpm-ms-field--full">
								<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
								<input type="password" name="swpm_elasticemail_api_key_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
						</div>
					</div>

					<!-- Mailjet -->
					<div class="swpm-provider-fields swpm-mailjet-fields<?php echo 'mailjet' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Mailjet Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
								<input type="password" name="swpm_mailjet_api_key_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Secret Key', 'swpmail' ); ?></label>
								<input type="password" name="swpm_mailjet_secret_key_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
						</div>
					</div>

					<!-- MailerSend -->
					<div class="swpm-provider-fields swpm-mailersend-fields<?php echo 'mailersend' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'MailerSend Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field swpm-ms-field--full">
								<label><?php esc_html_e( 'API Token', 'swpmail' ); ?></label>
								<input type="password" name="swpm_mailersend_api_token_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
						</div>
					</div>

					<!-- SMTP2GO -->
					<div class="swpm-provider-fields swpm-smtp2go-fields<?php echo 'smtp2go' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'SMTP2GO Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field swpm-ms-field--full">
								<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
								<input type="password" name="swpm_smtp2go_api_key_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
						</div>
					</div>

					<!-- SparkPost -->
					<div class="swpm-provider-fields swpm-sparkpost-fields<?php echo 'sparkpost' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'SparkPost Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'API Key', 'swpmail' ); ?></label>
								<input type="password" name="swpm_sparkpost_api_key_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Region', 'swpmail' ); ?></label>
								<?php $sparkpost_region = get_option( 'swpm_sparkpost_region', 'us' ); ?>
								<select name="swpm_sparkpost_region">
									<option value="us" <?php selected( $sparkpost_region, 'us' ); ?>><?php esc_html_e( 'US', 'swpmail' ); ?></option>
									<option value="eu" <?php selected( $sparkpost_region, 'eu' ); ?>><?php esc_html_e( 'EU', 'swpmail' ); ?></option>
								</select>
							</div>
						</div>
					</div>

					<!-- Zoho -->
					<div class="swpm-provider-fields swpm-zoho-fields<?php echo 'zoho' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Zoho Mail Configuration', 'swpmail' ); ?></h4>
						<div class="swpm-ms-field-grid">
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Email Address', 'swpmail' ); ?></label>
								<input type="email" name="swpm_zoho_username" value="<?php echo esc_attr( get_option( 'swpm_zoho_username' ) ); ?>" class="regular-text" placeholder="you@zoho.com">
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Password', 'swpmail' ); ?></label>
								<input type="password" name="swpm_zoho_password_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
								<p class="description"><?php esc_html_e( 'Stored encrypted. Leave blank to keep current.', 'swpmail' ); ?></p>
							</div>
							<div class="swpm-ms-field">
								<label><?php esc_html_e( 'Region', 'swpmail' ); ?></label>
								<?php $zoho_region = get_option( 'swpm_zoho_region', 'com' ); ?>
								<select name="swpm_zoho_region">
									<option value="com"   <?php selected( $zoho_region, 'com' ); ?>><?php esc_html_e( 'Global (.com)', 'swpmail' ); ?></option>
									<option value="eu"    <?php selected( $zoho_region, 'eu' ); ?>><?php esc_html_e( 'Europe (.eu)', 'swpmail' ); ?></option>
									<option value="in"    <?php selected( $zoho_region, 'in' ); ?>><?php esc_html_e( 'India (.in)', 'swpmail' ); ?></option>
									<option value="com.au" <?php selected( $zoho_region, 'com.au' ); ?>><?php esc_html_e( 'Australia (.com.au)', 'swpmail' ); ?></option>
									<option value="jp"   <?php selected( $zoho_region, 'jp' ); ?>><?php esc_html_e( 'Japan (.jp)', 'swpmail' ); ?></option>
								</select>
							</div>
						</div>
					</div>

					<!-- Gmail (OAuth) -->
					<div class="swpm-provider-fields swpm-gmail-fields<?php echo 'gmail' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Gmail / Google Workspace', 'swpmail' ); ?></h4>

						<div class="swpm-ms-oauth-block">
							<div class="swpm-ms-oauth-label">
								<span class="dashicons dashicons-shield"></span>
								<?php esc_html_e( 'OAuth 2.0 (Recommended)', 'swpmail' ); ?>
							</div>
							<div class="swpm-info-box" style="margin-bottom: 14px;">
								<p><?php printf(
									/* translators: %s: redirect URI */
									esc_html__( 'Create a Google Cloud project, enable Gmail API, create OAuth credentials, and set the Redirect URI to: %s', 'swpmail' ),
									'<code class="swpm-oauth-redirect-uri">' . esc_html( $gmail_oauth ? $gmail_oauth->get_redirect_uri() : '' ) . '</code>'
								); ?></p>
							</div>
							<div class="swpm-ms-field-grid">
								<div class="swpm-ms-field swpm-ms-field--full">
									<label><?php esc_html_e( 'Client ID', 'swpmail' ); ?></label>
									<input type="text" name="swpm_gmail_oauth_client_id" value="<?php echo esc_attr( get_option( 'swpm_gmail_oauth_client_id', '' ) ); ?>" class="regular-text" placeholder="xxxx.apps.googleusercontent.com" autocomplete="off">
								</div>
								<div class="swpm-ms-field swpm-ms-field--full">
									<label><?php esc_html_e( 'Client Secret', 'swpmail' ); ?></label>
									<input type="password" name="swpm_gmail_oauth_client_secret_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
									<p class="description"><?php esc_html_e( 'Stored encrypted. Leave blank to keep current.', 'swpmail' ); ?></p>
								</div>
								<div class="swpm-ms-field swpm-ms-field--full">
									<label><?php esc_html_e( 'Authorization', 'swpmail' ); ?></label>
									<?php if ( $gmail_oauth_connected ) : ?>
										<div class="swpm-oauth-status swpm-oauth-status--connected">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php printf( esc_html__( 'Connected as %s', 'swpmail' ), '<strong>' . esc_html( $gmail_oauth_email ) . '</strong>' ); ?>
										</div>
										<button type="button" class="swpm-btn swpm-btn--danger swpm-oauth-disconnect" data-provider="gmail">
											<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Disconnect', 'swpmail' ); ?>
										</button>
									<?php else : ?>
										<button type="button" class="swpm-btn swpm-btn--primary swpm-oauth-connect" data-provider="gmail" <?php echo ! $gmail_has_oauth_creds ? 'disabled' : ''; ?>>
											<span class="dashicons dashicons-admin-network"></span> <?php esc_html_e( 'Sign in with Google', 'swpmail' ); ?>
										</button>
										<?php if ( ! $gmail_has_oauth_creds ) : ?>
											<p class="description"><?php esc_html_e( 'Save Client ID and Secret first, then authorize.', 'swpmail' ); ?></p>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</div>
						</div>

						<details class="swpm-ms-oauth-alt">
							<summary><?php esc_html_e( 'App Password (alternative, only when OAuth is not connected)', 'swpmail' ); ?></summary>
							<div class="swpm-ms-field-grid" style="margin-top: 14px;">
								<div class="swpm-ms-field">
									<label><?php esc_html_e( 'Gmail Address', 'swpmail' ); ?></label>
									<input type="email" name="swpm_gmail_username" value="<?php echo esc_attr( get_option( 'swpm_gmail_username' ) ); ?>" class="regular-text" placeholder="you@gmail.com">
								</div>
								<div class="swpm-ms-field">
									<label><?php esc_html_e( 'App Password', 'swpmail' ); ?></label>
									<input type="password" name="swpm_gmail_app_password_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
									<p class="description"><?php esc_html_e( 'Google Account → Security → 2-Step Verification → App passwords', 'swpmail' ); ?></p>
								</div>
							</div>
						</details>
					</div>

					<!-- Outlook / 365 (OAuth) -->
					<div class="swpm-provider-fields swpm-outlook-fields<?php echo 'outlook' !== $provider_key ? ' swpm-provider-fields--hidden' : ''; ?>">
						<h4 class="swpm-ms-config-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Microsoft 365 / Outlook', 'swpmail' ); ?></h4>

						<div class="swpm-ms-oauth-block">
							<div class="swpm-ms-oauth-label">
								<span class="dashicons dashicons-shield"></span>
								<?php esc_html_e( 'OAuth 2.0 (Recommended)', 'swpmail' ); ?>
							</div>
							<div class="swpm-info-box" style="margin-bottom: 14px;">
								<p><?php printf(
									/* translators: %s: redirect URI */
									esc_html__( 'Register an app in Microsoft Entra ID (Azure AD), add SMTP.Send permission, create a client secret, and set the Redirect URI to: %s', 'swpmail' ),
									'<code class="swpm-oauth-redirect-uri">' . esc_html( $outlook_oauth ? $outlook_oauth->get_redirect_uri() : '' ) . '</code>'
								); ?></p>
							</div>
							<div class="swpm-ms-field-grid">
								<div class="swpm-ms-field swpm-ms-field--full">
									<label><?php esc_html_e( 'Application (Client) ID', 'swpmail' ); ?></label>
									<input type="text" name="swpm_outlook_oauth_client_id" value="<?php echo esc_attr( get_option( 'swpm_outlook_oauth_client_id', '' ) ); ?>" class="regular-text" autocomplete="off">
								</div>
								<div class="swpm-ms-field swpm-ms-field--full">
									<label><?php esc_html_e( 'Client Secret', 'swpmail' ); ?></label>
									<input type="password" name="swpm_outlook_oauth_client_secret_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
									<p class="description"><?php esc_html_e( 'Stored encrypted. Leave blank to keep current.', 'swpmail' ); ?></p>
								</div>
								<div class="swpm-ms-field swpm-ms-field--full">
									<label><?php esc_html_e( 'Authorization', 'swpmail' ); ?></label>
									<?php if ( $outlook_oauth_connected ) : ?>
										<div class="swpm-oauth-status swpm-oauth-status--connected">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php printf( esc_html__( 'Connected as %s', 'swpmail' ), '<strong>' . esc_html( $outlook_oauth_email ) . '</strong>' ); ?>
										</div>
										<button type="button" class="swpm-btn swpm-btn--danger swpm-oauth-disconnect" data-provider="outlook">
											<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Disconnect', 'swpmail' ); ?>
										</button>
									<?php else : ?>
										<button type="button" class="swpm-btn swpm-btn--primary swpm-oauth-connect" data-provider="outlook" <?php echo ! $outlook_has_oauth_creds ? 'disabled' : ''; ?>>
											<span class="dashicons dashicons-admin-network"></span> <?php esc_html_e( 'Sign in with Microsoft', 'swpmail' ); ?>
										</button>
										<?php if ( ! $outlook_has_oauth_creds ) : ?>
											<p class="description"><?php esc_html_e( 'Save Client ID and Secret first, then authorize.', 'swpmail' ); ?></p>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</div>
						</div>

						<details class="swpm-ms-oauth-alt">
							<summary><?php esc_html_e( 'Password (alternative, only when OAuth is not connected)', 'swpmail' ); ?></summary>
							<div class="swpm-ms-field-grid" style="margin-top: 14px;">
								<div class="swpm-ms-field">
									<label><?php esc_html_e( 'Email Address', 'swpmail' ); ?></label>
									<input type="email" name="swpm_outlook_username" value="<?php echo esc_attr( get_option( 'swpm_outlook_username' ) ); ?>" class="regular-text" placeholder="you@outlook.com">
								</div>
								<div class="swpm-ms-field">
									<label><?php esc_html_e( 'Password', 'swpmail' ); ?></label>
									<input type="password" name="swpm_outlook_password_enc" placeholder="••••••••" class="regular-text" autocomplete="new-password">
									<p class="description"><?php esc_html_e( 'Stored encrypted. Leave blank to keep current.', 'swpmail' ); ?></p>
								</div>
							</div>
						</details>
					</div>

				</div><!-- /.swpm-ms-config-panel -->

				<!-- Save bar inside card -->
				<div class="swpm-ms-save-bar">
					<button type="submit" name="submit" class="swpm-btn swpm-btn--primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Provider Settings', 'swpmail' ); ?></button>
				</div>

			</div><!-- /.swpm-card.swpm-ms-provider-card -->

		</div><!-- /#swpm-tab-provider -->


		<!-- ═══════════════════════════════════════════════════════════
		     TAB 2 — SENDER IDENTITY
		     ═══════════════════════════════════════════════════════════ -->
		<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-sender" role="tabpanel">

			<div class="swpm-card">
				<div class="swpm-ms-field-grid">
					<div class="swpm-ms-field">
						<label for="swpm_from_name"><?php esc_html_e( 'From Name', 'swpmail' ); ?></label>
						<input type="text" id="swpm_from_name" name="swpm_from_name" value="<?php echo esc_attr( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'The name recipients see in their inbox.', 'swpmail' ); ?></p>
					</div>
					<div class="swpm-ms-field">
						<label for="swpm_from_email"><?php esc_html_e( 'From Email', 'swpmail' ); ?></label>
						<input type="email" id="swpm_from_email" name="swpm_from_email" value="<?php echo esc_attr( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Must be verified with your mail provider for best deliverability.', 'swpmail' ); ?></p>
					</div>
				</div>

				<div class="swpm-ms-save-bar">
					<button type="submit" name="submit" class="swpm-btn swpm-btn--primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Sender Settings', 'swpmail' ); ?></button>
				</div>
			</div>

		</div><!-- /#swpm-tab-sender -->

	</form><!-- /#swpm-main-settings-form -->


	<!-- ═══════════════════════════════════════════════════════════
	     TAB 3 — FAILOVER  (own form, outside main form)
	     ═══════════════════════════════════════════════════════════ -->
	<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-failover" role="tabpanel">

		<div class="swpm-card">

			<div class="swpm-info-box" style="margin-bottom: 20px;">
				<h3><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Auto-Failover', 'swpmail' ); ?></h3>
				<p><?php esc_html_e( 'When the primary provider fails, SWPMail automatically retries through the backup provider. The backup must be a separately configured provider.', 'swpmail' ); ?></p>
			</div>

			<?php if ( $failover_status ) : ?>
			<!-- Connection Status -->
			<div class="swpm-connections-status">
				<!-- Primary -->
				<div class="swpm-connection-slot">
					<div class="swpm-connection-slot__header">
						<span class="swpm-connection-slot__badge swpm-connection-slot__badge--primary"><?php esc_html_e( 'Primary', 'swpmail' ); ?></span>
						<strong><?php echo esc_html( $failover_status['primary']['label'] ); ?></strong>
					</div>
					<div class="swpm-connection-slot__body">
						<?php if ( $failover_status['primary']['healthy'] ) : ?>
							<span class="swpm-health-dot swpm-health-dot--healthy"></span>
							<span class="swpm-health-label"><?php esc_html_e( 'Healthy', 'swpmail' ); ?></span>
						<?php else : ?>
							<span class="swpm-health-dot swpm-health-dot--unhealthy"></span>
							<span class="swpm-health-label swpm-health-label--unhealthy"><?php esc_html_e( 'Unhealthy', 'swpmail' ); ?></span>
						<?php endif; ?>
						<?php if ( $failover_status['primary']['failures'] > 0 ) : ?>
							<span class="swpm-health-failures"><?php printf( esc_html__( '%d failures', 'swpmail' ), $failover_status['primary']['failures'] ); ?></span>
						<?php endif; ?>
					</div>
					<div class="swpm-connection-slot__actions">
						<button type="button" class="swpm-btn swpm-btn--small swpm-btn--secondary swpm-health-check-btn" data-slot="primary">
							<span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Check Health', 'swpmail' ); ?>
						</button>
						<span class="swpm-health-check-result" data-slot="primary"></span>
					</div>
					<?php if ( $failover_status['primary']['last_check'] > 0 ) : ?>
					<div class="swpm-connection-slot__meta">
						<?php
						$last_check_status = $failover_status['primary']['last_check_ok'] ? '✓' : '✗';
						printf( esc_html__( 'Last check: %1$s %2$s ago', 'swpmail' ), $last_check_status, esc_html( human_time_diff( $failover_status['primary']['last_check'] ) ) );
						?>
					</div>
					<?php endif; ?>
				</div>

				<!-- Arrow -->
				<div class="swpm-connection-arrow">
					<span class="dashicons dashicons-arrow-right-alt"></span>
					<span class="swpm-connection-arrow__label"><?php esc_html_e( 'Failover', 'swpmail' ); ?></span>
				</div>

				<!-- Backup -->
				<div class="swpm-connection-slot<?php echo ! $failover_status['backup'] ? ' swpm-connection-slot--empty' : ''; ?>">
					<?php if ( $failover_status['backup'] ) : ?>
						<div class="swpm-connection-slot__header">
							<span class="swpm-connection-slot__badge swpm-connection-slot__badge--backup"><?php esc_html_e( 'Backup', 'swpmail' ); ?></span>
							<strong><?php echo esc_html( $failover_status['backup']['label'] ); ?></strong>
						</div>
						<div class="swpm-connection-slot__body">
							<?php if ( $failover_status['backup']['healthy'] ) : ?>
								<span class="swpm-health-dot swpm-health-dot--healthy"></span>
								<span class="swpm-health-label"><?php esc_html_e( 'Healthy', 'swpmail' ); ?></span>
							<?php else : ?>
								<span class="swpm-health-dot swpm-health-dot--unhealthy"></span>
								<span class="swpm-health-label swpm-health-label--unhealthy"><?php esc_html_e( 'Unhealthy', 'swpmail' ); ?></span>
							<?php endif; ?>
							<?php if ( $failover_status['backup']['failures'] > 0 ) : ?>
								<span class="swpm-health-failures"><?php printf( esc_html__( '%d failures', 'swpmail' ), $failover_status['backup']['failures'] ); ?></span>
							<?php endif; ?>
						</div>
						<div class="swpm-connection-slot__actions">
							<button type="button" class="swpm-btn swpm-btn--small swpm-btn--secondary swpm-health-check-btn" data-slot="backup">
								<span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Check Health', 'swpmail' ); ?>
							</button>
							<span class="swpm-health-check-result" data-slot="backup"></span>
						</div>
						<?php if ( $failover_status['backup']['last_check'] > 0 ) : ?>
						<div class="swpm-connection-slot__meta">
							<?php
							$backup_check_status = $failover_status['backup']['last_check_ok'] ? '✓' : '✗';
							printf( esc_html__( 'Last check: %1$s %2$s ago', 'swpmail' ), $backup_check_status, esc_html( human_time_diff( $failover_status['backup']['last_check'] ) ) );
							?>
						</div>
						<?php endif; ?>
					<?php else : ?>
						<div class="swpm-connection-slot__header">
							<span class="swpm-connection-slot__badge swpm-connection-slot__badge--backup"><?php esc_html_e( 'Backup', 'swpmail' ); ?></span>
							<span class="swpm-connection-slot__empty-text"><?php esc_html_e( 'Not configured', 'swpmail' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Backup Provider Form -->
			<form method="post" action="options.php" id="swpm-failover-form">
				<?php settings_fields( 'swpm_failover_settings_group' ); ?>
				<div class="swpm-ms-field-grid">
					<div class="swpm-ms-field swpm-ms-field--full">
						<label for="swpm-backup-provider-select"><?php esc_html_e( 'Backup Provider', 'swpmail' ); ?></label>
						<select name="swpm_backup_provider" id="swpm-backup-provider-select">
							<option value="none" <?php selected( $backup_key, '' ); selected( $backup_key, 'none' ); ?>><?php esc_html_e( '— No Backup (Disabled) —', 'swpmail' ); ?></option>
							<?php foreach ( $all_providers as $key => $class_name ) :
								if ( $key === $provider_key ) { continue; }
								$tmp   = class_exists( $class_name ) ? new $class_name() : null;
								$label = $tmp ? $tmp->get_label() : $key;
							?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $backup_key, $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Select a fully configured provider to use when the primary fails.', 'swpmail' ); ?></p>
					</div>
				</div>

				<div class="swpm-ms-save-bar">
					<button type="submit" name="submit" class="swpm-btn swpm-btn--secondary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Failover Settings', 'swpmail' ); ?></button>
				</div>
			</form>

		</div><!-- /.swpm-card -->

	</div><!-- /#swpm-tab-failover -->

</div><!-- /.swpm-wrap -->

<script>
(function() {
	/* ── Tab switching ──────────────────────────────── */
	var tabs   = document.querySelectorAll('.swpm-ms-tab');
	var panels = document.querySelectorAll('.swpm-ms-panel');

	tabs.forEach(function(tab) {
		tab.addEventListener('click', function() {
			var target = this.dataset.tab;

			tabs.forEach(function(t) {
				t.classList.remove('active');
				t.setAttribute('aria-selected', 'false');
			});
			panels.forEach(function(p) {
				p.classList.add('swpm-ms-panel--hidden');
			});

			this.classList.add('active');
			this.setAttribute('aria-selected', 'true');
			var panel = document.getElementById('swpm-tab-' + target);
			if (panel) {
				panel.classList.remove('swpm-ms-panel--hidden');
			}
		});
	});

	/* ── Provider field switching (replaces old card show/hide) ── */
	/* The existing swpmail-admin.js looks for .swpm-provider-fields and uses
	   display:none / display:block. We use a CSS class instead, so we need
	   to patch: hide means adding swpm-provider-fields--hidden,
	   show means removing it. Override via the same data the main JS reads. */
	document.addEventListener('swpm:providerChanged', function(e) {
		var key = e.detail && e.detail.provider;
		if (!key) return;
		document.querySelectorAll('.swpm-provider-fields').forEach(function(el) {
			el.classList.add('swpm-provider-fields--hidden');
		});
		var target = document.querySelector('.swpm-' + key + '-fields');
		if (target) {
			target.classList.remove('swpm-provider-fields--hidden');
		}
	});
})();
</script>
