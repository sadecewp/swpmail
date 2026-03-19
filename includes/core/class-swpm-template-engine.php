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
	 * Get the current site locale, normalized to a slug used in template directories.
	 *
	 * The function checks (in order) the locale for the current user, otherwise
	 * falls back to the site locale.  The returned value is sanitized so it is
	 * safe to use as a directory name.
	 *
	 * @return string Locale slug, e.g. "de_DE", "tr_TR", "ja".
	 */
	private function get_locale(): string {
		$locale = determine_locale();
		// Strip any charset suffix (e.g. "de_DE.UTF-8" → "de_DE").
		$locale = preg_replace( '/[^a-zA-Z_].*$/', '', $locale );
		return sanitize_key( $locale );
	}

	/**
	 * Load a template by ID with locale-aware priority:
	 * filter > theme (locale) > theme (default) > DB (locale) > DB (default)
	 * > plugin locale file > plugin default file.
	 *
	 * @param string $id     Template ID.
	 * @param string $locale Optional locale override. Defaults to the current site locale.
	 * @return string Template HTML.
	 */
	private function load_template( string $id, string $locale = '' ): string {
		if ( '' === $locale ) {
			$locale = $this->get_locale();
		}

		$custom_path         = (string) apply_filters( 'swpm_template_path', '', $id, $locale );
		$theme_locale_path   = get_stylesheet_directory() . '/swpmail/templates/' . $locale . '/' . $id . '.html';
		$theme_default_path  = get_stylesheet_directory() . '/swpmail/templates/' . $id . '.html';
		$db_locale_template  = ( 'en' !== $locale && 'en_US' !== $locale )
			? get_option( 'swpm_template_' . $locale . '_' . $id, '' )
			: '';
		$db_default_template = get_option( 'swpm_template_' . $id, '' );
		$locale_file         = SWPM_PLUGIN_DIR . 'templates/' . $locale . '/' . $id . '.html';
		$default_file        = SWPM_PLUGIN_DIR . 'templates/default/' . $id . '.html';

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
		if ( file_exists( $theme_locale_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return (string) file_get_contents( $theme_locale_path );
		}
		if ( file_exists( $theme_default_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return (string) file_get_contents( $theme_default_path );
		}
		if ( ! empty( $db_locale_template ) ) {
			return $db_locale_template;
		}
		if ( ! empty( $db_default_template ) ) {
			return $db_default_template;
		}
		if ( file_exists( $locale_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return (string) file_get_contents( $locale_file );
		}
		if ( file_exists( $default_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return (string) file_get_contents( $default_file );
		}

		swpm_log( 'warning', "Template not found: {$id} (locale: {$locale})" );
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
	 * Get raw template content for editing, respecting the current locale.
	 *
	 * @param string $template_id Template ID.
	 * @param string $locale      Optional locale override.
	 * @return string
	 */
	public function get_raw( string $template_id, string $locale = '' ): string {
		$template_id = sanitize_key( $template_id );
		if ( '' === $locale ) {
			$locale = $this->get_locale();
		}
		return $this->load_template( $template_id, $locale );
	}

	/**
	 * Save template to DB for a specific locale.
	 *
	 * When locale is "en", "en_US", or empty, the template is saved under the
	 * legacy key "swpm_template_{id}" so it continues to serve as the default
	 * fallback.  For all other locales the key is "swpm_template_{locale}_{id}".
	 *
	 * @param string $template_id Template ID.
	 * @param string $content     HTML content.
	 * @param string $locale      Optional locale. Defaults to current site locale.
	 */
	public function save( string $template_id, string $content, string $locale = '' ): void {
		$template_id = sanitize_key( $template_id );
		if ( '' === $locale ) {
			$locale = $this->get_locale();
		}
		if ( '' === $locale || 'en' === $locale || 'en_us' === $locale ) {
			update_option( 'swpm_template_' . $template_id, $content );
		} else {
			update_option( 'swpm_template_' . $locale . '_' . $template_id, $content );
		}
	}

	/**
	 * Delete the saved (DB) version of a template for a specific locale.
	 *
	 * @param string $template_id Template ID.
	 * @param string $locale      Optional locale. Defaults to current site locale.
	 */
	public function reset( string $template_id, string $locale = '' ): void {
		$template_id = sanitize_key( $template_id );
		if ( '' === $locale ) {
			$locale = $this->get_locale();
		}
		if ( '' === $locale || 'en' === $locale || 'en_us' === $locale ) {
			delete_option( 'swpm_template_' . $template_id );
		} else {
			delete_option( 'swpm_template_' . $locale . '_' . $template_id );
		}
	}

	/**
	 * Load the pristine default template file for a given template and locale.
	 *
	 * Locale file is preferred; falls back to the default (English) file.
	 *
	 * @param string $template_id Template ID.
	 * @param string $locale      Optional locale override.
	 * @return string
	 */
	public function get_default_file_content( string $template_id, string $locale = '' ): string {
		$template_id = sanitize_key( $template_id );
		if ( '' === $locale ) {
			$locale = $this->get_locale();
		}
		$locale_file  = SWPM_PLUGIN_DIR . 'templates/' . $locale . '/' . $template_id . '.html';
		$default_file = SWPM_PLUGIN_DIR . 'templates/default/' . $template_id . '.html';
		if ( file_exists( $locale_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return (string) file_get_contents( $locale_file );
		}
		if ( file_exists( $default_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return (string) file_get_contents( $default_file );
		}
		return '';
	}
}
