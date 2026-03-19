<?php
/**
 * Mailjet Provider.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Provider_Mailjet implements SWPM_Provider_Interface {

	public function get_key(): string {
		return 'mailjet';
	}

	public function get_label(): string {
		return __( 'Mailjet', 'swpmail' );
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

		$api_key    = swpm_decrypt( get_option( 'swpm_mailjet_api_key_enc', '' ) );
		$secret_key = swpm_decrypt( get_option( 'swpm_mailjet_secret_key_enc', '' ) );
		$from_email = sanitize_email( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) );
		$from_name  = sanitize_text_field( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) );

		if ( ! is_email( $from_email ) ) {
			return SWPM_Send_Result::failure( 'Invalid from email address.', 'INVALID_FROM_EMAIL' );
		}

		if ( empty( $api_key ) || empty( $secret_key ) ) {
			return SWPM_Send_Result::failure(
				__( 'Mailjet API key or secret key is not configured.', 'swpmail' ),
				'MISSING_CONFIG'
			);
		}

		$message = array(
			'From'     => array(
				'Email' => $from_email,
				'Name'  => $from_name,
			),
			'To'       => array(
				array( 'Email' => $to ),
			),
			'Subject'  => $subject,
			'HTMLPart' => $body,
			'TextPart' => wp_strip_all_tags( $body ),
		);

		$reply_to = $this->extract_header( $headers, 'Reply-To' );
		if ( is_email( $reply_to ) ) {
			$message['ReplyTo'] = array( 'Email' => $reply_to );
		}

		if ( ! empty( $attachments ) ) {
			$message['Attachments'] = $this->build_attachments( $attachments );
		}

		$payload = array( 'Messages' => array( $message ) );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$auth = base64_encode( $api_key . ':' . $secret_key );

		$response = wp_remote_post( 'https://api.mailjet.com/v3.1/send', array(
			'timeout'     => (int) apply_filters( 'swpm_api_timeout', 20 ),
			'httpversion' => '1.1',
			'headers'     => array(
				'Authorization' => 'Basic ' . $auth,
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
			$msg_id = '';
			if ( is_array( $parsed['Messages'] ?? null )
				&& is_array( $parsed['Messages'][0]['To'] ?? null )
				&& is_array( $parsed['Messages'][0]['To'][0] ?? null )
			) {
				$msg_id = (string) ( $parsed['Messages'][0]['To'][0]['MessageID'] ?? '' );
			}
			return SWPM_Send_Result::success( $msg_id, $parsed ?: array() );
		}

		$error = $parsed['ErrorMessage'] ?? '';
		if ( empty( $error )
			&& is_array( $parsed['Messages'] ?? null )
			&& is_array( $parsed['Messages'][0]['Errors'] ?? null )
			&& is_array( $parsed['Messages'][0]['Errors'][0] ?? null )
		) {
			$error = (string) ( $parsed['Messages'][0]['Errors'][0]['ErrorMessage'] ?? '' );
		}
		if ( empty( $error ) ) {
			$error = wp_remote_retrieve_body( $response );
		}

		return SWPM_Send_Result::failure(
			"Mailjet API Error {$status}: {$error}",
			"HTTP_{$status}",
			$parsed ?: array()
		);
	}

	public function test_connection(): SWPM_Send_Result {
		return $this->send(
			get_option( 'admin_email' ),
			__( 'SWPMail Mailjet Test', 'swpmail' ),
			__( 'Mailjet connection is working correctly.', 'swpmail' )
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
				'ContentType'   => ( new \finfo( FILEINFO_MIME_TYPE ) )->file( $real ) ?: 'application/octet-stream',
				'Filename'      => basename( $file ),
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Base64Content' => base64_encode( $content ),
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
