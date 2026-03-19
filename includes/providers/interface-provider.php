<?php
/**
 * Provider interface (contract).
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface SWPM_Provider_Interface {

	/**
	 * Send an email.
	 *
	 * @param string $to          Recipient email.
	 * @param string $subject     Subject line.
	 * @param string $body        HTML body.
	 * @param array  $headers     Additional headers.
	 * @param array  $attachments File paths.
	 * @return SWPM_Send_Result
	 */
	public function send(
		string $to,
		string $subject,
		string $body,
		array $headers = array(),
		array $attachments = array()
	): SWPM_Send_Result;

	/**
	 * Get provider unique key.
	 *
	 * @return string
	 */
	public function get_key(): string;

	/**
	 * Get provider display label.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Test the connection (send a test email).
	 *
	 * @return SWPM_Send_Result
	 */
	public function test_connection(): SWPM_Send_Result;
}
