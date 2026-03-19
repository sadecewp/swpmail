<?php
/**
 * Alarm channel interface.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface SWPM_Alarm_Channel_Interface {

	/**
	 * Unique key for this channel.
	 *
	 * @return string
	 */
	public function get_key(): string;

	/**
	 * Human-readable label.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Send an alarm notification.
	 *
	 * @param array $event Event data: type, message, context, timestamp.
	 * @return bool True on success.
	 */
	public function send( array $event ): bool;

	/**
	 * Send a test notification.
	 *
	 * @return bool True on success.
	 */
	public function test(): bool;
}
