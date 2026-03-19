<?php
/**
 * Amazon SES Provider (SDK-less, AWS Signature V4).
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Provider_SES implements SWPM_Provider_Interface {

	public function get_key(): string {
		return 'ses';
	}

	public function get_label(): string {
		return __( 'Amazon SES', 'swpmail' );
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

		$access_key = swpm_decrypt( get_option( 'swpm_ses_access_key_enc', '' ) );
		$secret_key = swpm_decrypt( get_option( 'swpm_ses_secret_key_enc', '' ) );
		$region     = sanitize_text_field( get_option( 'swpm_ses_region', 'us-east-1' ) );
		$from_email = sanitize_email( get_option( 'swpm_from_email', get_option( 'admin_email' ) ) );
		$from_name  = sanitize_text_field( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) );

		if ( ! is_email( $from_email ) ) {
			return SWPM_Send_Result::failure( 'Invalid from email address.', 'INVALID_FROM_EMAIL' );
		}

		if ( empty( $access_key ) || empty( $secret_key ) ) {
			return SWPM_Send_Result::failure(
				__( 'Amazon SES credentials are not configured.', 'swpmail' ),
				'MISSING_CONFIG'
			);
		}

		// Validate region format to prevent SSRF via endpoint manipulation.
		if ( ! preg_match( '/^[a-z]{2}-[a-z]+-\d{1,2}$/', $region ) ) {
			return SWPM_Send_Result::failure(
				__( 'Invalid AWS region format.', 'swpmail' ),
				'INVALID_REGION'
			);
		}

		$endpoint = "https://email.{$region}.amazonaws.com/v2/email/outbound-emails";

		$safe_from_name = '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $from_name ) . '"';

		$payload = array(
			'FromEmailAddress' => "{$safe_from_name} <{$from_email}>",
			'Destination'      => array( 'ToAddresses' => array( $to ) ),
			'Content'          => array(
				'Simple' => array(
					'Subject' => array( 'Data' => $subject, 'Charset' => 'UTF-8' ),
					'Body'    => array(
						'Html' => array( 'Data' => $body, 'Charset' => 'UTF-8' ),
						'Text' => array( 'Data' => wp_strip_all_tags( $body ), 'Charset' => 'UTF-8' ),
					),
				),
			),
		);

		$reply_to = $this->extract_header( $headers, 'Reply-To' );
		if ( is_email( $reply_to ) ) {
			$payload['ReplyToAddresses'] = array( $reply_to );
		}

		$json_body  = wp_json_encode( $payload );
		$amz_date   = gmdate( 'Ymd\THis\Z' );
		$date_stamp = gmdate( 'Ymd' );

		$signed_headers = $this->sign_request(
			$endpoint, $json_body, $amz_date, $date_stamp,
			$region, $access_key, $secret_key
		);

		$response = wp_remote_post( $endpoint, array(
			'timeout'     => (int) apply_filters( 'swpm_api_timeout', 20 ),
			'httpversion' => '1.1',
			'headers'     => $signed_headers,
			'body'        => $json_body,
		) );

		if ( is_wp_error( $response ) ) {
			return SWPM_Send_Result::failure( $response->get_error_message(), 'WP_HTTP_ERROR' );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$parsed = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 200 && $status < 300 ) {
			return SWPM_Send_Result::success( $parsed['MessageId'] ?? '', $parsed ?: array() );
		}

		$error_msg = $parsed['message'] ?? $parsed['Message'] ?? wp_remote_retrieve_body( $response );
		return SWPM_Send_Result::failure(
			"SES API Error {$status}: {$error_msg}",
			"HTTP_{$status}",
			$parsed ?: array()
		);
	}

	public function test_connection(): SWPM_Send_Result {
		return $this->send(
			get_option( 'admin_email' ),
			__( 'SWPMail Amazon SES Test', 'swpmail' ),
			__( 'Amazon SES connection is working correctly.', 'swpmail' )
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

	/**
	 * AWS Signature Version 4 signed headers.
	 *
	 * @param string $endpoint   API endpoint.
	 * @param string $body       JSON body.
	 * @param string $amz_date   AMZ datetime.
	 * @param string $date_stamp Date stamp.
	 * @param string $region     AWS region.
	 * @param string $access_key Access key.
	 * @param string $secret_key Secret key.
	 * @return array
	 */
	private function sign_request(
		string $endpoint,
		string $body,
		string $amz_date,
		string $date_stamp,
		string $region,
		string $access_key,
		string $secret_key
	): array {
		$service   = 'ses';
		$algorithm = 'AWS4-HMAC-SHA256';
		$host      = wp_parse_url( $endpoint, PHP_URL_HOST );
		$body_hash = hash( 'sha256', $body );

		$canonical_headers = "content-type:application/json\nhost:{$host}\nx-amz-date:{$amz_date}\n";
		$signed_headers    = 'content-type;host;x-amz-date';
		$canonical_request = implode( "\n", array(
			'POST',
			'/v2/email/outbound-emails',
			'',
			$canonical_headers,
			$signed_headers,
			$body_hash,
		) );

		$credential_scope = "{$date_stamp}/{$region}/{$service}/aws4_request";
		$string_to_sign   = implode( "\n", array(
			$algorithm,
			$amz_date,
			$credential_scope,
			hash( 'sha256', $canonical_request ),
		) );

		$signing_key = $this->hmac_sha256(
			$this->hmac_sha256(
				$this->hmac_sha256(
					$this->hmac_sha256( 'AWS4' . $secret_key, $date_stamp ),
					$region
				),
				$service
			),
			'aws4_request'
		);

		$signature = bin2hex( hash_hmac( 'sha256', $string_to_sign, $signing_key, true ) );

		$auth_header = sprintf(
			'%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
			$algorithm,
			$access_key,
			$credential_scope,
			$signed_headers,
			$signature
		);

		return array(
			'Content-Type'  => 'application/json',
			'X-Amz-Date'    => $amz_date,
			'Authorization' => $auth_header,
		);
	}

	/**
	 * HMAC-SHA256.
	 *
	 * @param string $key  Key.
	 * @param string $data Data.
	 * @return string
	 */
	private function hmac_sha256( string $key, string $data ): string {
		return hash_hmac( 'sha256', $data, $key, true );
	}
}
