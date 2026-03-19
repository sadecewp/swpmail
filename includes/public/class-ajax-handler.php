<?php
/**
 * AJAX handler for subscribe/confirm/unsubscribe.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Ajax_Handler {

	/** @var SWPM_Subscriber */
	private SWPM_Subscriber $subscriber;

	/** @var SWPM_Mailer */
	private SWPM_Mailer $mailer;

	public function __construct( SWPM_Subscriber $subscriber, SWPM_Mailer $mailer ) {
		$this->subscriber = $subscriber;
		$this->mailer     = $mailer;
	}

	/**
	 * Register AJAX hooks.
	 */
	public function register(): void {
		add_action( 'wp_ajax_nopriv_swpm_subscribe', array( $this, 'handle_subscribe' ) );
		add_action( 'wp_ajax_swpm_subscribe', array( $this, 'handle_subscribe' ) );
		add_action( 'wp_ajax_nopriv_swpm_confirm', array( $this, 'handle_confirm' ) );
		add_action( 'wp_ajax_nopriv_swpm_unsubscribe', array( $this, 'handle_unsubscribe' ) );
	}

	/**
	 * Handle subscribe request.
	 */
	public function handle_subscribe(): void {
		// 1. Nonce verification.
		if ( ! check_ajax_referer( 'swpm_subscribe_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'swpmail' ) ), 403 );
		}

		// 2. Rate limit.
		$ip       = self::get_client_ip();
		$rate_key = 'swpm_rate_' . md5( $ip );
		$attempts = (int) get_transient( $rate_key );

		if ( $attempts >= 5 ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'swpmail' ) ), 429 );
		}
		set_transient( $rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );

		// 3. Honeypot.
		if ( ! empty( $_POST['swpm_website'] ) ) {
			// Respond as if successful to confuse bots.
			wp_send_json_success( array( 'message' => __( 'Check your email to confirm.', 'swpmail' ) ) );
		}

		// 4. Input sanitize.
		$email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$name      = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$frequency = sanitize_key( wp_unslash( $_POST['frequency'] ?? 'instant' ) );

		// 5. Validation.
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'swpmail' ) ), 400 );
		}

		// 6. GDPR consent.
		if ( get_option( 'swpm_gdpr_checkbox' ) && empty( $_POST['gdpr'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please accept the privacy policy.', 'swpmail' ) ), 400 );
		}

		// 7. Create subscriber.
		$result = $this->subscriber->create( $email, $name, $frequency );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}

		// 8. Send confirmation email.
		if ( get_option( 'swpm_double_opt_in', true ) ) {
			$sub = $this->subscriber->get_by_email( $email );

			/** @var SWPM_Template_Engine $engine */
			$engine = swpm( 'template_engine' );
			$body   = $engine->render( 'confirm-subscription', array(
				'subscriber_name' => $name ?: $email,
				'confirm_url'     => add_query_arg(
					array(
						'swpm_action' => 'confirm',
						'token'       => rawurlencode( $sub->token ),
					),
					home_url()
				),
			) );

			// Skip override — this mail is already sent by SWPMail.
			add_filter( 'swpm_skip_override', '__return_true' );
			try {
				wp_mail(
					$email,
					sprintf(
						/* translators: %s: site name */
						__( 'Confirm your subscription to %s', 'swpmail' ),
						get_bloginfo( 'name' )
					),
					$body,
					array( 'Content-Type: text/html; charset=UTF-8' )
				);
			} finally {
				remove_filter( 'swpm_skip_override', '__return_true' );
			}
		}

		wp_send_json_success( array(
			'message' => get_option( 'swpm_double_opt_in', true )
				? __( 'Please check your email to confirm your subscription.', 'swpmail' )
				: __( 'You have successfully subscribed!', 'swpmail' ),
		) );
	}

	/**
	 * Handle confirm request.
	 */
	public function handle_confirm(): void {
		// Nonce verification (token-specific nonce).
		$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );

		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid token.', 'swpmail' ) ), 400 );
		}

		if ( ! check_ajax_referer( 'swpm_confirm_' . $token, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'swpmail' ) ), 403 );
		}

		// Rate limit.
		$ip       = self::get_client_ip();
		$rate_key = 'swpm_confirm_rate_' . md5( $ip );
		$attempts = (int) get_transient( $rate_key );

		if ( $attempts >= 10 ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'swpmail' ) ), 429 );
		}
		set_transient( $rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );

		$confirmed = $this->subscriber->confirm( $token );

		if ( $confirmed ) {
			wp_send_json_success( array( 'message' => __( 'Subscription confirmed!', 'swpmail' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired confirmation link.', 'swpmail' ) ), 400 );
		}
	}

	/**
	 * Handle unsubscribe request.
	 */
	public function handle_unsubscribe(): void {
		// Nonce verification (token-specific nonce).
		$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );

		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid token.', 'swpmail' ) ), 400 );
		}

		if ( ! check_ajax_referer( 'swpm_unsub_' . $token, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'swpmail' ) ), 403 );
		}

		// Rate limit.
		$ip       = self::get_client_ip();
		$rate_key = 'swpm_unsub_rate_' . md5( $ip );
		$attempts = (int) get_transient( $rate_key );

		if ( $attempts >= 10 ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'swpmail' ) ), 429 );
		}
		set_transient( $rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );

		$unsubscribed = $this->subscriber->unsubscribe( $token );

		if ( $unsubscribed ) {
			wp_send_json_success( array( 'message' => __( 'You have been unsubscribed.', 'swpmail' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not process your request.', 'swpmail' ) ), 400 );
		}
	}

	/**
	 * Get the real client IP, accounting for trusted reverse proxies.
	 *
	 * @return string
	 */
	public static function get_client_ip(): string {
		// Only trust proxy headers when explicitly configured.
		if ( defined( 'SWPM_TRUSTED_PROXY' ) && SWPM_TRUSTED_PROXY ) {
			// Cloudflare.
			if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
				$ip = filter_var(
					wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ),
					FILTER_VALIDATE_IP,
					FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
				);
				if ( $ip ) {
					return $ip;
				}
			}
			// Generic reverse proxy.
			if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
				$ip = filter_var(
					wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ),
					FILTER_VALIDATE_IP,
					FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
				);
				if ( $ip ) {
					return $ip;
				}
			}
		}

		return filter_var(
			wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ),
			FILTER_VALIDATE_IP
		) ?: '';
	}
}
