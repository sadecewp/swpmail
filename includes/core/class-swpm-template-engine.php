<?php
/**
 * Template engine.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SWPM_Template_Engine.
 */
class SWPM_Template_Engine {

	/**
	 * Render a template with variables.
	 *
	 * @param string $template_id Template identifier.
	 * @param array  $variables   Template variables.
	 * @return string Rendered HTML.
	 */
	public function render( string $template_id, array $variables = array() ): string {
		$template_id = sanitize_key( $template_id );
		$html        = $this->load_template( $template_id );

		$variables = array_merge( $this->get_global_variables(), $variables );
		return $this->interpolate( $html, $variables );
	}

	/**
	 * Load a template by ID with priority: filter > theme > DB > plugin default.
	 *
	 * @param string $id Template ID.
	 * @return string Template HTML.
	 */
	private function load_template( string $id ): string {
		$custom_path = (string) apply_filters( 'swpm_template_path', '', $id );
		$theme_path  = get_stylesheet_directory() . '/swpmail/templates/' . $id . '.html';
		$db_template = get_option( 'swpm_template_' . $id, '' );
		$default     = SWPM_PLUGIN_DIR . 'templates/default/' . $id . '.html';

		if ( ! empty( $custom_path ) && file_exists( $custom_path ) ) {
			// Validate path is within allowed directories to prevent path traversal.
			$real_custom  = realpath( $custom_path );
			$allowed_dirs = array_filter(
				array(
					realpath( SWPM_PLUGIN_DIR ),
					realpath( get_stylesheet_directory() ),
					realpath( get_template_directory() ),
				)
			);
			$is_safe      = false;
			foreach ( $allowed_dirs as $dir ) {
				if ( $real_custom && 0 === strpos( $real_custom, $dir . DIRECTORY_SEPARATOR ) ) {
					$is_safe = true;
					break;
				}
			}
			if ( $is_safe ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				return (string) file_get_contents( $real_custom );
			}
			swpm_log( 'warning', 'Template path rejected (outside allowed directories): ' . $custom_path );
		}
		if ( file_exists( $theme_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return (string) file_get_contents( $theme_path );
		}
		if ( ! empty( $db_template ) ) {
			return $db_template;
		}
		if ( file_exists( $default ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return (string) file_get_contents( $default );
		}

		swpm_log( 'warning', "Template not found: {$id}" );
		return '{{content}}';
	}

	/**
	 * Replace {{variable}} placeholders.
	 *
	 * @param string $html Template HTML.
	 * @param array  $vars Variables.
	 * @return string
	 */
	private function interpolate( string $html, array $vars ): string {
		// URL-type variables get esc_url(); everything else gets wp_kses_post().
		$url_keys = array( 'unsubscribe_url', 'confirm_url', 'site_url', 'privacy_url', 'manage_url' );
		foreach ( $vars as $key => $value ) {
			$escaped = in_array( $key, $url_keys, true ) || str_ends_with( $key, '_url' )
				? esc_url( (string) $value )
				: wp_kses_post( (string) $value );
			$html    = str_replace( '{{' . $key . '}}', $escaped, $html );
		}
		return $html;
	}

	/**
	 * Get global template variables.
	 *
	 * @return array
	 */
	private function get_global_variables(): array {
		return array(
			'site_name'        => esc_html( get_bloginfo( 'name' ) ),
			'site_url'         => esc_url( home_url() ),
			'site_logo'        => esc_url( swpm_get_site_logo_url() ),
			'year'             => gmdate( 'Y' ),
			'from_name'        => esc_html( get_option( 'swpm_from_name', get_bloginfo( 'name' ) ) ),
			'privacy_url'      => esc_url( get_privacy_policy_url() ),
			'unsubscribe_text' => esc_html__( 'Unsubscribe', 'swpmail' ),
			'visit_site_text'  => esc_html__( 'Visit Site', 'swpmail' ),
		);
	}

	/**
	 * Get list of available template IDs.
	 *
	 * @return array
	 */
	public function get_template_ids(): array {
		return array(
			'confirm-subscription',
			'welcome',
			'new-post',
			'new-user',
			'user-login',
			'new-comment',
			'password-reset',
			'digest-daily',
			'digest-weekly',
		);
	}

	/**
	 * Get raw template content for editing.
	 *
	 * @param string $template_id Template ID.
	 * @return string
	 */
	public function get_raw( string $template_id ): string {
		$template_id = sanitize_key( $template_id );
		return $this->load_template( $template_id );
	}

	/**
	 * Save template to DB.
	 *
	 * @param string $template_id Template ID.
	 * @param string $content     HTML content.
	 */
	public function save( string $template_id, string $content ): void {
		$template_id = sanitize_key( $template_id );
		update_option( 'swpm_template_' . $template_id, $content );
	}
}
