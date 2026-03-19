<?php
/**
 * Provider factory.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Factory for creating mail provider instances.
 */
class SWPM_Provider_Factory {

	/**
	 * Provider registry mapping keys to class names.
	 *
	 * @var array<string, string>
	 */
	private array $registry = array(
		'phpmail'      => 'SWPM_Provider_PHPMail',
		'smtp'         => 'SWPM_Provider_SMTP',
		'sendlayer'    => 'SWPM_Provider_SendLayer',
		'smtpcom'      => 'SWPM_Provider_SMTPcom',
		'gmail'        => 'SWPM_Provider_Gmail',
		'outlook'      => 'SWPM_Provider_Outlook',
		'mailgun'      => 'SWPM_Provider_Mailgun',
		'sendgrid'     => 'SWPM_Provider_SendGrid',
		'postmark'     => 'SWPM_Provider_Postmark',
		'brevo'        => 'SWPM_Provider_Brevo',
		'ses'          => 'SWPM_Provider_SES',
		'resend'       => 'SWPM_Provider_Resend',
		'elasticemail' => 'SWPM_Provider_ElasticEmail',
		'mailjet'      => 'SWPM_Provider_Mailjet',
		'mailersend'   => 'SWPM_Provider_MailerSend',
		'smtp2go'      => 'SWPM_Provider_SMTP2GO',
		'sparkpost'    => 'SWPM_Provider_SparkPost',
		'zoho'         => 'SWPM_Provider_Zoho',
	);

	/**
	 * Create the configured provider instance.
	 *
	 * @return SWPM_Provider_Interface
	 */
	public function make(): SWPM_Provider_Interface {
		$key = sanitize_key( get_option( 'swpm_mail_provider', 'phpmail' ) );

		/**
		 * Allow extending the provider registry.
		 *
		 * @since 1.0.0
		 * @param array $registry Key => Class name mappings.
		 */
		$this->registry = apply_filters( 'swpm_provider_registry', $this->registry );

		if ( ! isset( $this->registry[ $key ] ) ) {
			swpm_log( 'warning', "Unknown provider '{$key}', falling back to PHP Mail." );
			$key = 'phpmail';
		}

		$class = $this->registry[ $key ];

		if ( ! class_exists( $class ) ) {
			swpm_log( 'error', "Provider class '{$class}' not found." );
			return new SWPM_Provider_PHPMail();
		}

		return new $class();
	}

	/**
	 * Get all registered providers.
	 *
	 * @return array<string, string>
	 */
	public function get_all(): array {
		return $this->registry;
	}
}
