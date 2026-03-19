<?php
/**
 * Abstract trigger base class.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class SWPM_Trigger_Base {

	/**
	 * Get the unique trigger key.
	 */
	abstract public function get_key(): string;

	/**
	 * Get the display label.
	 */
	abstract public function get_label(): string;

	/**
	 * Get the WordPress hook name.
	 */
	abstract public function get_hook(): string;

	/**
	 * Get the email subject.
	 *
	 * @param array $data Prepared data.
	 * @return string
	 */
	abstract public function get_subject( array $data ): string;

	/**
	 * Get the template ID.
	 */
	abstract public function get_template_id(): string;

	/**
	 * Get recipients.
	 *
	 * @param array $data Prepared data.
	 * @return array Array of subscriber-like objects.
	 */
	abstract public function get_recipients( array $data ): array;

	/**
	 * Prepare data from hook arguments.
	 *
	 * @param mixed ...$args Hook arguments.
	 * @return array Empty array to abort.
	 */
	abstract protected function prepare_data( ...$args ): array;

	/**
	 * Number of accepted arguments.
	 *
	 * @return int
	 */
	public function get_hook_args(): int {
		return 1;
	}

	/**
	 * Check if this trigger is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$active = get_option( 'swpm_active_triggers', array() );
		return in_array( $this->get_key(), (array) $active, true );
	}

	/**
	 * Handle the trigger.
	 *
	 * @param mixed ...$args Hook arguments.
	 */
	public function handle( ...$args ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$data = $this->prepare_data( ...$args );

		if ( empty( $data ) ) {
			return;
		}

		$proceed = apply_filters( 'swpm_trigger_should_send_' . $this->get_key(), true, $data );
		if ( ! $proceed ) {
			return;
		}

		$recipients = $this->get_recipients( $data );

		if ( empty( $recipients ) ) {
			swpm_log( 'info', "Trigger '{$this->get_key()}': no recipients, skipping." );
			return;
		}

		$subject = $this->get_subject( $data );

		/** @var SWPM_Queue $queue */
		$queue = swpm( 'queue' );
		if ( $queue ) {
			$queue->enqueue_bulk( $recipients, $this->get_template_id(), $subject, $data );
		}
	}

	/**
	 * Register the trigger hook.
	 */
	public function register(): void {
		add_action( $this->get_hook(), array( $this, 'handle' ), 10, $this->get_hook_args() );
	}
}
