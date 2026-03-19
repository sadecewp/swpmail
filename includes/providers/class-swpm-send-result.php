<?php
/**
 * Send result DTO.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable value object representing a send result.
 */
final class SWPM_Send_Result {

	/**

	 * Variable.
	 *
	 * @var bool
	 */
	private bool $success;

	/**

	 * Variable.
	 *
	 * @var string
	 */
	private string $message_id;

	/**

	 * Variable.
	 *
	 * @var string
	 */
	private string $error_message;

	/**

	 * Variable.
	 *
	 * @var string
	 */
	private string $error_code;

	/**
	 * Raw API response.
	 *
	 * @var array
	 */
	private array $raw_response;

	/**
	 * Constructor.
	 *
	 * @param bool   $success       Whether the send was successful.
	 * @param string $message_id    Message ID from provider.
	 * @param string $error_message Error message on failure.
	 * @param string $error_code    Error code on failure.
	 * @param array  $raw_response  Raw API response.
	 */
	private function __construct(
		bool $success,
		string $message_id = '',
		string $error_message = '',
		string $error_code = '',
		array $raw_response = array()
	) {
		$this->success       = $success;
		$this->message_id    = $message_id;
		$this->error_message = $error_message;
		$this->error_code    = $error_code;
		$this->raw_response  = $raw_response;
	}

	/**
	 * Create a success result.
	 *
	 * @param string $message_id Provider message ID.
	 * @param array  $raw        Raw response data.
	 * @return self
	 */
	public static function success( string $message_id = '', array $raw = array() ): self {
		return new self( true, $message_id, '', '', $raw );
	}

	/**
	 * Create a failure result.
	 *
	 * @param string $error_message Human readable error.
	 * @param string $error_code    Error code identifier.
	 * @param array  $raw           Raw response data.
	 * @return self
	 */
	public static function failure( string $error_message, string $error_code = '', array $raw = array() ): self {
		return new self( false, '', $error_message, $error_code, $raw );
	}

	/**
	 * Check if success.
	 *
	 * @return bool
	 */
	public function is_success(): bool {
		return $this->success;
	}

	/**
	 * Get message id.
	 *
	 * @return string
	 */
	public function get_message_id(): string {
		return $this->message_id;
	}

	/**
	 * Get error message.
	 *
	 * @return string
	 */
	public function get_error_message(): string {
		return $this->error_message;
	}

	/**
	 * Get error code.
	 *
	 * @return string
	 */
	public function get_error_code(): string {
		return $this->error_code;
	}

	/**
	 * Get raw response.
	 *
	 * @return array
	 */
	public function get_raw_response(): array {
		return $this->raw_response;
	}
}
