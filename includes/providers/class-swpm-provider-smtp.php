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

/**
 * SMTP email provider via PHPMailer.
 */
class SWPM_Provider_SMTP implements SWPM_Provider_Interface {

	/**
	 * Get key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'smtp';
	}

	/**
	 * Get label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Generic SMTP', 'swpmail' );
	}

	/**
	 * Send email via SMTP (uses wp_mail with phpmailer_init hook).
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

		// Capture the real PHPMailer error when wp_mail() fails.
		$last_error        = '';
		$phpmailer_ref     = null;
		$error_catcher     = function ( $wp_error ) use ( &$last_error ) {
			if ( is_wp_error( $wp_error ) ) {
				$msg  = $wp_error->get_error_message();
				$data = $wp_error->get_error_data();
				if ( ! empty( $data['phpmailer_exception_code'] ) ) {
					$msg .= ' (code ' . $data['phpmailer_exception_code'] . ')';
				}
				$last_error = $msg;
			}
		};
		$capture_phpmailer = function ( $phpmailer ) use ( &$phpmailer_ref ) {
			$phpmailer_ref = $phpmailer;
		};

		add_action( 'wp_mail_failed', $error_catcher );
		add_action( 'phpmailer_init', $capture_phpmailer, 999 );

		// Prevent recursive override.
		add_filter( 'swpm_skip_override', '__return_true' );

		// Ensure PHPMailer is configured even if the global hook was not registered
		// (e.g. first setup when swpm_mail_provider was empty at plugins_loaded).
		$configure_cb = array( $this, 'configure_phpmailer' );
		add_action( 'phpmailer_init', $configure_cb, 99999 );
		try {
			$sent = wp_mail( $to, $subject, $body, $headers, $attachments );
		} finally {
			remove_action( 'phpmailer_init', $configure_cb, 99999 );
			remove_action( 'phpmailer_init', $capture_phpmailer, 999 );
			remove_filter( 'swpm_skip_override', '__return_true' );
			remove_action( 'wp_mail_failed', $error_catcher );
		}

		if ( $sent ) {
			return SWPM_Send_Result::success();
		}

		// Try to get the most informative error message available.
		if ( empty( $last_error ) && $phpmailer_ref && ! empty( $phpmailer_ref->ErrorInfo ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$last_error = $phpmailer_ref->ErrorInfo; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
		$message = ! empty( $last_error ) ? $last_error : 'wp_mail() returned false';
		return SWPM_Send_Result::failure( $message, 'WP_MAIL_FAILED' );
	}

	/**
	 * Test connection.
	 *
	 * @return SWPM_Send_Result
	 */
	public function test_connection(): SWPM_Send_Result {
		$test_email = get_option( 'admin_email' );
		return $this->send(
			$test_email,
			__( 'SWPMail SMTP Test', 'swpmail' ),
			__( 'SMTP connection is working correctly.', 'swpmail' )
		);
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	/**
	 * Configure PHPMailer for SMTP — hooked on phpmailer_init.
	 *
	 * @param object $phpmailer PHPMailer instance.
	 */





	/**
	 * Configure phpmailer.
	 *
	 * @param object $phpmailer Phpmailer.
	 */
	public function configure_phpmailer( object $phpmailer ): void {
		$host       = get_option( 'swpm_smtp_host', '' );
		$port       = (int) get_option( 'swpm_smtp_port', 587 );
		$username   = get_option( 'swpm_smtp_username', '' );
		$password   = swpm_decrypt( get_option( 'swpm_smtp_password_enc', '' ) );
		$encryption = get_option( 'swpm_smtp_encryption', 'tls' );
		$from_email = sanitize_email( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) );
		$from_name  = sanitize_text_field( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) );

// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$phpmailer->isSMTP();
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Host = sanitize_text_field( $host );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Port = $port;
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPAuth = ! empty( $username );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Username = sanitize_user( $username );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Password = $password;
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPSecure = in_array( $encryption, array( 'tls', 'ssl', '' ), true )
								? $encryption : 'tls';

		if ( ! empty( $from_email ) && is_email( $from_email ) ) {
			try {
				$phpmailer->setFrom( $from_email, $from_name );
			} catch ( \Exception $e ) {
				swpm_log( 'error', 'Failed to set From address: ' . $e->getMessage() );
			}
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Timeout = (int) apply_filters( 'swpm_smtp_timeout', 15 );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->SMTPDebug = 1;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
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
