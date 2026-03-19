<?php
/**
 * Custom webhook alarm channel.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Alarm_Channel_Custom implements SWPM_Alarm_Channel_Interface {

	public function get_key(): string {
		return 'custom';
	}

	public function get_label(): string {
		return __( 'Custom Webhook', 'swpmail' );
	}

	public function send( array $event ): bool {
		$webhook_url = $this->get_webhook_url();
		if ( empty( $webhook_url ) ) {
			return false;
		}

		$payload = array(
			'source'    => 'swpmail',
			'type'      => $event['type'],
			'title'     => $event['title'],
			'message'   => $event['message'],
			'context'   => $event['context'] ?? array(),
			'timestamp' => $event['timestamp'],
			'site_url'  => get_site_url(),
		);

		$secret = $this->get_secret();
		$headers = array( 'Content-Type' => 'application/json' );

		if ( ! empty( $secret ) ) {
			$body_json = wp_json_encode( $payload );
			$signature = hash_hmac( 'sha256', $body_json, $secret );
			$headers['X-SWPMail-Signature'] = $signature;
		}

		return $this->post( $webhook_url, $payload, $headers );
	}

	public function test(): bool {
		return $this->send( array(
			'type'      => 'test',
			'title'     => __( 'SWPMail Test Alarm', 'swpmail' ),
			'message'   => __( 'This is a test notification from SWPMail alarm system.', 'swpmail' ),
			'context'   => array(),
			'timestamp' => time(),
		) );
	}

	private function get_webhook_url(): string {
		$encrypted = get_option( 'swpm_alarm_custom_webhook_enc', '' );
		return swpm_decrypt( $encrypted );
	}

	private function get_secret(): string {
		$encrypted = get_option( 'swpm_alarm_custom_secret_enc', '' );
		return swpm_decrypt( $encrypted );
	}

	private function post( string $url, array $payload, array $headers ): bool {
		if ( ! swpm_is_safe_url( $url ) ) {
			swpm_log( 'warning', 'Custom webhook alarm blocked: unsafe URL.' );
			return false;
		}

		$response = wp_remote_post( $url, array(
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			swpm_log( 'warning', 'Custom webhook alarm failed: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}
}
