<?php
/**
 * Trigger: User Login.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Trigger_User_Login extends SWPM_Trigger_Base {

	public function get_key(): string {
		return 'user_login';
	}

	public function get_label(): string {
		return __( 'User Login', 'swpmail' );
	}

	public function get_hook(): string {
		return 'wp_login';
	}

	public function get_hook_args(): int {
		return 2;
	}

	public function get_template_id(): string {
		return 'user-login';
	}

	public function get_subject( array $data ): string {
		return sprintf(
			/* translators: %s: username */
			__( 'New login detected: %s', 'swpmail' ),
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
		list( $user_login, $user ) = $args;
		return array(
			'user_id'    => $user->ID,
			'username'   => $user->display_name,
			'login_time' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'ip_address' => filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ), FILTER_VALIDATE_IP ) ?: 'unknown', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		);
	}
}
