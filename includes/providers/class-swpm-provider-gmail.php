<?php
/**
 * Google / Gmail Provider.
 *
 * Supports two authentication modes:
 *   1. OAuth 2.0 (XOAUTH2) — recommended, uses access tokens.
 *   2. App Password (legacy SMTP) — fallback when OAuth is not configured.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gmail SMTP email provider with OAuth.
 */
class SWPM_Provider_Gmail implements SWPM_Provider_Interface {

	/**
	 * Get key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'gmail';
	}

	/**
	 * Get label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Google / Gmail', 'swpmail' );
	}

	/**
	 * Whether OAuth is the active authentication mode.
	 *
	 * @return bool
	 */
	public function is_oauth_mode(): bool {
		/* @var SWPM_OAuth_Manager|null $oauth */
		$oauth = swpm( 'oauth' );
		return $oauth && $oauth->is_connected( 'gmail' );
	}

	/**
	 * Send email via Gmail SMTP.
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

		if ( $this->is_oauth_mode() ) {
			return $this->send_oauth( $to, $subject, $body, $headers, $attachments );
		}

		return $this->send_app_password( $to, $subject, $body, $headers, $attachments );
	}

	/**
	 * Test connection.
	 *
	 * @return SWPM_Send_Result
	 */
	public function test_connection(): SWPM_Send_Result {
		return $this->send(
			get_option( 'admin_email' ),
			__( 'SWPMail Gmail Test', 'swpmail' ),
			__( 'Gmail SMTP connection is working correctly.', 'swpmail' )
		);
	}

	// ------------------------------------------------------------------
	// OAuth (XOAUTH2) Mode
	// ----------------------------------------------------------------

	/**
	 * Send email via Gmail SMTP using OAuth XOAUTH2.
	 *
	 * @param string $to          Recipient email address.
	 * @param string $subject     Email subject line.
	 * @param string $body        Email body content.
	 * @param array  $headers     Email headers.
	 * @param array  $attachments File attachments.
	 *
	 * @return SWPM_Send_Result
	 */
	private function send_oauth(
		string $to,
		string $subject,
		string $body,
		array $headers,
		array $attachments
	): SWPM_Send_Result {
		/* @var SWPM_OAuth_Manager $oauth */
		$oauth        = swpm( 'oauth' );
		$access_token = $oauth->get_access_token( 'gmail' );

		if ( empty( $access_token ) ) {
			return SWPM_Send_Result::failure(
				__( 'Gmail OAuth access token is unavailable. Please re-authorize.', 'swpmail' ),
				'OAUTH_TOKEN_MISSING'
			);
		}

		$username = $oauth->get_authenticated_email( 'gmail' );
		if ( empty( $username ) ) {
			$username = get_option( 'swpm_gmail_username', '' );
		}

		// Store token + username temporarily for the phpmailer_init callback.
		$this->_oauth_token    = $access_token;
		$this->_oauth_username = $username;

		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer_oauth' ), 99 );
		add_filter( 'swpm_skip_override', '__return_true' );

		try {
			$sent = wp_mail( $to, $subject, $body, $headers, $attachments );
		} finally {
			remove_filter( 'swpm_skip_override', '__return_true' );
			remove_action( 'phpmailer_init', array( $this, 'configure_phpmailer_oauth' ), 99 );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			unset( $this->_oauth_token, $this->_oauth_username );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		return $sent
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			? SWPM_Send_Result::success()
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			: SWPM_Send_Result::failure( 'wp_mail() returned false', 'GMAIL_OAUTH_SEND_FAILED' );
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Configure PHPMailer for Gmail SMTP with XOAUTH2.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint





	/**
	 * Configure phpmailer oauth.
	 *
	 * @param object $phpmailer Phpmailer.
	 */
	public function configure_phpmailer_oauth( object $phpmailer ): void {
		$from_email = sanitize_email( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) );
		$from_name  = sanitize_text_field( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) );

// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->isSMTP();
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Host = 'smtp.gmail.com';
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Port = 587;
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPAuth = true;
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPSecure = 'tls';
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->AuthType = 'XOAUTH2';
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Username = sanitize_email( $this->_oauth_username ?? '' );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Password = $this->_oauth_token ?? '';

		if ( ! empty( $from_email ) && is_email( $from_email ) ) {
			try {
				$phpmailer->setFrom( $from_email, $from_name );
			} catch ( \Exception $e ) {
				swpm_log( 'error', 'Failed to set From address: ' . $e->getMessage() );
			}
		}
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Timeout = (int) apply_filters( 'swpm_smtp_timeout', 15 );
	}

	// ------------------------------------------------------------------
	// App Password (Legacy) Mode
	// ----------------------------------------------------------------

	/**
	 * Send email via Gmail SMTP using App Password.
	 *
	 * @param string $to          Recipient email address.
	 * @param string $subject     Email subject line.
	 * @param string $body        Email body content.
	 * @param array  $headers     Email headers.
	 * @param array  $attachments File attachments.
	 *
	 * @return SWPM_Send_Result
	 */
	private function send_app_password(
		string $to,
		string $subject,
		string $body,
		array $headers,
		array $attachments
	): SWPM_Send_Result {
		$username = get_option( 'swpm_gmail_username', '' );
		$password = swpm_decrypt( get_option( 'swpm_gmail_app_password_enc', '' ) );

		if ( empty( $username ) || empty( $password ) ) {
			return SWPM_Send_Result::failure(
				__( 'Gmail username or app password is not configured.', 'swpmail' ),
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

// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		return $sent
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			? SWPM_Send_Result::success()
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			: SWPM_Send_Result::failure( 'wp_mail() returned false', 'GMAIL_SEND_FAILED' );
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

	/**
	 * Configure PHPMailer for Gmail SMTP with App Password.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint





	/**
	 * Configure phpmailer.
	 *
	 * @param object $phpmailer Phpmailer.
	 */
	public function configure_phpmailer( object $phpmailer ): void {
		$username   = get_option( 'swpm_gmail_username', '' );
		$password   = swpm_decrypt( get_option( 'swpm_gmail_app_password_enc', '' ) );
		$from_email = get_option( 'swpm_from_email', get_option( 'admin_email' ) );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$from_name = get_option( 'swpm_from_name', get_bloginfo( 'name' ) );
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->isSMTP();
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Host = 'smtp.gmail.com';
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
			} catch ( \Exception $e ) {
				swpm_log( 'error', 'Failed to set From address: ' . $e->getMessage() );
			}
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Timeout = (int) apply_filters( 'swpm_smtp_timeout', 15 );
	}
}
