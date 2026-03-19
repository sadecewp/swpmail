<?php
/**
 * Global wp_mail override / interceptor.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overrides wp_mail to route through SWPMail.
 */
class SWPM_WP_Mail_Override {

	/**

	 * Variable.
	 *
	 * @var SWPM_Provider_Interface
	 */
	private SWPM_Provider_Interface $provider;

	/**

	 * Variable.
	 *
	 * @var SWPM_Queue
	 */
	private SWPM_Queue $queue;

	/**
	 * Constructor.
	 *
	 * @param SWPM_Provider_Interface $provider Provider.
	 * @param SWPM_Queue              $queue Queue.
	 */
	public function __construct( SWPM_Provider_Interface $provider, SWPM_Queue $queue ) {
		$this->provider = $provider;
		$this->queue    = $queue;
	}

	/**
	 * Initialize the override based on provider type.
	 */
	public function init(): void {
		$provider_key = get_option( 'swpm_mail_provider', '' );

		if ( empty( $provider_key ) || ! get_option( 'swpm_override_wp_mail', true ) ) {
			return;
		}

		if ( 'smtp' === $provider_key ) {
			add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ), 10 );
			add_action( 'phpmailer_init', array( $this, 'set_from_address' ), 9 );
		} else {
			add_filter( 'pre_wp_mail', array( $this, 'intercept_wp_mail' ), 10, 2 );
		}

		add_filter( 'wp_mail_from', array( $this, 'filter_from_email' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ) );
	}

	/**
	 * Configure PHPMailer for SMTP provider.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint





	/**
	 * Configure phpmailer.
	 *
	 * @param object $phpmailer Phpmailer.
	 */
	public function configure_phpmailer( object $phpmailer ): void {
		$smtp_provider = swpm( 'provider' );
		if ( $smtp_provider && method_exists( $smtp_provider, 'configure_phpmailer' ) ) {
			$smtp_provider->configure_phpmailer( $phpmailer );
		}
	}

	/**
	 * Set From address early in phpmailer_init.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint





	/**
	 * Set from address.
	 *
	 * @param object $phpmailer Phpmailer.
	 */
	public function set_from_address( object $phpmailer ): void {
		$from_email = get_option( 'swpm_from_email', '' );
		$from_name  = get_option( 'swpm_from_name', '' );

		if ( ! empty( $from_email ) && is_email( $from_email ) ) {
			try {
				$phpmailer->setFrom( $from_email, $from_name ? $from_name : get_bloginfo( 'name' ) );
			} catch ( \Exception $e ) {
				swpm_log( 'error', 'Failed to set From address: ' . $e->getMessage() );
			}
		}
	// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.returnFound
	}

	/**
	 * Intercept wp_mail() for API providers.
	 *
	 * @param null|bool $return    Current return value.
	 * @param array     $mail_data wp_mail arguments.
	 * @return bool
	 */
	public function intercept_wp_mail( $return, array $mail_data ): bool {
		if ( apply_filters( 'swpm_skip_override', false ) ) {
			return false;
		}

		$to          = $mail_data['to'];
		$subject     = $mail_data['subject'];
		$body        = $mail_data['message'];
		$headers     = (array) ( $mail_data['headers'] ?? array() );
		$attachments = (array) ( $mail_data['attachments'] ?? array() );

		$recipients  = $this->normalize_recipients( $to );
		$all_success = true;

		foreach ( $recipients as $recipient ) {
			if ( ! is_email( $recipient ) ) {
				swpm_log( 'warning', "Invalid recipient skipped: {$recipient}" );
				continue;
			}

			/**
			 * Filter whether to send to this recipient.
			 *
			 * @since 1.0.0
			 */
			$should_send = apply_filters( 'swpm_pre_send', true, $recipient, $mail_data );
			if ( ! $should_send ) {
				swpm_log( 'info', "Send skipped for {$recipient} by swpm_pre_send filter." );
				continue;
			}

			// Smart routing: resolve per-recipient provider.
			$send_provider = $this->provider;
			$router        = swpm( 'router' );
			if ( $router instanceof SWPM_Router ) {
				$routed = $router->resolve(
					array(
						'to'      => $recipient,
						'subject' => $subject,
						'from'    => get_option( 'swpm_from_email', '' ),
						'headers' => $headers,
						'source'  => 'wp_mail',
					)
				);
				if ( $routed ) {
					$send_provider = $routed;
				}
			}

			$result = $send_provider->send( $recipient, $subject, $body, $headers, $attachments );

			$this->log_result( $result, $recipient, $subject );

			if ( ! $result->is_success() ) {
				$all_success = false;
				do_action( 'swpm_mail_failed', $result, $recipient, $mail_data );
			} else {
				do_action( 'swpm_mail_sent', $result, $recipient, $mail_data );
			}
		}

		return $all_success;
	}

	/**
	 * Filter From email.
	 *
	 * @param string $email Default email.
	 * @return string
	 */
	public function filter_from_email( string $email ): string {
		$configured = get_option( 'swpm_from_email', '' );
		return ( ! empty( $configured ) && is_email( $configured ) ) ? $configured : $email;
	}

	/**
	 * Filter From name.
	 *
	 * @param string $name Default name.
	 * @return string
	 */
	public function filter_from_name( string $name ): string {
		$configured = get_option( 'swpm_from_name', '' );
		return ! empty( $configured ) ? $configured : $name;
	}

	/**
	 * Normalize recipients to array.
	 *
	 * @param string|array $to Recipients.
	 * @return array<string>
	 */
	private function normalize_recipients( $to ): array {
		if ( is_array( $to ) ) {
			return array_map( 'trim', $to );
		}
		return array_map( 'trim', explode( ',', $to ) );
	}

	/**
	 * Log send result.
	 *
	 * @param SWPM_Send_Result $result    Result object.
	 * @param string           $recipient Recipient email.
	 * @param string           $subject   Email subject.
	 */
	private function log_result( SWPM_Send_Result $result, string $recipient, string $subject ): void {
		if ( $result->is_success() ) {
			swpm_log(
				'info',
				"Mail sent to {$recipient}: {$subject}",
				array(
					'message_id' => $result->get_message_id(),
					'provider'   => get_option( 'swpm_mail_provider' ),
				)
			);
		} else {
			swpm_log(
				'error',
				"Mail failed to {$recipient}: " . $result->get_error_message(),
				array(
					'error_code' => $result->get_error_code(),
					'provider'   => get_option( 'swpm_mail_provider' ),
				)
			);
		}
	}
}
