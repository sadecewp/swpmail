<?php
/**
 * Postmark Provider.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Provider_Postmark implements SWPM_Provider_Interface {

	public function get_key(): string {
		return 'postmark';
	}

	public function get_label(): string {
		return __( 'Postmark', 'swpmail' );
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

		$api_token  = swpm_decrypt( get_option( 'swpm_postmark_server_token_enc', '' ) );
		$from_email = get_option( 'swpm_from_email', get_option( 'admin_email' ) );
		$from_name  = get_option( 'swpm_from_name', get_bloginfo( 'name' ) );
		$stream     = sanitize_text_field( get_option( 'swpm_postmark_message_stream', 'outbound' ) );

		if ( empty( $api_token ) ) {
			return SWPM_Send_Result::failure(
				__( 'Postmark Server Token is not configured.', 'swpmail' ),
				'MISSING_CONFIG'
			);
		}

		$safe_from_name = '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $from_name ) . '"';

		$payload = array(
			'From'          => "{$safe_from_name} <{$from_email}>",
			'To'            => $to,
			'Subject'       => $subject,
			'HtmlBody'      => $body,
			'TextBody'      => wp_strip_all_tags( $body ),
			'MessageStream' => $stream,
		);

		$reply_to = $this->extract_header( $headers, 'Reply-To' );
		if ( is_email( $reply_to ) ) {
			$payload['ReplyTo'] = $reply_to;
		}

		if ( ! empty( $attachments ) ) {
			$payload['Attachments'] = $this->build_attachments( $attachments );
		}

		$response = wp_remote_post( 'https://api.postmarkapp.com/email', array(
			'timeout'     => (int) apply_filters( 'swpm_api_timeout', 20 ),
			'httpversion' => '1.1',
			'headers'     => array(
				'Accept'                  => 'application/json',
				'Content-Type'            => 'application/json',
				'X-Postmark-Server-Token' => $api_token,
			),
			'body'        => wp_json_encode( $payload ),
		) );

		if ( is_wp_error( $response ) ) {
			return SWPM_Send_Result::failure( $response->get_error_message(), 'WP_HTTP_ERROR' );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$parsed = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 200 && $status < 300 && ! empty( $parsed['MessageID'] ) ) {
			return SWPM_Send_Result::success( $parsed['MessageID'], $parsed );
		}

		$error_code = $parsed['ErrorCode'] ?? 0;
		$error_msg  = $parsed['Message'] ?? 'Unknown Postmark error';

		return SWPM_Send_Result::failure(
			"Postmark API Error {$status}: {$error_msg}",
			"HTTP_{$status}",
			$parsed ?: array()
		);
	}

	public function test_connection(): SWPM_Send_Result {
		return $this->send(
			get_option( 'admin_email' ),
			__( 'SWPMail Postmark Test', 'swpmail' ),
			__( 'Postmark connection is working correctly.', 'swpmail' )
		);
	}

	/**
	 * Build attachments array for Postmark.
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
				'Name'        => basename( $file ),
				'ContentType' => ( new \finfo( FILEINFO_MIME_TYPE ) )->file( $real ) ?: 'application/octet-stream',
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Content'     => base64_encode( $content ),
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
