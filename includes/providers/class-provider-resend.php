<?php
/**
 * Resend Provider.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Provider_Resend implements SWPM_Provider_Interface {

	public function get_key(): string {
		return 'resend';
	}

	public function get_label(): string {
		return __( 'Resend', 'swpmail' );
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

		$api_key    = swpm_decrypt( get_option( 'swpm_resend_api_key_enc', '' ) );
		$from_email = get_option( 'swpm_from_email', get_option( 'admin_email' ) );
		$from_name  = get_option( 'swpm_from_name', get_bloginfo( 'name' ) );

		if ( empty( $api_key ) ) {
			return SWPM_Send_Result::failure(
				__( 'Resend API key is not configured.', 'swpmail' ),
				'MISSING_CONFIG'
			);
		}

		$safe_from_name = '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $from_name ) . '"';

		$payload = array(
			'from'    => "{$safe_from_name} <{$from_email}>",
			'to'      => array( $to ),
			'subject' => $subject,
			'html'    => $body,
			'text'    => wp_strip_all_tags( $body ),
		);

		$reply_to = $this->extract_header( $headers, 'Reply-To' );
		if ( is_email( $reply_to ) ) {
			$payload['reply_to'] = array( $reply_to );
		}

		if ( ! empty( $attachments ) ) {
			$payload['attachments'] = $this->build_attachments( $attachments );
		}

		$response = wp_remote_post( 'https://api.resend.com/emails', array(
			'timeout'     => (int) apply_filters( 'swpm_api_timeout', 20 ),
			'httpversion' => '1.1',
			'headers'     => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'        => wp_json_encode( $payload ),
		) );

		if ( is_wp_error( $response ) ) {
			return SWPM_Send_Result::failure( $response->get_error_message(), 'WP_HTTP_ERROR' );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$parsed = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 200 && $status < 300 ) {
			return SWPM_Send_Result::success( $parsed['id'] ?? '', $parsed ?: array() );
		}

		$error_name    = $parsed['name'] ?? 'UnknownError';
		$error_message = $parsed['message'] ?? wp_remote_retrieve_body( $response );

		return SWPM_Send_Result::failure(
			"Resend API Error {$status}: {$error_message}",
			"HTTP_{$status}",
			$parsed ?: array()
		);
	}

	public function test_connection(): SWPM_Send_Result {
		return $this->send(
			get_option( 'admin_email' ),
			__( 'SWPMail Resend Test', 'swpmail' ),
			__( 'Resend connection is working correctly.', 'swpmail' )
		);
	}

	/**
	 * Build attachments array for Resend.
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
				'filename' => basename( $file ),
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'content'  => base64_encode( $content ),
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
