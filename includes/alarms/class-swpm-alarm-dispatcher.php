<?php
/**
 * Alarm dispatcher — routes failure events to enabled notification channels.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alarm dispatcher routes failure events to enabled notification channels.
 */
class SWPM_Alarm_Dispatcher {

	/**
	 * Registered alarm channels.
	 *
	 * @var SWPM_Alarm_Channel_Interface[]
	 */
	private array $channels = array();

	/**
	 * Default throttle cooldown in seconds.
	 *
	 * @var int
	 */
	private const DEFAULT_COOLDOWN = 300;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_channels();
	}

	/**
	 * Register built-in channels.
	 */
	private function register_channels(): void {
		$this->channels = array(
			'slack'   => new SWPM_Alarm_Channel_Slack(),
			'discord' => new SWPM_Alarm_Channel_Discord(),
			'teams'   => new SWPM_Alarm_Channel_Teams(),
			'twilio'  => new SWPM_Alarm_Channel_Twilio(),
			'custom'  => new SWPM_Alarm_Channel_Custom(),
		);
	}

	/**
	 * Initialize hooks.
	 */
	public function init(): void {
		// Listen for per-recipient mail failures.
		add_action( 'swpm_mail_failed', array( $this, 'on_mail_failed' ), 10, 3 );

		// Listen for failover events.
		add_action( 'swpm_failover_triggered', array( $this, 'on_failover_triggered' ), 10, 2 );

		// AJAX handlers.
		add_action( 'wp_ajax_swpm_save_alarm_channels', array( $this, 'ajax_save_channels' ) );
		add_action( 'wp_ajax_swpm_test_alarm_channel', array( $this, 'ajax_test_channel' ) );
	}

	/**
	 * Handle mail_failed action.
	 *
	 * @param SWPM_Send_Result $result   Send result.
	 * @param string           $recipient Recipient email.
	 * @param array            $mail_data Original mail data.
	 */
	public function on_mail_failed( $result, string $recipient, array $mail_data ): void {
		$error_msg = is_object( $result ) && method_exists( $result, 'get_message' )
			? $result->get_message()
			: __( 'Unknown error', 'swpmail' );

		// Sanitize: remove potential API keys/tokens from error messages before external dispatch.
		$error_msg = preg_replace( '/[a-zA-Z0-9_\-]{20,}/', '***', $error_msg );

		$this->dispatch(
			'mail_failed',
			array(
				'title'   => __( 'Email Delivery Failed', 'swpmail' ),
				'message' => sprintf(
					/* translators: 1: recipient, 2: error message */
					__( 'Failed to deliver email to %1$s: %2$s', 'swpmail' ),
					$recipient,
					$error_msg
				),
				'context' => array(
					'recipient' => $recipient,
					'subject'   => $mail_data['subject'] ?? '',
					'error'     => $error_msg,
				),
			)
		);
	}

	/**
	 * Handle failover_triggered action.
	 *
	 * @param string           $provider_key Provider that failed.
	 * @param SWPM_Send_Result $result       Send result.
	 */
	public function on_failover_triggered( string $provider_key, $result ): void {
		$error_msg = is_object( $result ) && method_exists( $result, 'get_message' )
			? $result->get_message()
			: __( 'Unknown error', 'swpmail' );

		$this->dispatch(
			'failover_triggered',
			array(
				'title'   => __( 'Provider Failover Activated', 'swpmail' ),
				'message' => sprintf(
					/* translators: 1: provider key, 2: error */
					__( 'Primary provider "%1$s" failed, switching to backup. Error: %2$s', 'swpmail' ),
					$provider_key,
					$error_msg
				),
				'context' => array(
					'provider' => $provider_key,
					'error'    => $error_msg,
				),
			)
		);
	}

	/**
	 * Dispatch an event to all enabled channels.
	 *
	 * @param string $event_type Event type key.
	 * @param array  $data       title, message, context keys.
	 */
	public function dispatch( string $event_type, array $data ): void {
		$enabled_events = (array) get_option( 'swpm_alarm_events', array( 'mail_failed', 'failover_triggered' ) );
		if ( ! in_array( $event_type, $enabled_events, true ) ) {
			return;
		}

		// Throttle: one notification per event type within cooldown.
		$cooldown      = (int) get_option( 'swpm_alarm_cooldown', self::DEFAULT_COOLDOWN );
		$transient_key = 'swpm_alarm_t_' . $event_type;

		if ( get_transient( $transient_key ) ) {
			return;
		}

		$enabled_channels = (array) get_option( 'swpm_alarm_enabled_channels', array() );
		if ( empty( $enabled_channels ) ) {
			return;
		}

		$event = array(
			'type'      => $event_type,
			'title'     => $data['title'] ?? $event_type,
			'message'   => $data['message'] ?? '',
			'context'   => $data['context'] ?? array(),
			'timestamp' => time(),
		);

		$sent = false;
		foreach ( $enabled_channels as $channel_key ) {
			if ( isset( $this->channels[ $channel_key ] ) ) {
				$result = $this->channels[ $channel_key ]->send( $event );
				if ( $result ) {
					$sent = true;
				}
			}
		}

		if ( $sent && $cooldown > 0 ) {
			set_transient( $transient_key, 1, $cooldown );
		}
	}

	/**
	 * AJAX: Save alarm channel settings.
	 */
	public function ajax_save_channels(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'swpmail' ) );
		}

		// Enabled channels.
		$channels = isset( $_POST['enabled_channels'] ) && is_array( $_POST['enabled_channels'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_channels'] ) )
			: array();
		$valid    = array_keys( $this->channels );
		$channels = array_values( array_intersect( $channels, $valid ) );
		update_option( 'swpm_alarm_enabled_channels', $channels );

		// Enabled events.
		$events       = isset( $_POST['enabled_events'] ) && is_array( $_POST['enabled_events'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_events'] ) )
			: array();
		$valid_events = array( 'mail_failed', 'failover_triggered' );
		$events       = array_values( array_intersect( $events, $valid_events ) );
		update_option( 'swpm_alarm_events', $events );

		// Cooldown.
		$cooldown = isset( $_POST['cooldown'] ) ? absint( $_POST['cooldown'] ) : self::DEFAULT_COOLDOWN;
		update_option( 'swpm_alarm_cooldown', $cooldown );

		// Slack (empty = keep current value).
		if ( isset( $_POST['slack_webhook'] ) ) {
			$val = sanitize_text_field( wp_unslash( $_POST['slack_webhook'] ) );
			if ( ! empty( $val ) ) {
				if ( ! preg_match( '#^https://hooks\.slack\.com/services/#', $val ) ) {
					wp_send_json_error( __( 'Invalid Slack webhook URL format.', 'swpmail' ) );
				}
				update_option( 'swpm_alarm_slack_webhook_enc', swpm_encrypt( $val ) );
			}
		}

		// Discord (empty = keep current value).
		if ( isset( $_POST['discord_webhook'] ) ) {
			$val = sanitize_text_field( wp_unslash( $_POST['discord_webhook'] ) );
			if ( ! empty( $val ) ) {
				if ( ! preg_match( '#^https://(discord|discordapp)\.com/api/webhooks/#', $val ) ) {
					wp_send_json_error( __( 'Invalid Discord webhook URL format.', 'swpmail' ) );
				}
				update_option( 'swpm_alarm_discord_webhook_enc', swpm_encrypt( $val ) );
			}
		}

		// Teams (empty = keep current value).
		if ( isset( $_POST['teams_webhook'] ) ) {
			$val = sanitize_text_field( wp_unslash( $_POST['teams_webhook'] ) );
			if ( ! empty( $val ) ) {
				if ( ! preg_match( '#^https://.*\.webhook\.office\.com/#', $val ) ) {
					wp_send_json_error( __( 'Invalid Teams webhook URL format.', 'swpmail' ) );
				}
				update_option( 'swpm_alarm_teams_webhook_enc', swpm_encrypt( $val ) );
			}
		}

		// Twilio (empty = keep current value).
		if ( isset( $_POST['twilio_sid'] ) ) {
			$val = sanitize_text_field( wp_unslash( $_POST['twilio_sid'] ) );
			if ( ! empty( $val ) ) {
				update_option( 'swpm_alarm_twilio_sid_enc', swpm_encrypt( $val ) );
			}
		}
		if ( isset( $_POST['twilio_token'] ) ) {
			$val = sanitize_text_field( wp_unslash( $_POST['twilio_token'] ) );
			if ( ! empty( $val ) ) {
				update_option( 'swpm_alarm_twilio_token_enc', swpm_encrypt( $val ) );
			}
		}
		if ( isset( $_POST['twilio_from'] ) ) {
			update_option( 'swpm_alarm_twilio_from', sanitize_text_field( wp_unslash( $_POST['twilio_from'] ) ) );
		}
		if ( isset( $_POST['twilio_to'] ) ) {
			update_option( 'swpm_alarm_twilio_to', sanitize_text_field( wp_unslash( $_POST['twilio_to'] ) ) );
		}

		// Custom webhook (empty = keep current value).
		if ( isset( $_POST['custom_webhook'] ) ) {
			$val = sanitize_text_field( wp_unslash( $_POST['custom_webhook'] ) );
			if ( ! empty( $val ) ) {
				update_option( 'swpm_alarm_custom_webhook_enc', swpm_encrypt( $val ) );
			}
		}
		if ( isset( $_POST['custom_secret'] ) ) {
			$val = sanitize_text_field( wp_unslash( $_POST['custom_secret'] ) );
			if ( ! empty( $val ) ) {
				update_option( 'swpm_alarm_custom_secret_enc', swpm_encrypt( $val ) );
			}
		}

		wp_send_json_success( __( 'Alarm settings saved.', 'swpmail' ) );
	}

	/**
	 * AJAX: Test a single alarm channel.
	 */
	public function ajax_test_channel(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'swpmail' ) );
		}

		$channel_key = isset( $_POST['channel'] ) ? sanitize_text_field( wp_unslash( $_POST['channel'] ) ) : '';
		if ( ! isset( $this->channels[ $channel_key ] ) ) {
			wp_send_json_error( __( 'Invalid channel.', 'swpmail' ) );
		}

		$result = $this->channels[ $channel_key ]->test();
		if ( $result ) {
			wp_send_json_success( __( 'Test notification sent successfully!', 'swpmail' ) );
		} else {
			wp_send_json_error( __( 'Failed to send test notification. Check your credentials.', 'swpmail' ) );
		}
	}

	/**
	 * Get all registered channels keyed by key.
	 *
	 * @return SWPM_Alarm_Channel_Interface[]
	 */
	public function get_channels(): array {
		return $this->channels;
	}
}
