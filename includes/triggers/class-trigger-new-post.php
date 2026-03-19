<?php
/**
 * Trigger: New Post Published.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Trigger_New_Post extends SWPM_Trigger_Base {

	public function get_key(): string {
		return 'new_post';
	}

	public function get_label(): string {
		return __( 'New Post Published', 'swpmail' );
	}

	public function get_hook(): string {
		return 'transition_post_status';
	}

	public function get_hook_args(): int {
		return 3;
	}

	public function get_template_id(): string {
		return 'new-post';
	}

	public function get_subject( array $data ): string {
		return sprintf(
			/* translators: %s: post title */
			__( 'New Post: %s', 'swpmail' ),
			$data['post_title']
		);
	}

	public function get_recipients( array $data ): array {
		/** @var SWPM_Subscriber $subscriber */
		$subscriber = swpm( 'subscriber' );
		return $subscriber ? $subscriber->get_confirmed_by_frequency( 'instant' ) : array();
	}

	protected function prepare_data( ...$args ): array {
		list( $new_status, $old_status, $post ) = $args;

		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return array();
		}

		$allowed = apply_filters( 'swpm_trigger_new_post_types', array( 'post' ) );
		if ( ! in_array( $post->post_type, $allowed, true ) ) {
			return array();
		}

		return array(
			'post_id'        => $post->ID,
			'post_title'     => get_the_title( $post ),
			'post_url'       => get_permalink( $post ),
			'post_excerpt'   => has_excerpt( $post )
								? get_the_excerpt( $post )
								: wp_trim_words( $post->post_content, 30 ),
			'post_thumbnail' => get_the_post_thumbnail_url( $post, 'large' ) ?: '',
			'author_name'    => get_the_author_meta( 'display_name', $post->post_author ),
		);
	}
}
