<?php
/**
 * PHP Mail (Default) Provider.
 *
 * Uses WordPress's default wp_mail() with PHP's built-in mail() function.
 * No SMTP configuration required.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Provider_PHPMail implements SWPM_Provider_Interface {

	public function get_key(): string {
		return 'phpmail';
	}

	public function get_label(): string {
		return __( 'PHP Mail (Default)', 'swpmail' );
	}

	/**
	 * Send email via PHP's native mail() through wp_mail.
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

		// Ensure PHPMailer uses PHP mail() and not SMTP.
		$reset_mailer = function ( $phpmailer ) {
			$phpmailer->isMail();

			$from_email = get_option( 'swpm_from_email', get_option( 'admin_email' ) );
			$from_name  = get_option( 'swpm_from_name', get_bloginfo( 'name' ) );

			if ( ! empty( $from_email ) && is_email( $from_email ) ) {
				try {
					$phpmailer->setFrom( $from_email, $from_name );
				} catch ( \Exception $e ) {
					swpm_log( 'error', 'Failed to set From address: ' . $e->getMessage() );
				}
			}
		};

		add_action( 'phpmailer_init', $reset_mailer, 99 );

		try {
			$sent = wp_mail( $to, $subject, $body, $headers, $attachments );
		} finally {
			remove_filter( 'swpm_skip_override', '__return_true' );
			remove_action( 'phpmailer_init', $reset_mailer, 99 );
		}

		return $sent
			? SWPM_Send_Result::success()
			: SWPM_Send_Result::failure( 'wp_mail() returned false', 'PHP_MAIL_FAILED' );
	}

	public function test_connection(): SWPM_Send_Result {
		$test_email = get_option( 'admin_email' );
		return $this->send(
			$test_email,
			__( 'SWPMail PHP Mail Test', 'swpmail' ),
			__( 'PHP mail() connection is working correctly.', 'swpmail' )
		);
	}
}
