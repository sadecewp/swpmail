<?php
/**
 * Discord alarm channel.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Alarm_Channel_Discord implements SWPM_Alarm_Channel_Interface {

	public function get_key(): string {
		return 'discord';
	}

	public function get_label(): string {
		return 'Discord';
	}

	public function send( array $event ): bool {
		$webhook_url = $this->get_webhook_url();
		if ( empty( $webhook_url ) ) {
			return false;
		}

		$color = 'mail_failed' === $event['type'] ? 0xe74c3c : 0xf39c12;

		$fields = array(
			array(
				'name'   => __( 'Event', 'swpmail' ),
				'value'  => $event['type'],
				'inline' => true,
			),
			array(
				'name'   => __( 'Time', 'swpmail' ),
				'value'  => wp_date( 'Y-m-d H:i:s', $event['timestamp'] ),
				'inline' => true,
			),
		);

		if ( ! empty( $event['context'] ) ) {
			$fields[] = array(
				'name'   => __( 'Details', 'swpmail' ),
				'value'  => is_array( $event['context'] ) ? '```json' . "\n" . wp_json_encode( $event['context'], JSON_PRETTY_PRINT ) . "\n" . '```' : (string) $event['context'],
				'inline' => false,
			);
		}

		$payload = array(
			'embeds' => array(
				array(
					'title'       => $event['title'],
					'description' => $event['message'],
					'color'       => $color,
					'fields'      => $fields,
					'footer'      => array( 'text' => 'SWPMail' ),
					'timestamp'   => gmdate( 'c', $event['timestamp'] ),
				),
			),
		);

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
		$encrypted = get_option( 'swpm_alarm_discord_webhook_enc', '' );
		return swpm_decrypt( $encrypted );
	}

	private function post( string $url, array $payload ): bool {
		if ( ! swpm_is_safe_url( $url ) ) {
			swpm_log( 'warning', 'Discord alarm blocked: unsafe webhook URL.' );
			return false;
		}

		$response = wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			swpm_log( 'warning', 'Discord alarm failed: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}
}
