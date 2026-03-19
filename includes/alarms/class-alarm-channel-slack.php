<?php
/**
 * Slack alarm channel.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Alarm_Channel_Slack implements SWPM_Alarm_Channel_Interface {

	public function get_key(): string {
		return 'slack';
	}

	public function get_label(): string {
		return 'Slack';
	}

	public function send( array $event ): bool {
		$webhook_url = $this->get_webhook_url();
		if ( empty( $webhook_url ) ) {
			return false;
		}

		$color = 'mail_failed' === $event['type'] ? '#e74c3c' : '#f39c12';

		$payload = array(
			'attachments' => array(
				array(
					'color'  => $color,
					'title'  => $event['title'],
					'text'   => $event['message'],
					'fields' => array(
						array(
							'title' => __( 'Event', 'swpmail' ),
							'value' => $event['type'],
							'short' => true,
						),
						array(
							'title' => __( 'Time', 'swpmail' ),
							'value' => wp_date( 'Y-m-d H:i:s', $event['timestamp'] ),
							'short' => true,
						),
					),
					'footer' => 'SWPMail',
					'ts'     => $event['timestamp'],
				),
			),
		);

		if ( ! empty( $event['context'] ) ) {
			$payload['attachments'][0]['fields'][] = array(
				'title' => __( 'Details', 'swpmail' ),
				'value' => is_array( $event['context'] ) ? wp_json_encode( $event['context'] ) : (string) $event['context'],
				'short' => false,
			);
		}

		return $this->post( $webhook_url, $payload );
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
		$encrypted = get_option( 'swpm_alarm_slack_webhook_enc', '' );
		return swpm_decrypt( $encrypted );
	}

	private function post( string $url, array $payload ): bool {
		if ( ! swpm_is_safe_url( $url ) ) {
			swpm_log( 'warning', 'Slack alarm blocked: unsafe webhook URL.' );
			return false;
		}

		$response = wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			swpm_log( 'warning', 'Slack alarm failed: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}
}
