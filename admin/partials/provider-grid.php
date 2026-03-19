<?php
/**
 * Shared provider grid partial.
 *
 * Expected variables (set before including):
 *   $swpm_grid_active_key  — string  Current active provider key ('' = none highlighted).
 *   $swpm_grid_extra_class — string  Extra CSS class for the wrapper (e.g. 'swpm-wizard-provider-grid').
 *   $img_url               — string  Base URL pointing to public/img/.
 *
 * @package SWPMail
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$swpm_grid_active_key  = $swpm_grid_active_key ?? '';
$swpm_grid_extra_class = $swpm_grid_extra_class ?? '';

$swpm_providers = array(
	array(
		'key'  => 'phpmail',
		'name' => __( 'PHP Mail', 'swpmail' ),
		'desc' => __( 'Default (no config)', 'swpmail' ),
		'icon' => '<span class="dashicons dashicons-admin-site-alt3"></span>',
	),
	array(
		'key'  => 'smtp',
		'name' => __( 'Generic SMTP', 'swpmail' ),
		'desc' => __( 'Any mail server', 'swpmail' ),
		'icon' => '<span class="dashicons dashicons-email"></span>',
	),
	array( 'key' => 'mailgun',      'name' => 'Mailgun',       'desc' => __( 'HTTP API', 'swpmail' ), 'img' => 'mailgun.svg' ),
	array( 'key' => 'sendgrid',     'name' => 'SendGrid',      'desc' => __( 'HTTP API', 'swpmail' ), 'img' => 'sendgrid.svg' ),
	array( 'key' => 'postmark',     'name' => 'Postmark',      'desc' => __( 'HTTP API', 'swpmail' ), 'img' => 'postmark.svg' ),
	array( 'key' => 'brevo',        'name' => 'Brevo',         'desc' => __( 'HTTP API', 'swpmail' ), 'img' => 'brevo.svg' ),
	array( 'key' => 'ses',          'name' => 'Amazon SES',    'desc' => __( 'HTTP API', 'swpmail' ), 'img' => 'aws.svg' ),
	array( 'key' => 'resend',       'name' => 'Resend',        'desc' => __( 'HTTP API', 'swpmail' ), 'img' => 'resend.svg' ),
	array( 'key' => 'sendlayer',    'name' => 'SendLayer',     'desc' => __( 'HTTP API', 'swpmail' ), 'img' => 'sendlayer.svg' ),
	array( 'key' => 'smtpcom',      'name' => 'SMTP.com',      'desc' => __( 'HTTP API', 'swpmail' ), 'img' => 'smtpcom.svg' ),
	array( 'key' => 'gmail',        'name' => 'Google / Gmail', 'desc' => __( 'OAuth / SMTP', 'swpmail' ), 'img' => 'gmail.svg' ),
	array( 'key' => 'outlook',      'name' => '365 / Outlook', 'desc' => __( 'OAuth / SMTP', 'swpmail' ), 'img' => 'outlook.svg' ),
	array( 'key' => 'elasticemail', 'name' => 'Elastic Email', 'desc' => __( 'HTTP API', 'swpmail' ), 'img' => 'elasticemail.svg' ),
	array( 'key' => 'mailjet',      'name' => 'Mailjet',       'desc' => __( 'HTTP API', 'swpmail' ), 'img' => 'mailjet.svg' ),
	array( 'key' => 'mailersend',   'name' => 'MailerSend',    'desc' => __( 'HTTP API', 'swpmail' ), 'img' => 'mailersend.svg' ),
	array( 'key' => 'smtp2go',      'name' => 'SMTP2GO',       'desc' => __( 'HTTP API', 'swpmail' ), 'img' => 'smtp2go.svg' ),
	array( 'key' => 'sparkpost',    'name' => 'SparkPost',     'desc' => __( 'HTTP API', 'swpmail' ), 'img' => 'sparkpost.svg' ),
	array( 'key' => 'zoho',         'name' => 'Zoho Mail',     'desc' => __( 'SMTP', 'swpmail' ),     'img' => 'zoho.svg' ),
);
?>
<div class="swpm-provider-grid<?php echo $swpm_grid_extra_class ? ' ' . esc_attr( $swpm_grid_extra_class ) : ''; ?>">
	<?php foreach ( $swpm_providers as $p ) : ?>
		<button type="button" class="swpm-provider-option<?php echo $swpm_grid_active_key === $p['key'] ? ' active' : ''; ?>" data-provider="<?php echo esc_attr( $p['key'] ); ?>">
			<?php if ( ! empty( $p['img'] ) ) : ?>
				<span class="swpm-provider-option__icon swpm-provider-option__icon--has-logo">
					<img src="<?php echo esc_url( $img_url . $p['img'] ); ?>" alt="<?php echo esc_attr( $p['name'] ); ?>">
				</span>
			<?php else : ?>
				<span class="swpm-provider-option__icon">
					<?php echo $p['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — static dashicon HTML. ?>
				</span>
			<?php endif; ?>
			<span class="swpm-provider-option__name"><?php echo esc_html( $p['name'] ); ?></span>
			<span class="swpm-provider-option__desc"><?php echo esc_html( $p['desc'] ); ?></span>
		</button>
	<?php endforeach; ?>
</div>
