<?php
/**
 * Dynamic custom trigger — instantiated from database configuration.
 *
 * @package SWPMail
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom user-defined email triggers.
 */
class SWPM_Trigger_Custom extends SWPM_Trigger_Base {

	/**

	 * Variable.
	 *
	 * @var string
	 */
	private string $key;

	/**

	 * Variable.
	 *
	 * @var string
	 */
	private string $label;

	/**

	 * Variable.
	 *
	 * @var string
	 */
	private string $hook;

	/**

	 * Variable.
	 *
	 * @var int
	 */
	private int $hook_args;

	/**

	 * Variable.
	 *
	 * @var string
	 */
	private string $template_id;

	/**

	 * Variable.
	 *
	 * @var string
	 */
	private string $subject_template;

	/**

	 * Variable.
	 *
	 * @var string
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.ParamCommentFullStop, Squiz.Commenting.VariableComment.Missing
	private string $recipient_type;

	/**
	 * Constructor.
	 *
	 * @param array $config Configuration array with keys: key, label, hook, hook_args, template_id, subject_template, recipient_type.
	 */
	public function __construct( array $config ) {
		$this->key              = sanitize_key( $config['key'] ?? '' );
		$this->label            = $config['label'] ?? '';
		$this->hook             = sanitize_key( $config['hook'] ?? '' );
		$this->hook_args        = max( 1, (int) ( $config['hook_args'] ?? 1 ) );
		$this->template_id      = sanitize_key( $config['template_id'] ?? '' );
		$this->subject_template = $config['subject_template'] ?? '';
		$this->recipient_type   = $config['recipient_type'] ?? 'subscribers';
	}

	/**
	 * Get key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return $this->key;
	}

	/**
	 * Get label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Get hook.
	 *
	 * @return string
	 */
	public function get_hook(): string {
		return $this->hook;
	}

	/**
	 * Get hook args.
	 *
	 * @return int
	 */
	public function get_hook_args(): int {
		return $this->hook_args;
	}

	/**
	 * Get template id.
	 *
	 * @return string
	 */
	public function get_template_id(): string {
		return $this->template_id;
	}

	/**
	 * Get subject.
	 *
	 * @param array $data Data.
	 * @return string
	 */
	public function get_subject( array $data ): string {
		$subject = $this->subject_template;
		foreach ( $data as $key => $value ) {
			$subject = str_replace( '{{' . $key . '}}', (string) $value, $subject );
		}
		return $subject;
	}

	/**
	 * Get recipients.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	public function get_recipients( array $data ): array {
		switch ( $this->recipient_type ) {
			case 'admin':
				$admin_email = get_option( 'admin_email' );
				return array(
					(object) array(
						'email' => $admin_email,
						'name'  => 'Admin',
						'token' => '',
					),
				);

			case 'hook_user':
				// Try to get user from first hook argument (user_id or user object).
				if ( ! empty( $data['_user_email'] ) ) {
					return array(
						(object) array(
							'email' => $data['_user_email'],
							'name'  => $data['_user_name'] ?? '',
							'token' => '',
						),
					);
				}
				return array();

			case 'subscribers':
			default:
				/**
				 * Subscriber.
				 *
				 * @var SWPM_Subscriber|null
				 */
				$subscriber = swpm( 'subscriber' );
				return $subscriber ? $subscriber->get_confirmed_by_frequency( 'instant' ) : array();
		}
	}

	/**
	 * Prepare data.
	 *
	 * @return array
	 * @param mixed ...$args Args.
	 */
	protected function prepare_data( ...$args ): array {
		$data = array();

		// Pass through all hook arguments as arg_0, arg_1, etc.
		foreach ( $args as $i => $arg ) {
			if ( is_scalar( $arg ) ) {
				$data[ 'arg_' . $i ] = $arg;
			}

			// If the arg is a WP_Post, extract useful fields.
			if ( $arg instanceof \WP_Post ) {
				$data['post_id']        = $arg->ID;
				$data['post_title']     = get_the_title( $arg );
				$data['post_url']       = get_permalink( $arg );
				$data['post_excerpt']   = has_excerpt( $arg ) ? get_the_excerpt( $arg ) : wp_trim_words( $arg->post_content, 30 );
				$data['post_thumbnail'] = get_the_post_thumbnail_url( $arg, 'large' ) ? get_the_post_thumbnail_url( $arg, 'large' ) : '';
				$data['author_name']    = get_the_author_meta( 'display_name', $arg->post_author );
			}

			// If the arg is a WP_User, extract useful fields.
			if ( $arg instanceof \WP_User ) {
				$data['user_id']     = $arg->ID;
				$data['username']    = $arg->display_name;
				$data['user_email']  = $arg->user_email;
				$data['_user_email'] = $arg->user_email;
				$data['_user_name']  = $arg->display_name;
			}

			// If it's a numeric user ID, try to resolve.
			if ( is_int( $arg ) && 0 === $i ) {
				$user = get_userdata( $arg );
				if ( $user ) {
					$data['user_id']     = $user->ID;
					$data['username']    = $user->display_name;
					$data['user_email']  = $user->user_email;
					$data['_user_email'] = $user->user_email;
					$data['_user_name']  = $user->display_name;
				}
			}
		}

		$data['site_name'] = get_bloginfo( 'name' );
		$data['site_url']  = home_url();

		/**
		 * Filter custom trigger data before sending.
		 *
		 * @since 1.1.0
		 * @param array  $data Prepared data.
		 * @param string $key  Trigger key.
		 * @param array  $args Raw hook arguments.
		 */
		return apply_filters( 'swpm_custom_trigger_data', $data, $this->key, $args );
	}
}
