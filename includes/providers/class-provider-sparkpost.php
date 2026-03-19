<?php
/**
 * SparkPost Provider.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Provider_SparkPost implements SWPM_Provider_Interface {

	public function get_key(): string {
		return 'sparkpost';
	}

	public function get_label(): string {
		return __( 'SparkPost', 'swpmail' );
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

		$api_key    = swpm_decrypt( get_option( 'swpm_sparkpost_api_key_enc', '' ) );
		$region     = get_option( 'swpm_sparkpost_region', 'us' );
		$from_email = get_option( 'swpm_from_email', get_option( 'admin_email' ) );
		$from_name  = get_option( 'swpm_from_name', get_bloginfo( 'name' ) );

		if ( empty( $api_key ) ) {
			return SWPM_Send_Result::failure(
				__( 'SparkPost API key is not configured.', 'swpmail' ),
				'MISSING_CONFIG'
			);
		}

		$base_url = 'eu' === $region
			? 'https://api.eu.sparkpost.com/api/v1/transmissions'
			: 'https://api.sparkpost.com/api/v1/transmissions';

		$content = array(
			'from'    => array(
				'email' => $from_email,
				'name'  => $from_name,
			),
			'subject' => $subject,
			'html'    => $body,
			'text'    => wp_strip_all_tags( $body ),
		);

		$reply_to = $this->extract_header( $headers, 'Reply-To' );
		if ( is_email( $reply_to ) ) {
			$content['reply_to'] = $reply_to;
		}

		$payload = array(
			'recipients' => array(
				array( 'address' => array( 'email' => $to ) ),
			),
			'content'    => $content,
		);

		if ( ! empty( $attachments ) ) {
			$payload['content']['attachments'] = $this->build_attachments( $attachments );
		}

		$response = wp_remote_post( $base_url, array(
			'timeout'     => (int) apply_filters( 'swpm_api_timeout', 20 ),
			'httpversion' => '1.1',
			'headers'     => array(
				'Authorization' => $api_key,
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
			$msg_id = $parsed['results']['id'] ?? '';
			return SWPM_Send_Result::success( $msg_id, $parsed ?: array() );
		}

		$errors = $parsed['errors'][0]['message'] ?? wp_remote_retrieve_body( $response );

		return SWPM_Send_Result::failure(
			"SparkPost API Error {$status}: {$errors}",
			"HTTP_{$status}",
			$parsed ?: array()
		);
	}

	public function test_connection(): SWPM_Send_Result {
		return $this->send(
			get_option( 'admin_email' ),
			__( 'SWPMail SparkPost Test', 'swpmail' ),
			__( 'SparkPost connection is working correctly.', 'swpmail' )
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
				'name' => basename( $file ),
				'type' => ( new \finfo( FILEINFO_MIME_TYPE ) )->file( $real ) ?: 'application/octet-stream',
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'data' => base64_encode( $content ),
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
