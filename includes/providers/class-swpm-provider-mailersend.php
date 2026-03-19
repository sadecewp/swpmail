<?php
/**
 * MailerSend Provider.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SWPM_Provider_MailerSend.
 */
class SWPM_Provider_MailerSend implements SWPM_Provider_Interface {

	/**
	 * Get key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'mailersend';
	}

	/**
	 * Get label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'MailerSend', 'swpmail' );
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

		$api_token  = swpm_decrypt( get_option( 'swpm_mailersend_api_token_enc', '' ) );
		$from_email = sanitize_email( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) );
		$from_name  = sanitize_text_field( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) );

		if ( ! is_email( $from_email ) ) {
			return SWPM_Send_Result::failure( 'Invalid from email address.', 'INVALID_FROM_EMAIL' );
		}

		if ( empty( $api_token ) ) {
			return SWPM_Send_Result::failure(
				__( 'MailerSend API token is not configured.', 'swpmail' ),
				'MISSING_CONFIG'
			);
		}

		$payload = array(
			'from'    => array(
				'email' => $from_email,
				'name'  => $from_name,
			),
			'to'      => array(
				array( 'email' => $to ),
			),
			'subject' => $subject,
			'html'    => $body,
			'text'    => wp_strip_all_tags( $body ),
		);

		$reply_to = $this->extract_header( $headers, 'Reply-To' );
		if ( is_email( $reply_to ) ) {
			$payload['reply_to'] = array( 'email' => $reply_to );
		}

		if ( ! empty( $attachments ) ) {
			$payload['attachments'] = $this->build_attachments( $attachments );
		}

		$response = wp_remote_post(
			'https://api.mailersend.com/v1/email',
			array(
				'timeout'     => (int) apply_filters( 'swpm_api_timeout', 20 ),
				'httpversion' => '1.1',
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_token,
					'Content-Type'  => 'application/json',
				),
				'body'        => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return SWPM_Send_Result::failure( $response->get_error_message(), 'WP_HTTP_ERROR' );
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ( $status >= 200 && $status < 300 ) {
			$msg_id = wp_remote_retrieve_header( $response, 'x-message-id' );
			return SWPM_Send_Result::success( $msg_id ? $msg_id : '' );
		}

		$parsed = json_decode( wp_remote_retrieve_body( $response ), true );
		$error  = $parsed['message'] ?? wp_remote_retrieve_body( $response );

		return SWPM_Send_Result::failure(
			"MailerSend API Error {$status}: {$error}",
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
			__( 'SWPMail MailerSend Test', 'swpmail' ),
			__( 'MailerSend connection is working correctly.', 'swpmail' )
		);
	}

	/**
	 * Build attachments.
	 *
	 * @param array $files Files.
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
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'content'     => base64_encode( $content ),
				'filename'    => basename( $file ),
				'disposition' => 'attachment',
			);
		}
		return $result;
	}

	/**
	 * Extract header.
	 *
	 * @param array  $headers Headers.
	 * @param string $name Name.
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
