<?php
/**
 * Brevo (Sendinblue) Provider.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SWPM_Provider_Brevo.
 */
class SWPM_Provider_Brevo implements SWPM_Provider_Interface {

	/**
	 * Get key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'brevo';
	}

	/**
	 * Get label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Brevo (Sendinblue)', 'swpmail' );
	}

	/**
	 * Send email via the provider.
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

		$api_key    = swpm_decrypt( get_option( 'swpm_brevo_api_key_enc', '' ) );
		$from_email = sanitize_email( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) );
		$from_name  = sanitize_text_field( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) );

		if ( ! is_email( $from_email ) ) {
			return SWPM_Send_Result::failure( 'Invalid from email address.', 'INVALID_FROM_EMAIL' );
		}

		if ( empty( $api_key ) ) {
			return SWPM_Send_Result::failure(
				__( 'Brevo API key is not configured.', 'swpmail' ),
				'MISSING_CONFIG'
			);
		}

		$payload = array(
			'sender'      => array(
				'name'  => $from_name,
				'email' => $from_email,
			),
			'to'          => array( array( 'email' => $to ) ),
			'subject'     => $subject,
			'htmlContent' => $body,
			'textContent' => wp_strip_all_tags( $body ),
		);

		$reply_to = $this->extract_header( $headers, 'Reply-To' );
		if ( is_email( $reply_to ) ) {
			$payload['replyTo'] = array( 'email' => $reply_to );
		}

		if ( ! empty( $attachments ) ) {
			$payload['attachment'] = $this->build_attachments( $attachments );
		}

		$response = wp_remote_post(
			'https://api.brevo.com/v3/smtp/email',
			array(
				'timeout'     => (int) apply_filters( 'swpm_api_timeout', 20 ),
				'httpversion' => '1.1',
				'headers'     => array(
					'accept'       => 'application/json',
					'api-key'      => $api_key,
					'content-type' => 'application/json',
				),
				'body'        => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return SWPM_Send_Result::failure( $response->get_error_message(), 'WP_HTTP_ERROR' );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$parsed = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 200 && $status < 300 ) {
			return SWPM_Send_Result::success( $parsed['messageId'] ?? '', $parsed ? $parsed : array() );
		}

		$error_msg = $parsed['message'] ?? wp_remote_retrieve_body( $response );
		return SWPM_Send_Result::failure(
			"Brevo API Error {$status}: {$error_msg}",
			"HTTP_{$status}",
			$parsed ? $parsed : array()
		);
	}

	/**
	 * Test connection.
	 *
	 * @return SWPM_Send_Result
	 */
	public function test_connection(): SWPM_Send_Result {
		return $this->send(
			get_option( 'admin_email' ),
			__( 'SWPMail Brevo Test', 'swpmail' ),
			__( 'Brevo connection is working correctly.', 'swpmail' )
		);
	}

	/**
	 * Build attachments array for Brevo.
	 *
	 * @param array $files File paths.
	 * @return array
	 */
	private function build_attachments( array $files ): array {
		$result = array();
		foreach ( $files as $file ) {
			$real = swpm_validate_attachment( $file );
			if ( ! $real ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content = file_get_contents( $real );
			if ( false === $content ) {
				continue;
			}
			$result[] = array(
				'name'    => basename( $file ),
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'content' => base64_encode( $content ),
			);
		}
		return $result;
	}

	/**
	 * Extract a header value.
	 *
	 * @param array  $headers Headers.
	 * @param string $name    Header name.
	 * @return string
	 */
	private function extract_header( array $headers, string $name ): string {
		foreach ( $headers as $h ) {
			if ( stripos( $h, "{$name}:" ) === 0 ) {
				return swpm_sanitize_header_value( trim( substr( $h, strlen( $name ) + 1 ) ) );
			}
		}
		return '';
	}
}
