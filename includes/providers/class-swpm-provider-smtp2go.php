<?php
/**
 * SMTP2GO Provider.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SWPM_Provider_SMTP2GO.
 */
class SWPM_Provider_SMTP2GO implements SWPM_Provider_Interface {

	/**
	 * Get key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'smtp2go';
	}

	/**
	 * Get label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'SMTP2GO', 'swpmail' );
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

		$api_key    = swpm_decrypt( get_option( 'swpm_smtp2go_api_key_enc', '' ) );
		$from_email = sanitize_email( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) );
		$from_name  = sanitize_text_field( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) );

		if ( ! is_email( $from_email ) ) {
			return SWPM_Send_Result::failure( 'Invalid from email address.', 'INVALID_FROM_EMAIL' );
		}

		if ( empty( $api_key ) ) {
			return SWPM_Send_Result::failure(
				__( 'SMTP2GO API key is not configured.', 'swpmail' ),
				'MISSING_CONFIG'
			);
		}

		$sender = ! empty( $from_name ) ? "{$from_name} <{$from_email}>" : $from_email;

		$payload = array(
			'api_key'   => $api_key,
			'sender'    => $sender,
			'to'        => array( $to ),
			'subject'   => $subject,
			'html_body' => $body,
			'text_body' => wp_strip_all_tags( $body ),
		);

		$reply_to = $this->extract_header( $headers, 'Reply-To' );
		if ( is_email( $reply_to ) ) {
			$payload['custom_headers'] = array(
				array(
					'header' => 'Reply-To',
					'value'  => $reply_to,
				),
			);
		}

		if ( ! empty( $attachments ) ) {
			$payload['attachments'] = $this->build_attachments( $attachments );
		}

		$response = wp_remote_post(
			'https://api.smtp2go.com/v3/email/send',
			array(
				'timeout'     => (int) apply_filters( 'swpm_api_timeout', 20 ),
				'httpversion' => '1.1',
				'headers'     => array(
					'Content-Type' => 'application/json',
				),
				'body'        => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return SWPM_Send_Result::failure( $response->get_error_message(), 'WP_HTTP_ERROR' );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$parsed = json_decode( wp_remote_retrieve_body( $response ), true );

		$data = $parsed['data'] ?? array();

		if ( $status >= 200 && $status < 300 && ( $data['succeeded'] ?? 0 ) > 0 ) {
			$msg_id = $data['email_id'] ?? '';
			return SWPM_Send_Result::success( $msg_id, $parsed ? $parsed : array() );
		}

		$error = $data['error'] ?? $parsed['data']['error_code'] ?? wp_remote_retrieve_body( $response );

		return SWPM_Send_Result::failure(
			"SMTP2GO API Error {$status}: {$error}",
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
			__( 'SWPMail SMTP2GO Test', 'swpmail' ),
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			__( 'SMTP2GO connection is working correctly.', 'swpmail' )
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
				'filename' => basename( $file ),
				'fileblob' => base64_encode( $content ),
				// phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				'mimetype' => ( new \finfo( FILEINFO_MIME_TYPE ) )->file( $real ) ?: 'application/octet-stream',
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
