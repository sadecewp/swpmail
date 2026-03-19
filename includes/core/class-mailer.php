<?php
/**
 * Mailer - orchestrates provider and queue.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Mailer {

	/** @var SWPM_Queue */
	private SWPM_Queue $queue;

	public function __construct( SWPM_Queue $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Send an email immediately or queue it.
	 *
	 * @param string $to       Recipient email.
	 * @param string $subject  Subject.
	 * @param string $body     HTML body.
	 * @param array  $headers  Headers.
	 * @param bool   $queue    Whether to queue instead of sending immediately.
	 * @return SWPM_Send_Result|int Result if immediate, queue ID if queued.
	 */
	public function send( string $to, string $subject, string $body, array $headers = array(), bool $queue = false ) {
		if ( ! is_email( $to ) ) {
			return SWPM_Send_Result::failure( 'Invalid recipient email address.', 'INVALID_EMAIL' );
		}

		if ( $queue ) {
			return $this->queue->enqueue( $to, $subject, $body );
		}

		/** @var SWPM_Provider_Interface $provider */
		$provider = swpm( 'provider' );
		if ( ! $provider ) {
			return SWPM_Send_Result::failure( 'No provider configured.', 'NO_PROVIDER' );
		}

		// Smart routing: resolve provider for this email.
		$router = swpm( 'router' );
		if ( $router instanceof SWPM_Router ) {
			$routed = $router->resolve( array(
				'to'      => $to,
				'subject' => $subject,
				'from'    => get_option( 'swpm_from_email', '' ),
				'headers' => $headers,
				'source'  => 'mailer',
			) );
			if ( $routed ) {
				$provider = $routed;
			}
		}

		// Inject tracking pixel and link rewrites.
		$tracker = swpm( 'tracker' );
		if ( $tracker instanceof SWPM_Tracker ) {
			$body = $tracker->inject_tracking( $body, $to, $subject );
		}

		add_filter( 'swpm_skip_override', '__return_true' );
		try {
			$result = $provider->send( $to, $subject, $body, array_merge(
				array( 'Content-Type: text/html; charset=UTF-8' ),
				$headers
			) );
		} finally {
			remove_filter( 'swpm_skip_override', '__return_true' );
		}

		return $result;
	}

	/**
	 * Send a templated email.
	 *
	 * @param string $to          Recipient email.
	 * @param string $subject     Subject.
	 * @param string $template_id Template ID.
	 * @param array  $variables   Template variables.
	 * @param bool   $queue       Whether to queue.
	 * @return SWPM_Send_Result|int
	 */
	public function send_template( string $to, string $subject, string $template_id, array $variables = array(), bool $queue = false ) {
		/** @var SWPM_Template_Engine $engine */
		$engine = swpm( 'template_engine' );
		$body   = $engine->render( $template_id, $variables );

		return $this->send( $to, $subject, $body, array(), $queue );
	}
}
