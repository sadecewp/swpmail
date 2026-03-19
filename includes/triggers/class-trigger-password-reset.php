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

class SWPM_Trigger_Password_Reset extends SWPM_Trigger_Base {

	public function get_key(): string {
		return 'password_reset';
	}

	public function get_label(): string {
		return __( 'Password Reset Requested', 'swpmail' );
	}

	public function get_hook(): string {
		return 'retrieve_password_key';
	}

	public function get_template_id(): string {
		return 'password-reset';
	}

	public function get_hook_args(): int {
		return 2;
	}

	public function get_subject( array $data ): string {
		return sprintf(
			/* translators: %s: username */
			__( 'Password reset for %s', 'swpmail' ),
			$data['username']
		);
	}

	public function get_recipients( array $data ): array {
		$user = get_user_by( 'login', $data['user_login'] );
		return $user
			? array( (object) array( 'email' => $user->user_email, 'name' => $user->display_name, 'token' => '' ) )
			: array();
	}

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
