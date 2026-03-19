<?php
/**
 * Elastic Email Provider.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Provider_ElasticEmail implements SWPM_Provider_Interface {

	public function get_key(): string {
		return 'elasticemail';
	}

	public function get_label(): string {
		return __( 'Elastic Email', 'swpmail' );
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

		$api_key    = swpm_decrypt( get_option( 'swpm_elasticemail_api_key_enc', '' ) );
		$from_email = sanitize_email( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) );
		$from_name  = sanitize_text_field( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) );

		if ( ! is_email( $from_email ) ) {
			return SWPM_Send_Result::failure( 'Invalid from email address.', 'INVALID_FROM_EMAIL' );
		}

		if ( empty( $api_key ) ) {
			return SWPM_Send_Result::failure(
				__( 'Elastic Email API key is not configured.', 'swpmail' ),
				'MISSING_CONFIG'
			);
		}

		$payload = array(
			'Recipients' => array(
				'To' => array( $to ),
			),
			'Content'    => array(
				'From'    => "{$from_name} <{$from_email}>",
				'Subject' => $subject,
				'Body'    => array(
					array(
						'ContentType' => 'HTML',
						'Content'     => $body,
						'Charset'     => 'utf-8',
					),
					array(
						'ContentType' => 'PlainText',
						'Content'     => wp_strip_all_tags( $body ),
						'Charset'     => 'utf-8',
					),
				),
			),
		);

		$reply_to = $this->extract_header( $headers, 'Reply-To' );
		if ( is_email( $reply_to ) ) {
			$payload['Content']['ReplyTo'] = $reply_to;
		}

		if ( ! empty( $attachments ) ) {
			$payload['Content']['Attachments'] = $this->build_attachments( $attachments );
		}

		$response = wp_remote_post( 'https://api.elasticemail.com/v4/emails/transactional', array(
			'timeout'     => (int) apply_filters( 'swpm_api_timeout', 20 ),
			'httpversion' => '1.1',
			'headers'     => array(
				'X-ElasticEmail-ApiKey' => $api_key,
				'Content-Type'          => 'application/json',
			),
			'body'        => wp_json_encode( $payload ),
		) );

		if ( is_wp_error( $response ) ) {
			return SWPM_Send_Result::failure( $response->get_error_message(), 'WP_HTTP_ERROR' );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$parsed = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 200 && $status < 300 ) {
			$msg_id = $parsed['MessageID'] ?? $parsed['TransactionID'] ?? '';
			return SWPM_Send_Result::success( $msg_id, $parsed ?: array() );
		}

		$error = $parsed['Error'] ?? wp_remote_retrieve_body( $response );

		return SWPM_Send_Result::failure(
			"Elastic Email API Error {$status}: {$error}",
			"HTTP_{$status}",
			$parsed ?: array()
		);
	}

	public function test_connection(): SWPM_Send_Result {
		return $this->send(
			get_option( 'admin_email' ),
			__( 'SWPMail Elastic Email Test', 'swpmail' ),
			__( 'Elastic Email connection is working correctly.', 'swpmail' )
		);
	}

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
				'BinaryContent' => base64_encode( $content ),
				'Name'          => basename( $file ),
				'ContentType'   => ( new \finfo( FILEINFO_MIME_TYPE ) )->file( $real ) ?: 'application/octet-stream',
			);
		}
		return $result;
	}

	private function extract_header( array $headers, string $name ): string {
		foreach ( $headers as $h ) {
			if ( stripos( $h, "{$name}:" ) === 0 ) {
				return swpm_sanitize_header_value( trim( substr( $h, strlen( $name ) + 1 ) ) );
			}
		}
		return '';
	}
}
