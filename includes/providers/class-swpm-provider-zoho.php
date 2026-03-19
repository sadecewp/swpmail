<?php
/**
 * Zoho Mail Provider.
 *
 * Pre-configured SMTP for Zoho Mail.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SWPM_Provider_Zoho.
 */
class SWPM_Provider_Zoho implements SWPM_Provider_Interface {

	/**
	 * Get key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'zoho';
	}

	/**
	 * Get label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Zoho Mail', 'swpmail' );
	}

	/**
	 * Send email via Zoho Mail SMTP.
	 *
	 * @param string $to          Recipient email address.
	 * @param string $subject     Email subject line.
	 * @param string $body        Email body content.
	 * @param array  $headers     Optional email headers.
	 * @param array  $attachments Optional file attachments.
	 *
	 * @return SWPM_Send_Result
	 */
	public function send(
		string $to,
		string $subject,
		string $body,
		array $headers = array(),
		array $attachments = array()
	): SWPM_Send_Result {

		if ( ! is_email( $to ) ) {
			return SWPM_Send_Result::failure( 'Invalid recipient email.', 'INVALID_EMAIL' );
		}

		$username = get_option( 'swpm_zoho_username', '' );
		$password = swpm_decrypt( get_option( 'swpm_zoho_password_enc', '' ) );

		if ( empty( $username ) || empty( $password ) ) {
			return SWPM_Send_Result::failure(
				__( 'Zoho Mail email or password is not configured.', 'swpmail' ),
				'MISSING_CONFIG'
			);
		}

		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ), 99 );
		add_filter( 'swpm_skip_override', '__return_true' );

		try {
			$sent = wp_mail( $to, $subject, $body, $headers, $attachments );
		} finally {
			remove_filter( 'swpm_skip_override', '__return_true' );
			remove_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ), 99 );
		}

		return $sent
			? SWPM_Send_Result::success()
			: SWPM_Send_Result::failure( 'wp_mail() returned false', 'ZOHO_SEND_FAILED' );
	}

	/**
	 * Test connection.
	 *
	 * @return SWPM_Send_Result
	 */
	public function test_connection(): SWPM_Send_Result {
		return $this->send(
			get_option( 'admin_email' ),
			__( 'SWPMail Zoho Mail Test', 'swpmail' ),
			__( 'Zoho Mail SMTP connection is working correctly.', 'swpmail' )
		);
	}

	/**
	 * Configure PHPMailer for Zoho Mail SMTP.
	 *
	 // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 *
	 // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	/**
	 * Configure phpmailer.
	 *
	 * @param object $phpmailer Phpmailer.
	 */
	public function configure_phpmailer( object $phpmailer ): void {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$username = get_option( 'swpm_zoho_username', '' );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$password = swpm_decrypt( get_option( 'swpm_zoho_password_enc', '' ) );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$region     = get_option( 'swpm_zoho_region', 'com' );
		$from_email = sanitize_email( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) );
		$from_name  = sanitize_text_field( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) );

		$hosts = array(
			'com'    => 'smtp.zoho.com',
			'eu'     => 'smtp.zoho.eu',
			'in'     => 'smtp.zoho.in',
			'com.au' => 'smtp.zoho.com.au',
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			'jp'     => 'smtp.zoho.jp',
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		);
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->isSMTP();
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Host = $hosts[ $region ] ?? 'smtp.zoho.com';
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Port = 587;
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPAuth = true;
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Username = sanitize_email( $username );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Password = $password;
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPSecure = 'tls';

		if ( ! empty( $from_email ) && is_email( $from_email ) ) {
			try {
				$phpmailer->setFrom( $from_email, $from_name );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			} catch ( \Exception $e ) {
				swpm_log( 'error', 'Failed to set From address: ' . $e->getMessage() );
			}
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Timeout = (int) apply_filters( 'swpm_smtp_timeout', 15 );
	}
}
