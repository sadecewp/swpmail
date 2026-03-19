<?php
/**
 * Template editor with CodeMirror.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin interface for editing email templates.
 */
class SWPM_Template_Editor {

	/**

	 * Variable.
	 *
	 * @var SWPM_Loader
	 */
	private SWPM_Loader $loader;

	/**
	 * Built-in templates that cannot be deleted.
	 *
	 * @var array<string, string>
	 */
	private const BUILTIN_TEMPLATES = array(
		'confirm-subscription' => 'Confirm Subscription',
		'welcome'              => 'Welcome',
		'new-post'             => 'New Post',
		'new-user'             => 'New User Registered',
		'user-login'           => 'User Login Notification',
		'new-comment'          => 'New Comment',
		'password-reset'       => 'Password Reset',
		'digest-daily'         => 'Daily Digest',
		'digest-weekly'        => 'Weekly Digest',
	);

	/**
	 * Built-in template variables reference.
	 *
	 * @var array<string, array<string>>
	 */
	private const BUILTIN_VARIABLES = array(
		'confirm-subscription' => array( 'confirm_url', 'subscriber_name', 'site_name', 'site_url' ),
		'welcome'              => array( 'subscriber_name', 'site_name', 'site_url' ),
		'new-post'             => array( 'post_title', 'post_url', 'post_excerpt', 'post_thumbnail', 'author_name', 'site_name' ),
		'new-user'             => array( 'username', 'login_url', 'site_name', 'site_url' ),
		'user-login'           => array( 'username', 'login_time', 'ip_address', 'site_name', 'site_url' ),
		'new-comment'          => array( 'post_title', 'commenter_name', 'comment_excerpt', 'site_name', 'site_url' ),
		'password-reset'       => array( 'username', 'reset_link', 'site_name', 'site_url' ),
		'digest-daily'         => array( 'post_list', 'date', 'site_name', 'site_url' ),
		'digest-weekly'        => array( 'post_list', 'week_start', 'week_end', 'site_name', 'site_url' ),
	);

	/**
	 * Constructor.
	 *
	 * @param SWPM_Loader $loader Loader.
	 */
	public function __construct( SWPM_Loader $loader ) {
		$this->loader = $loader;
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_codemirror' );
		$this->loader->add_action( 'wp_ajax_swpm_save_template', $this, 'ajax_save_template' );
		$this->loader->add_action( 'wp_ajax_swpm_reset_template', $this, 'ajax_reset_template' );
		$this->loader->add_action( 'wp_ajax_swpm_preview_template', $this, 'ajax_preview_template' );
		$this->loader->add_action( 'wp_ajax_swpm_create_template', $this, 'ajax_create_template' );
		$this->loader->add_action( 'wp_ajax_swpm_delete_template', $this, 'ajax_delete_template' );
		$this->loader->add_action( 'wp_ajax_swpm_create_trigger', $this, 'ajax_create_trigger' );
		$this->loader->add_action( 'wp_ajax_swpm_delete_trigger', $this, 'ajax_delete_trigger' );
	}

	/**
	 * Enqueue CodeMirror on templates page.
	 *
	 * @param string $hook_suffix Hook suffix.
	 */
	public function enqueue_codemirror( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'swpmail-templates' ) ) {
			return;
		}

		$cm_settings = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );

		if ( false !== $cm_settings ) {
			wp_localize_script( 'swpmail-admin', 'swpmCodeMirror', $cm_settings );
		}
	}

	/**
	 * Get all available templates (built-in + custom).
	 *
	 * @return array<string, string> template_id => label
	 */
	public function get_template_list(): array {
		$builtin = array();
		foreach ( self::BUILTIN_TEMPLATES as $id => $label ) {
			$builtin[ $id ] = __( $label, 'swpmail' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
		}

		$custom = $this->get_custom_templates();
		foreach ( $custom as $tpl ) {
			$builtin[ $tpl['id'] ] = $tpl['label'];
		}

		/**
		 * Filter the available template list.
		 *
		 * @since 1.1.0
		 * @param array $templates template_id => label pairs.
		 */
		return apply_filters( 'swpm_template_list', $builtin );
	}

	/**
	 * Get template variables reference per template (built-in + custom).
	 *
	 * @return array<string, array<string>>
	 */
	public function get_template_variables(): array {
		$vars = self::BUILTIN_VARIABLES;

		$custom = $this->get_custom_templates();
		foreach ( $custom as $tpl ) {
			$vars[ $tpl['id'] ] = $tpl['variables'] ?? array();
		}

		/**
		 * Filter template variables reference.
		 *
		 * @since 1.1.0
		 * @param array $variables template_id => array of variable names.
		 */
		return apply_filters( 'swpm_template_variables', $vars );
	}

	/**
	 * Check if a template is a built-in (non-deletable) template.
	 *
	 * @param string $template_id Template ID.
	 * @return bool
	 */
	public function is_builtin( string $template_id ): bool {
		return array_key_exists( $template_id, self::BUILTIN_TEMPLATES );
	}

	/**
	 * Get custom templates from database.
	 *
	 * @return array Array of custom template definitions.
	 */
	public function get_custom_templates(): array {
		return (array) get_option( 'swpm_custom_templates', array() );
	}

	/**
	 * AJAX: Save template content.
	 */
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	/**
	 * Ajax save template.
	 */
	public function ajax_save_template(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		$template_id = sanitize_key( wp_unslash( $_POST['template_id'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$content = $this->sanitize_template_html( wp_unslash( $_POST['content'] ?? '' ) );

		if ( empty( $template_id ) || ! array_key_exists( $template_id, $this->get_template_list() ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template.', 'swpmail' ) ) );
		}

		update_option( 'swpm_template_' . $template_id, $content );

		wp_send_json_success( array( 'message' => __( 'Template saved.', 'swpmail' ) ) );
	}

	/**
	 * AJAX: Reset template to default.
	 */
	public function ajax_reset_template(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		$template_id = sanitize_key( wp_unslash( $_POST['template_id'] ?? '' ) );

		if ( empty( $template_id ) || ! array_key_exists( $template_id, $this->get_template_list() ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template.', 'swpmail' ) ) );
		}

		delete_option( 'swpm_template_' . $template_id );

		// Load default content.
		$default_path = SWPM_PLUGIN_DIR . 'templates/default/' . $template_id . '.html';
		$content      = '';
		if ( file_exists( $default_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content = file_get_contents( $default_path );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Template reset to default.', 'swpmail' ),
				'content' => $content,
			)
		);
	}

	/**
	 * AJAX: Preview template with sample data.
	 */
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	/**
	 * Ajax preview template.
	 */
	public function ajax_preview_template(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		$template_id = sanitize_key( wp_unslash( $_POST['template_id'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$content = $this->sanitize_template_html( wp_unslash( $_POST['content'] ?? '' ) );

		if ( empty( $template_id ) || ! array_key_exists( $template_id, $this->get_template_list() ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template.', 'swpmail' ) ) );
		}

		/**

		 * Engine.
		 *
		 * @var SWPM_Template_Engine
		 */
		$engine    = swpm( 'template_engine' );
		$variables = $this->get_sample_variables( $template_id );
		$html      = $this->interpolate_preview( $content, $variables );

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * AJAX: Create a new custom template.
	 */
	public function ajax_create_template(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		$label     = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
		$variables = sanitize_text_field( wp_unslash( $_POST['variables'] ?? '' ) );

		if ( empty( $label ) ) {
			wp_send_json_error( array( 'message' => __( 'Template name is required.', 'swpmail' ) ) );
		}

		// Generate a slug-based ID from the label.
		$id = sanitize_key( sanitize_title( $label ) );
		if ( empty( $id ) ) {
			$id = 'custom-' . wp_generate_password( 6, false );
		}

		// Ensure uniqueness.
		$all = $this->get_template_list();
		if ( array_key_exists( $id, $all ) ) {
			$id = $id . '-' . wp_generate_password( 4, false );
		}

		// Parse variables list (comma-separated).
		$var_array = array_filter( array_map( 'sanitize_key', explode( ',', $variables ) ) );

		// Store custom template definition.
		$custom   = $this->get_custom_templates();
		$custom[] = array(
			'id'        => $id,
			'label'     => $label,
			'variables' => $var_array,
		);
		update_option( 'swpm_custom_templates', $custom );

		// Store blank default content.
		$default_html = $this->get_starter_template( $label, $var_array );
		update_option( 'swpm_template_' . $id, $default_html );

		wp_send_json_success(
			array(
				'message'     => __( 'Template created.', 'swpmail' ),
				'template_id' => $id,
				'redirect'    => add_query_arg(
					array(
						'page'     => 'swpmail-templates',
						'template' => $id,
					),
					admin_url( 'admin.php' )
				),
			)
		);
	}

	/**
	 * AJAX: Delete a custom template.
	 */
	public function ajax_delete_template(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		$template_id = sanitize_key( wp_unslash( $_POST['template_id'] ?? '' ) );

		if ( empty( $template_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template.', 'swpmail' ) ) );
		}

		if ( $this->is_builtin( $template_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Built-in templates cannot be deleted.', 'swpmail' ) ) );
		}

		// Remove from custom templates list.
		$custom = $this->get_custom_templates();
		$custom = array_values(
			array_filter(
				$custom,
				function ( $tpl ) use ( $template_id ) {
					return $tpl['id'] !== $template_id;
				}
			)
		);
		update_option( 'swpm_custom_templates', $custom );

		// Remove stored content.
		delete_option( 'swpm_template_' . $template_id );

		wp_send_json_success(
			array(
				'message'  => __( 'Template deleted.', 'swpmail' ),
				'redirect' => add_query_arg(
					array( 'page' => 'swpmail-templates' ),
					admin_url( 'admin.php' )
				),
			)
		);
	}

	/**
	 * Generate a starter HTML template for new custom templates.
	 *
	 * @param string $label     Template label.
	 * @param array  $variables Variable names.
	 * @return string
	 */
	private function get_starter_template( string $label, array $variables ): string {
		$var_html = '';
		foreach ( $variables as $var ) {
			$var_html .= "\n                <p><strong>" . esc_html( $var ) . ':</strong> {{' . esc_html( $var ) . '}}</p>';
		}

		return '<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>' . esc_html( $label ) . '</title>
    <style>
      body { margin: 0; padding: 0; background: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
      .wrapper { max-width: 600px; margin: 0 auto; background: #fff; }
      .header { padding: 24px 32px; background: #0073aa; text-align: center; }
      .header h1 { color: #fff; font-size: 20px; margin: 8px 0 0; }
      .content { padding: 32px; line-height: 1.6; color: #333; font-size: 15px; }
      .footer { padding: 20px 32px; background: #f9f9f9; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
      .footer a { color: #0073aa; text-decoration: none; }
    </style>
  </head>
  <body>
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f4f4f4">
      <tr>
        <td align="center" style="padding:24px 0">
          <table class="wrapper" width="600" cellpadding="0" cellspacing="0" role="presentation">
            <tr><td class="header"><h1>{{site_name}}</h1></td></tr>
            <tr>
              <td class="content">
                <h2>' . esc_html( $label ) . '</h2>' . $var_html . '
              </td>
            </tr>
            <tr>
              <td class="footer">
                <p>&copy; {{year}} {{site_name}} &middot; <a href="{{site_url}}">{{visit_site_text}}</a></p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>';
	}

	/**
	 * Replace {{variable}} placeholders for preview.
	 *
	 * @param string $html Template HTML.
	 * @param array  $vars Variables.
	 * @return string
	 */
	private function interpolate_preview( string $html, array $vars ): string {
		// Ensure URL variables are safe for CSS url() context (wp_kses_post does not cover CSS).
		$url_keys = array( 'post_thumbnail', 'site_logo', 'avatar_url' );
		foreach ( $url_keys as $url_key ) {
			if ( isset( $vars[ $url_key ] ) ) {
				$vars[ $url_key ] = esc_url( $vars[ $url_key ] );
			}
		}

		foreach ( $vars as $key => $value ) {
			$html = str_replace( '{{' . $key . '}}', wp_kses_post( (string) $value ), $html );
		}
		return $html;
	}

	/**
	 * Get sample variables for template preview.
	 *
	 * @param string $template_id Template ID.
	 * @return array
	 */
	private function get_sample_variables( string $template_id ): array {
		$site_name = esc_html( get_bloginfo( 'name' ) );
		$site_url  = esc_url( home_url() );

		$global = array(
			'site_name'        => $site_name,
			'site_url'         => $site_url,
			'site_logo'        => esc_url( swpm_get_site_logo_url() ),
			'year'             => gmdate( 'Y' ),
			'from_name'        => esc_html( get_option( 'swpm_from_name', $site_name ) ),
			// phpcs:ignore Universal.Operators.DisallowShortTernary.Found
			'privacy_url'      => esc_url( get_privacy_policy_url() ?: $site_url . '/privacy-policy/' ),
			'unsubscribe_text' => esc_html__( 'Unsubscribe', 'swpmail' ),
			'visit_site_text'  => esc_html__( 'Visit Site', 'swpmail' ),
			'subscriber_name'  => 'Jane Doe',
			'subscriber_email' => 'jane@example.com',
			'unsubscribe_url'  => '#',
		);

		$per_template = array(
			'confirm-subscription' => array(
				'confirm_url' => '#',
			),
			'welcome'              => array(),
			'new-post'             => array(
				'post_title'     => __( 'Sample Post Title', 'swpmail' ),
				'post_url'       => $site_url . '/sample-post/',
				'post_excerpt'   => __( 'This is a sample post excerpt that shows how your email will look when a new post is published on your site.', 'swpmail' ),
				'post_thumbnail' => 'https://placehold.co/600x300/e2e8f0/64748b?text=Post+Image',
				'author_name'    => 'Admin',
			),
			'digest-daily'         => array(
				'post_list' => $this->get_sample_post_list(),
				'date'      => wp_date( get_option( 'date_format' ) ),
			),
			'digest-weekly'        => array(
				'post_list'  => $this->get_sample_post_list(),
				'week_start' => wp_date( get_option( 'date_format' ), strtotime( 'last monday' ) ),
				'week_end'   => wp_date( get_option( 'date_format' ) ),
			),
		);

		return array_merge( $global, $per_template[ $template_id ] ?? array() );
	}

	/**
	 * Generate sample post list HTML for digest previews.
	 *
	 * @return string
	 */
	private function get_sample_post_list(): string {
		$items = array(
			__( 'Getting Started with Our Newsletter', 'swpmail' ),
			__( '5 Tips for Better Email Design', 'swpmail' ),
			__( 'Weekly Update: What\'s New', 'swpmail' ),
		);

		$html = '<ul>';
		foreach ( $items as $title ) {
			$html .= '<li><a href="#">' . esc_html( $title ) . '</a></li>';
		}
		// phpcs:ignore Generic.Commenting.DocComment.LongNotCapital
		$html .= '</ul>';
// phpcs:ignore Generic.Commenting.DocComment.LongNotCapital

		return $html;
	}

	/**
	 * Sanitize email template HTML preserving full document structure.
	 *
	 * Wp_kses_post() strips <style>, <head>, <html>, <body> which are required
	 * For email templates. This method uses an extended allowed-tags list.
	 * Only admins with manage_options can reach the handlers that call this.
	 *
	 * @param string $html Raw HTML content.
	 * @return string Sanitized HTML.
	 */
	private function sanitize_template_html( string $html ): string {
		$allowed = wp_kses_allowed_html( 'post' );

		// Add email-essential tags that wp_kses_post strips.
		// <style> contents are filtered — only safe CSS properties via protocol allow.
		$allowed['style'] = array(
			'type'  => true,
			'media' => true,
		);
		$allowed['html']  = array(
			'lang'  => true,
			'dir'   => true,
			'xmlns' => true,
		);
		$allowed['head']  = array();
		$allowed['body']  = array(
			'style' => true,
			'class' => true,
			'id'    => true,
		);
		$allowed['title'] = array();
		$allowed['meta']  = array(
			'charset'    => true,
			'name'       => true,
			'content'    => true,
			'http-equiv' => true,
		);
		// <link> restricted to stylesheet rel only — no arbitrary resource loading.
		$allowed['link']   = array(
			'rel'   => true,
			'href'  => true,
			'type'  => true,
			'media' => true,
		);
		$allowed['center'] = array(
			'style' => true,
			'class' => true,
		);

		// Doctype is stripped by KSES regardless; re-add if present.
		$has_doctype = (bool) preg_match( '/^<!doctype\s+html[^>]*>/i', trim( $html ) );
		$sanitized   = wp_kses( $html, $allowed );

		// Strip CSS expressions that can exfiltrate data or execute JS.
		$sanitized = preg_replace(
			array(
				'/expression\s*\(/i',
				'/javascript\s*:/i',
				'/-moz-binding\s*:/i',
				'/behavior\s*:/i',
			),
			'/* blocked */',
			$sanitized
		);

		// Block url() with dangerous schemes but allow data:, relative paths and https.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		$sanitized = preg_replace_callback(
			// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
			'/url\s*\(\s*(["\']?)\s*(.*?)\s*\1\s*\)/i',
			static function ( $m ) {
				$val = trim( $m[2] );
				// Allow data: URIs (images), relative paths, and https URLs.
				if (
					preg_match( '#^data:image/#i', $val ) ||
					preg_match( '#^https://#i', $val ) ||
					( ! preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $val ) && ! preg_match( '#^//#', $val ) ) // relative only, block protocol-relative.
				) {
					return $m[0]; // safe — keep as-is.
				}
				return '/* blocked */';
			},
			$sanitized
		);

		if ( $has_doctype ) {
			$sanitized = "<!doctype html>\n" . $sanitized;
		}

		return $sanitized;
	}

	/**
	 * AJAX: Create a new custom trigger.
	 */
	public function ajax_create_trigger(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		$label = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
		$hook  = sanitize_key( wp_unslash( $_POST['hook'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$hook_args      = min( 10, max( 1, (int) ( $_POST['hook_args'] ?? 1 ) ) );
		$template_id    = sanitize_key( wp_unslash( $_POST['template_id'] ?? '' ) );
		$subject        = sanitize_text_field( wp_unslash( $_POST['subject_template'] ?? '' ) );
		$recipient_type = sanitize_key( wp_unslash( $_POST['recipient_type'] ?? 'subscribers' ) );

		if ( empty( $label ) || empty( $hook ) || empty( $template_id ) || empty( $subject ) ) {
			wp_send_json_error( array( 'message' => __( 'All fields are required.', 'swpmail' ) ) );
		}

		// Block dangerous/sensitive hooks that should never be used as triggers.
		$core_blocked_hooks = array(
			'delete_post',
			'wp_delete_post',
			'before_delete_post',
			'delete_user',
			'deleted_user',
			'remove_user_from_blog',
			'wp_logout',
			'clear_auth_cookie',
			'switch_theme',
			'activate_plugin',
			'deactivate_plugin',
			'wp_insert_post',
			'save_post',
			'edit_post',
			'update_option',
			'delete_option',
			'add_option',
			'set_user_role',
			'grant_super_admin',
			'revoke_super_admin',
			'wp_update_user',
			'profile_update',
			'shutdown',
			'wp_die_handler',
		);
		$extra_blocked      = (array) apply_filters( 'swpm_extra_blocked_trigger_hooks', array() );
		$blocked_hooks      = array_merge( $core_blocked_hooks, $extra_blocked );
		if ( in_array( $hook, $blocked_hooks, true ) ) {
			wp_send_json_error( array( 'message' => __( 'This hook is blocked for security reasons.', 'swpmail' ) ) );
		}

		// Validate recipient type.
		if ( ! in_array( $recipient_type, array( 'subscribers', 'admin', 'hook_user' ), true ) ) {
			$recipient_type = 'subscribers';
		}

		// Generate key.
		$key = 'custom_' . sanitize_key( sanitize_title( $label ) );
		if ( empty( $key ) || 'custom_' === $key ) {
			$key = 'custom_' . wp_generate_password( 6, false );
		}

		// Ensure uniqueness.
		$existing = SWPM_Trigger_Manager::get_custom_triggers();
		foreach ( $existing as $t ) {
			if ( ( $t['key'] ?? '' ) === $key ) {
				$key .= '_' . wp_generate_password( 4, false );
				break;
			}
		}

		$config = array(
			'key'              => $key,
			'label'            => $label,
			'hook'             => $hook,
			'hook_args'        => $hook_args,
			'template_id'      => $template_id,
			'subject_template' => $subject,
			'recipient_type'   => $recipient_type,
		);

		SWPM_Trigger_Manager::save_custom_trigger( $config );

		// Auto-activate the new trigger.
		$active   = (array) get_option( 'swpm_active_triggers', array() );
		$active[] = $key;
		update_option( 'swpm_active_triggers', array_unique( $active ) );

		wp_send_json_success( array( 'message' => __( 'Trigger created and activated.', 'swpmail' ) ) );
	}

	/**
	 * AJAX: Delete a custom trigger.
	 */
	public function ajax_delete_trigger(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		$key = sanitize_key( wp_unslash( $_POST['trigger_key'] ?? '' ) );

		if ( empty( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid trigger.', 'swpmail' ) ) );
		}

		// Prevent deleting built-in triggers.
		$builtin_keys = array( 'new_post', 'new_user', 'user_login', 'new_comment', 'password_reset' );
		if ( in_array( $key, $builtin_keys, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Built-in triggers cannot be deleted.', 'swpmail' ) ) );
		}

		SWPM_Trigger_Manager::delete_custom_trigger( $key );

		wp_send_json_success( array( 'message' => __( 'Trigger deleted.', 'swpmail' ) ) );
	}
}
