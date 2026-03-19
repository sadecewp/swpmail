<?php
/**
 * Trigger: New User Registered.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Trigger_New_User extends SWPM_Trigger_Base {

	public function get_key(): string {
		return 'new_user';
	}

	public function get_label(): string {
		return __( 'New User Registered', 'swpmail' );
	}

	public function get_hook(): string {
		return 'user_register';
	}

	public function get_template_id(): string {
		return 'new-user';
	}

	public function get_subject( array $data ): string {
		return sprintf(
			/* translators: %s: username */
			__( 'Welcome, %s!', 'swpmail' ),
			$data['username']
		);
	}

	public function get_recipients( array $data ): array {
		$user = get_userdata( $data['user_id'] );
		return $user
			? array( (object) array( 'email' => $user->user_email, 'name' => $user->display_name, 'token' => '' ) )
			: array();
	}

	protected function prepare_data( ...$args ): array {
		$user_id = (int) $args[0];
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		return array(
			'user_id'   => $user_id,
			'username'  => $user->display_name,
			'login_url' => wp_login_url(),
		);
	}
}
