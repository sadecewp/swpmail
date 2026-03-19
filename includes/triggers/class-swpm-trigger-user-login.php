<?php // phpcs:disable Internal.Exception
/**
 * Trigger: User Login.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SWPM_Trigger_User_Login.
 */
class SWPM_Trigger_User_Login extends SWPM_Trigger_Base {

	/**
	 * Get key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'user_login';
	}

	/**
	 * Get label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'User Login', 'swpmail' );
	}

	/**
	 * Get hook.
	 *
	 * @return string
	 */
	public function get_hook(): string {
		return 'wp_login';
	}

	/**
	 * Get hook args.
	 *
	 * @return int
	 */
	public function get_hook_args(): int {
		return 2;
	}

	/**
	 * Get template id.
	 *
	 * @return string
	 */
	public function get_template_id(): string {
		return 'user-login';
	}

	/**
	 * Get subject.
	 *
	 * @param array $data Data.
	 * @return string
	 */
	public function get_subject( array $data ): string {
		return sprintf(
			/* translators: %s: username */
			__( 'New login detected: %s', 'swpmail' ),
			$data['username']
		);
	}

	/**
	 * Get recipients.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	public function get_recipients( array $data ): array {
		$user = get_userdata( $data['user_id'] );
		return $user
			? array(
				(object) array(
					'email' => $user->user_email,
					'name'  => $user->display_name,
					'token' => '',
				),
			)
			: array();
	}

	/**
	 * Prepare data.
	 *
	 * @return array
	 * @param mixed ...$args Args.
	 */
	protected function prepare_data( ...$args ): array {
		list( $user_login, $user ) = $args;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$ip = filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ), FILTER_VALIDATE_IP );
		return array(
			'user_id'    => $user->ID,
			'username'   => $user->display_name,
			'login_time' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'ip_address' => $ip ? $ip : 'unknown',
		);
	}
}
