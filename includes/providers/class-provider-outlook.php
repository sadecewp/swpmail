<?php
/**
 * Microsoft 365 / Outlook Provider.
 *
 * Supports two authentication modes:
 *   1. OAuth 2.0 (XOAUTH2) — recommended, uses Microsoft Identity Platform v2.0.
 *   2. Password (legacy SMTP) — fallback when OAuth is not configured.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Provider_Outlook implements SWPM_Provider_Interface {

	public function get_key(): string {
		return 'outlook';
	}

	public function get_label(): string {
		return __( '365 / Outlook', 'swpmail' );
	}

	/**
	 * Whether OAuth is the active authentication mode.
	 *
	 * @return bool
	 */
	public function is_oauth_mode(): bool {
		/** @var SWPM_OAuth_Manager|null $oauth */
		$oauth = swpm( 'oauth' );
		return $oauth && $oauth->is_connected( 'outlook' );
	}

	/**
	 * Send email via Microsoft 365 / Outlook SMTP.
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

		return $this->send_password( $to, $subject, $body, $headers, $attachments );
	}

	public function test_connection(): SWPM_Send_Result {
		return $this->send(
			get_option( 'admin_email' ),
			__( 'SWPMail Outlook Test', 'swpmail' ),
			__( 'Microsoft 365 / Outlook SMTP connection is working correctly.', 'swpmail' )
		);
	}

	/* ------------------------------------------------------------------
	 * OAuth (XOAUTH2) Mode
	 * ----------------------------------------------------------------*/

	/**
	 * Send email via Outlook SMTP using OAuth XOAUTH2.
	 */
	private function send_oauth(
		string $to,
		string $subject,
		string $body,
		array $headers,
		array $attachments
	): SWPM_Send_Result {
		/** @var SWPM_OAuth_Manager $oauth */
		$oauth        = swpm( 'oauth' );
		$access_token = $oauth->get_access_token( 'outlook' );

		if ( empty( $access_token ) ) {
			return SWPM_Send_Result::failure(
				__( 'Outlook OAuth access token is unavailable. Please re-authorize.', 'swpmail' ),
				'OAUTH_TOKEN_MISSING'
			);
		}

		$username = $oauth->get_authenticated_email( 'outlook' );
		if ( empty( $username ) ) {
			$username = get_option( 'swpm_outlook_username', '' );
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
			unset( $this->_oauth_token, $this->_oauth_username );
		}

		return $sent
			? SWPM_Send_Result::success()
			: SWPM_Send_Result::failure( 'wp_mail() returned false', 'OUTLOOK_OAUTH_SEND_FAILED' );
	}

	/**
	 * Configure PHPMailer for Outlook SMTP with XOAUTH2.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	public function configure_phpmailer_oauth( object $phpmailer ): void {
		$from_email = get_option( 'swpm_from_email', get_option( 'admin_email' ) );
		$from_name  = get_option( 'swpm_from_name', get_bloginfo( 'name' ) );

		$phpmailer->isSMTP();
		$phpmailer->Host       = 'smtp.office365.com';
		$phpmailer->Port       = 587;
		$phpmailer->SMTPAuth   = true;
		$phpmailer->SMTPSecure = 'tls';
		$phpmailer->AuthType   = 'XOAUTH2';
		$phpmailer->Username   = sanitize_email( $this->_oauth_username ?? '' );
		$phpmailer->Password   = $this->_oauth_token ?? '';

		if ( ! empty( $from_email ) && is_email( $from_email ) ) {
			try {
				$phpmailer->setFrom( $from_email, $from_name );
			} catch ( \Exception $e ) {
				swpm_log( 'error', 'Failed to set From address: ' . $e->getMessage() );
			}
		}

		$phpmailer->Timeout = (int) apply_filters( 'swpm_smtp_timeout', 15 );
	}

	/* ------------------------------------------------------------------
	 * Password (Legacy) Mode
	 * ----------------------------------------------------------------*/

	/**
	 * Send email via Outlook SMTP using password.
	 */
	private function send_password(
		string $to,
		string $subject,
		string $body,
		array $headers,
		array $attachments
	): SWPM_Send_Result {
		$username = get_option( 'swpm_outlook_username', '' );
		$password = swpm_decrypt( get_option( 'swpm_outlook_password_enc', '' ) );

		if ( empty( $username ) || empty( $password ) ) {
			return SWPM_Send_Result::failure(
				__( 'Outlook username or password is not configured.', 'swpmail' ),
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
			: SWPM_Send_Result::failure( 'wp_mail() returned false', 'OUTLOOK_SEND_FAILED' );
	}

	/**
	 * Configure PHPMailer for Microsoft 365 / Outlook SMTP with password.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	public function configure_phpmailer( object $phpmailer ): void {
		$username   = get_option( 'swpm_outlook_username', '' );
		$password   = swpm_decrypt( get_option( 'swpm_outlook_password_enc', '' ) );
		$from_email = get_option( 'swpm_from_email', get_option( 'admin_email' ) );
		$from_name  = get_option( 'swpm_from_name', get_bloginfo( 'name' ) );

		$phpmailer->isSMTP();
		$phpmailer->Host       = 'smtp.office365.com';
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
