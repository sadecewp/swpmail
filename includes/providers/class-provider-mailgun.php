<?php
/**
 * Mailgun Provider.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Provider_Mailgun implements SWPM_Provider_Interface {

	public function get_key(): string {
		return 'mailgun';
	}

	public function get_label(): string {
		return __( 'Mailgun', 'swpmail' );
	}

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

		$api_key    = swpm_decrypt( get_option( 'swpm_mailgun_api_key_enc', '' ) );
		$domain     = sanitize_text_field( get_option( 'swpm_mailgun_domain', '' ) );
		$region     = get_option( 'swpm_mailgun_region', 'us' );
		$from_email = sanitize_email( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) );
		$from_name  = sanitize_text_field( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) );

		if ( ! is_email( $from_email ) ) {
			return SWPM_Send_Result::failure( 'Invalid from email address.', 'INVALID_FROM_EMAIL' );
		}

		if ( empty( $api_key ) || empty( $domain ) ) {
			return SWPM_Send_Result::failure(
				__( 'Mailgun API key or domain is not configured.', 'swpmail' ),
				'MISSING_CONFIG'
			);
		}

		// Validate domain format to prevent URL injection.
		if ( ! preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain ) ) {
			return SWPM_Send_Result::failure(
				__( 'Invalid Mailgun domain format.', 'swpmail' ),
				'INVALID_DOMAIN'
			);
		}

		$base_url = 'eu' === $region
			? 'https://api.eu.mailgun.net/v3'
			: 'https://api.mailgun.net/v3';

		$endpoint = "{$base_url}/{$domain}/messages";

		$safe_from_name = '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $from_name ) . '"';

		$body_params = array(
			'from'    => "{$safe_from_name} <{$from_email}>",
			'to'      => $to,
			'subject' => $subject,
			'html'    => $body,
			'text'    => wp_strip_all_tags( $body ),
		);

		$reply_to = $this->extract_reply_to( $headers );
		if ( $reply_to ) {
			$body_params['h:Reply-To'] = $reply_to;
		}

		$request_args = array(
			'timeout'     => (int) apply_filters( 'swpm_api_timeout', 20 ),
			'httpversion' => '1.1',
			'headers'     => array(
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Authorization' => 'Basic ' . base64_encode( 'api:' . $api_key ),
			),
			'body'        => $body_params,
		);

		$response = wp_remote_post( $endpoint, $request_args );

		return $this->parse_response( $response );
	}

	public function test_connection(): SWPM_Send_Result {
		return $this->send(
			get_option( 'admin_email' ),
			__( 'SWPMail Mailgun Test', 'swpmail' ),
			__( 'Mailgun connection is working correctly.', 'swpmail' )
		);
	}

	/**
	 * Parse API response.
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @return SWPM_Send_Result
	 */
	private function parse_response( $response ): SWPM_Send_Result {
		if ( is_wp_error( $response ) ) {
			return SWPM_Send_Result::failure(
				$response->get_error_message(),
				'WP_HTTP_ERROR'
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$parsed      = json_decode( $body, true );

		if ( $status_code >= 200 && $status_code < 300 ) {
			$message_id = $parsed['id'] ?? '';
			return SWPM_Send_Result::success( $message_id, $parsed ?: array() );
		}

		$error_msg = $parsed['message'] ?? $body;

		return SWPM_Send_Result::failure(
			"Mailgun API Error {$status_code}: {$error_msg}",
			"HTTP_{$status_code}",
			$parsed ?: array()
		);
	}

	/**
	 * Extract Reply-To from headers array.
	 *
	 * @param array $headers Headers.
	 * @return string
	 */
	private function extract_reply_to( array $headers ): string {
		foreach ( $headers as $header ) {
			if ( stripos( $header, 'Reply-To:' ) === 0 ) {
				return swpm_sanitize_header_value( trim( substr( $header, strlen( 'Reply-To:' ) ) ) );
			}
		}
		return '';
	}
}
