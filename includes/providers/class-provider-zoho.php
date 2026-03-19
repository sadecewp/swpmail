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

class SWPM_Provider_Zoho implements SWPM_Provider_Interface {

	public function get_key(): string {
		return 'zoho';
	}

	public function get_label(): string {
		return __( 'Zoho Mail', 'swpmail' );
	}

	/**
	 * Send email via Zoho Mail SMTP.
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
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	public function configure_phpmailer( object $phpmailer ): void {
		$username   = get_option( 'swpm_zoho_username', '' );
		$password   = swpm_decrypt( get_option( 'swpm_zoho_password_enc', '' ) );
		$region     = get_option( 'swpm_zoho_region', 'com' );
		$from_email = sanitize_email( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) );
		$from_name  = sanitize_text_field( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) );

		$hosts = array(
			'com'    => 'smtp.zoho.com',
			'eu'     => 'smtp.zoho.eu',
			'in'     => 'smtp.zoho.in',
			'com.au' => 'smtp.zoho.com.au',
			'jp'     => 'smtp.zoho.jp',
		);

		$phpmailer->isSMTP();
		$phpmailer->Host       = $hosts[ $region ] ?? 'smtp.zoho.com';
		$phpmailer->Port       = 587;
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = sanitize_email( $username );
		$phpmailer->Password   = $password;
		$phpmailer->SMTPSecure = 'tls';

		if ( ! empty( $from_email ) && is_email( $from_email ) ) {
			try {
				$phpmailer->setFrom( $from_email, $from_name );
			} catch ( \Exception $e ) {
				swpm_log( 'error', 'Failed to set From address: ' . $e->getMessage() );
			}
		}

		$phpmailer->Timeout = (int) apply_filters( 'swpm_smtp_timeout', 15 );
	}
}
