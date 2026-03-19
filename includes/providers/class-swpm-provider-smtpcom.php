<?php
/**
 * SMTP.com Provider.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SWPM_Provider_SMTPcom.
 */
class SWPM_Provider_SMTPcom implements SWPM_Provider_Interface {

	/**
	 * Get key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'smtpcom';
	}

	/**
	 * Get label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'SMTP.com', 'swpmail' );
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

		$api_key    = swpm_decrypt( get_option( 'swpm_smtpcom_api_key_enc', '' ) );
		$channel    = get_option( 'swpm_smtpcom_channel', '' );
		$from_email = sanitize_email( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) );
		$from_name  = sanitize_text_field( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) );

		if ( ! is_email( $from_email ) ) {
			return SWPM_Send_Result::failure( 'Invalid from email address.', 'INVALID_FROM_EMAIL' );
		}

		if ( empty( $api_key ) ) {
			return SWPM_Send_Result::failure(
				__( 'SMTP.com API key is not configured.', 'swpmail' ),
				'MISSING_CONFIG'
			);
		}

		$payload = array(
			'channel'    => $channel,
			'recipients' => array(
				'to' => array( array( 'address' => $to ) ),
			),
			'originator' => array(
				'from' => array(
					'name'    => $from_name,
					'address' => $from_email,
				),
			),
			'subject'    => $subject,
			'body'       => array(
				'parts' => array(
					array(
						'type'    => 'text/plain',
						'content' => wp_strip_all_tags( $body ),
					),
					array(
						'type'    => 'text/html',
						'content' => $body,
					),
				),
			),
		);

		$reply_to = $this->extract_header( $headers, 'Reply-To' );
		if ( is_email( $reply_to ) ) {
			$payload['originator']['reply_to'] = array( 'address' => $reply_to );
		}

		$response = wp_remote_post(
			'https://api.smtp.com/v4/messages',
			array(
				'timeout'     => (int) apply_filters( 'swpm_api_timeout', 20 ),
				'httpversion' => '1.1',
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
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
			$msg_id = $parsed['data']['message_id'] ?? $parsed['msg_id'] ?? '';
			return SWPM_Send_Result::success( $msg_id, $parsed ? $parsed : array() );
		}

		$error_message = $parsed['error']['message'] ?? $parsed['message'] ?? wp_remote_retrieve_body( $response );

		return SWPM_Send_Result::failure(
			"SMTP.com API Error {$status}: {$error_message}",
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
			__( 'SWPMail SMTP.com Test', 'swpmail' ),
			__( 'SMTP.com connection is working correctly.', 'swpmail' )
		);
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
