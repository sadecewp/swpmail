<?php
/**
 * Trigger: New Comment Posted.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Trigger_New_Comment extends SWPM_Trigger_Base {

	public function get_key(): string {
		return 'new_comment';
	}

	public function get_label(): string {
		return __( 'New Comment Posted', 'swpmail' );
	}

	public function get_hook(): string {
		return 'comment_post';
	}

	public function get_hook_args(): int {
		return 3;
	}

	public function get_template_id(): string {
		return 'new-comment';
	}

	public function get_subject( array $data ): string {
		return sprintf(
			/* translators: %s: post title */
			__( 'New comment on: %s', 'swpmail' ),
			$data['post_title']
		);
	}

	public function get_recipients( array $data ): array {
		$post   = get_post( $data['post_id'] );
		if ( ! $post ) {
			return array();
		}
		$author = get_userdata( $post->post_author );
		return $author
			? array( (object) array( 'email' => $author->user_email, 'name' => $author->display_name, 'token' => '' ) )
			: array();
	}

	protected function prepare_data( ...$args ): array {
		$comment_id = (int) $args[0];
		$approved   = $args[1];
		$comment    = get_comment( $comment_id );

		if ( ! $comment || ! $approved ) {
			return array();
		}

		$post = get_post( $comment->comment_post_ID );
		if ( ! $post ) {
			return array();
		}

		return array(
			'post_id'         => $post->ID,
			'post_title'      => get_the_title( $post ),
			'commenter_name'  => $comment->comment_author,
			'comment_excerpt' => wp_trim_words( $comment->comment_content, 20 ),
		);
	}
}
