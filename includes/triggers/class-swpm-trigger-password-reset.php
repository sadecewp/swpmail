<?php
/**
 * Trigger: Password Reset Requested.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SWPM_Trigger_Password_Reset.
 */
class SWPM_Trigger_Password_Reset extends SWPM_Trigger_Base {

	/**
	 * Get key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'password_reset';
	}

	/**
	 * Get label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Password Reset Requested', 'swpmail' );
	}

	/**
	 * Get hook.
	 *
	 * @return string
	 */
	public function get_hook(): string {
		return 'retrieve_password_key';
	}

	/**
	 * Get template id.
	 *
	 * @return string
	 */
	public function get_template_id(): string {
		return 'password-reset';
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
	 * Get subject.
	 *
	 * @param array $data Data.
	 * @return string
	 */
	public function get_subject( array $data ): string {
		return sprintf(
			/* translators: %s: username */
			__( 'Password reset for %s', 'swpmail' ),
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
		$user = get_user_by( 'login', $data['user_login'] );
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
		$user_login = $args[0];
		$key        = $args[1] ?? '';
		$user       = get_user_by( 'login', $user_login );
		if ( ! $user ) {
			return array();
		}

		return array(
			'user_login' => $user_login,
			'username'   => $user->display_name,
			'reset_link' => network_site_url( "wp-login.php?action=rp&key={$key}&login=" . rawurlencode( $user_login ), 'login' ),
		);
	}
}
