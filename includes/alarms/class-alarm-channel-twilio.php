<?php
/**
 * Twilio SMS alarm channel.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Alarm_Channel_Twilio implements SWPM_Alarm_Channel_Interface {

	public function get_key(): string {
		return 'twilio';
	}

	public function get_label(): string {
		return 'Twilio SMS';
	}

	public function send( array $event ): bool {
		$account_sid = $this->get_credential( 'swpm_alarm_twilio_sid_enc' );
		$auth_token  = $this->get_credential( 'swpm_alarm_twilio_token_enc' );
		$from        = get_option( 'swpm_alarm_twilio_from', '' );
		$to          = get_option( 'swpm_alarm_twilio_to', '' );

		if ( empty( $account_sid ) || empty( $auth_token ) || empty( $from ) || empty( $to ) ) {
			return false;
		}

		// Validate E.164 phone number format.
		if ( ! preg_match( '/^\+[1-9]\d{1,14}$/', $from ) || ! preg_match( '/^\+[1-9]\d{1,14}$/', $to ) ) {
			swpm_log( 'warning', 'Twilio alarm: invalid phone number format (E.164 required).' );
			return false;
		}

		$body = sprintf(
			"[SWPMail] %s\n%s\n%s",
			$event['title'],
			$event['message'],
			wp_date( 'Y-m-d H:i:s', $event['timestamp'] )
		);

		// Keep SMS under 1600 chars (Twilio auto-segments beyond that).
		if ( strlen( $body ) > 1600 ) {
			$body = substr( $body, 0, 1597 ) . '...';
		}

		$url = sprintf(
			'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
			rawurlencode( $account_sid )
		);

		if ( ! swpm_is_safe_url( $url ) ) {
			swpm_log( 'warning', 'Twilio alarm blocked: unsafe URL.' );
			return false;
		}

		$response = wp_remote_post( $url, array(
			'headers' => array(
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ),
			),
			'body'    => array(
				'From' => $from,
				'To'   => $to,
				'Body' => $body,
			),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			swpm_log( 'warning', 'Twilio alarm failed: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
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

	private function get_credential( string $option_key ): string {
		$encrypted = get_option( $option_key, '' );
		return swpm_decrypt( $encrypted );
	}
}
