<?php
/**
 * Generic SMTP Provider.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Provider_SMTP implements SWPM_Provider_Interface {

	public function get_key(): string {
		return 'smtp';
	}

	public function get_label(): string {
		return __( 'Generic SMTP', 'swpmail' );
	}

	/**
	 * Send email via SMTP (uses wp_mail with phpmailer_init hook).
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

		// Prevent recursive override.
		add_filter( 'swpm_skip_override', '__return_true' );
		try {
			$sent = wp_mail( $to, $subject, $body, $headers, $attachments );
		} finally {
			remove_filter( 'swpm_skip_override', '__return_true' );
		}

		return $sent
			? SWPM_Send_Result::success()
			: SWPM_Send_Result::failure( 'wp_mail() returned false', 'WP_MAIL_FAILED' );
	}

	public function test_connection(): SWPM_Send_Result {
		$test_email = get_option( 'admin_email' );
		return $this->send(
			$test_email,
			__( 'SWPMail SMTP Test', 'swpmail' ),
			__( 'SMTP connection is working correctly.', 'swpmail' )
		);
	}

	/**
	 * Configure PHPMailer for SMTP — hooked on phpmailer_init.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	public function configure_phpmailer( object $phpmailer ): void {
		$host       = get_option( 'swpm_smtp_host', '' );
		$port       = (int) get_option( 'swpm_smtp_port', 587 );
		$username   = get_option( 'swpm_smtp_username', '' );
		$password   = swpm_decrypt( get_option( 'swpm_smtp_password_enc', '' ) );
		$encryption = get_option( 'swpm_smtp_encryption', 'tls' );
		$from_email = get_option( 'swpm_from_email', get_option( 'admin_email' ) );
		$from_name  = get_option( 'swpm_from_name', get_bloginfo( 'name' ) );

		$phpmailer->isSMTP();
		$phpmailer->Host       = sanitize_text_field( $host );
		$phpmailer->Port       = $port;
		$phpmailer->SMTPAuth   = ! empty( $username );
		$phpmailer->Username   = sanitize_user( $username );
		$phpmailer->Password   = $password;
		$phpmailer->SMTPSecure = in_array( $encryption, array( 'tls', 'ssl', '' ), true )
								 ? $encryption : 'tls';

		if ( ! empty( $from_email ) && is_email( $from_email ) ) {
			try {
				$phpmailer->setFrom( $from_email, $from_name );
			} catch ( \Exception $e ) {
				swpm_log( 'error', 'Failed to set From address: ' . $e->getMessage() );
			}
		}

		$phpmailer->Timeout = (int) apply_filters( 'swpm_smtp_timeout', 15 );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$phpmailer->SMTPDebug   = 1;
			$phpmailer->Debugoutput = function ( $str, $level ) {
				// Never log authentication credentials.
				$lower = strtolower( $str );
				if (
					false !== strpos( $lower, 'auth' ) ||
					false !== strpos( $lower, 'password' ) ||
					false !== strpos( $lower, 'username' ) ||
					preg_match( '/^\d{3}\s+[a-zA-Z0-9+\/=]{20,}/', trim( $str ) )
				) {
					swpm_log( 'debug', "SMTP [{$level}]: [AUTH REDACTED]" );
					return;
				}
				swpm_log( 'debug', "SMTP [{$level}]: " . trim( $str ) );
			};
		}
	}
}
