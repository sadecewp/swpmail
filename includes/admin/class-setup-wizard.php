<?php
/**
 * Setup Wizard — shown on first activation.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Setup_Wizard {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_swpm_wizard_save_and_test', array( $this, 'ajax_save_and_test' ) );
		add_action( 'wp_ajax_swpm_wizard_skip', array( $this, 'ajax_skip' ) );
	}

	/**
	 * Register hidden admin page.
	 */
	public function register_page(): void {
		add_submenu_page(
			'',
			__( 'SWPMail Setup', 'swpmail' ),
			__( 'SWPMail Setup', 'swpmail' ),
			'manage_options',
			'swpmail-setup',
			array( $this, 'display' )
		);
	}

	/**
	 * Redirect to wizard after activation (first time only).
	 */
	public function maybe_redirect(): void {
		if ( ! get_transient( 'swpm_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'swpm_activation_redirect' );

		// Don't redirect if setup already done.
		if ( get_option( 'swpm_setup_complete' ) ) {
			return;
		}

		// Don't redirect on multisite bulk activation or WP-CLI.
		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=swpmail-setup' ) );
		exit;
	}

	/**
	 * Render wizard page.
	 */
	public function display(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SWPM_PLUGIN_DIR . 'admin/partials/display-setup-wizard.php';
	}

	/**
	 * Enqueue wizard-specific assets.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'admin_page_swpmail-setup' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'swpmail-admin',
			SWPM_PLUGIN_URL . 'admin/css/swpmail-admin.css',
			array(),
			SWPM_VERSION
		);

		wp_enqueue_style(
			'swpmail-setup-wizard',
			SWPM_PLUGIN_URL . 'admin/css/swpmail-setup-wizard.css',
			array( 'swpmail-admin' ),
			SWPM_VERSION
		);

		wp_enqueue_script(
			'swpmail-setup-wizard',
			SWPM_PLUGIN_URL . 'admin/js/swpmail-setup-wizard.js',
			array( 'jquery' ),
			SWPM_VERSION,
			true
		);

		wp_localize_script( 'swpmail-setup-wizard', 'swpmWizard', array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'swpm_wizard_nonce' ),
			'dashboardUrl' => admin_url( 'admin.php?page=swpmail' ),
			'i18n'         => array(
				'testing'     => __( 'Sending test email...', 'swpmail' ),
				'testSuccess' => __( 'Test email sent successfully! Your mailer is configured correctly.', 'swpmail' ),
				'testFailed'  => __( 'Test failed: ', 'swpmail' ),
				'saving'      => __( 'Saving...', 'swpmail' ),
				'selectProvider' => __( 'Please select a mailer first.', 'swpmail' ),
			),
		) );
	}

	/* ------------------------------------------------------------------
	 * AJAX: Save settings + send test email
	 * ----------------------------------------------------------------*/

	/**
	 * Save wizard settings and send a test email.
	 */
	public function ajax_save_and_test(): void {
		check_ajax_referer( 'swpm_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		// Prevent concurrent wizard completions.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$locked = $wpdb->get_var( "SELECT GET_LOCK('swpm_wizard_lock', 0)" );
		if ( '1' !== $locked ) {
			wp_send_json_error( array( 'message' => __( 'Setup wizard is already in progress.', 'swpmail' ) ), 409 );
		}

		try {
		// --- Common settings ------------------------------------------------
		$allowed  = array( 'phpmail', 'smtp', 'sendlayer', 'smtpcom', 'gmail', 'outlook', 'mailgun', 'sendgrid', 'postmark', 'brevo', 'ses', 'resend', 'elasticemail', 'mailjet', 'mailersend', 'smtp2go', 'sparkpost', 'zoho' );
		$provider = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : 'phpmail';    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( ! in_array( $provider, $allowed, true ) ) {
			$provider = 'phpmail';
		}

		update_option( 'swpm_mail_provider', $provider );

		$from_name  = isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '';
		$from_email = isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '';

		if ( $from_name ) {
			update_option( 'swpm_from_name', $from_name );
		}
		if ( $from_email ) {
			update_option( 'swpm_from_email', $from_email );
		}

		// --- Provider-specific settings -------------------------------------
		$this->save_provider_fields( $_POST );

		// --- Send test email ------------------------------------------------
		$factory  = new SWPM_Provider_Factory();
		$instance = $factory->make();

		$admin_email = get_option( 'admin_email' );
		$subject     = __( '[SWPMail] Setup Wizard Test', 'swpmail' );
		$body        = sprintf(
			/* translators: %s: site name */
			__( 'Congratulations! SWPMail on %s is configured correctly. This test email was sent during the setup wizard.', 'swpmail' ),
			get_bloginfo( 'name' )
		);

		$result = $instance->send( $admin_email, $subject, $body );

		if ( $result->is_success() ) {
			update_option( 'swpm_setup_complete', true );
			wp_send_json_success( array(
				'message' => __( 'Test email sent successfully! Your mailer is working.', 'swpmail' ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
			) );
		}
		} finally {
			// Always release the wizard lock, even on error / wp_send_json exit.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "SELECT RELEASE_LOCK('swpm_wizard_lock')" );
		}
	}

	/**
	 * Skip the wizard.
	 */
	public function ajax_skip(): void {
		check_ajax_referer( 'swpm_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		update_option( 'swpm_setup_complete', true );
		wp_send_json_success();
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------*/

	/**
	 * Save provider-specific fields from POST data.
	 *
	 * @param array $data Raw $_POST data.
	 */
	private function save_provider_fields( array $data ): void {
		$fields = array(
			// SMTP.
			'swpm_smtp_host'                   => 'text',
			'swpm_smtp_port'                   => 'int',
			'swpm_smtp_encryption'             => 'text',
			'swpm_smtp_username'               => 'text',
			'swpm_smtp_password_enc'           => 'encrypt',
			// Mailgun.
			'swpm_mailgun_api_key_enc'         => 'encrypt',
			'swpm_mailgun_domain'              => 'text',
			'swpm_mailgun_region'              => 'text',
			// SendGrid.
			'swpm_sendgrid_api_key_enc'        => 'encrypt',
			// Postmark.
			'swpm_postmark_server_token_enc'   => 'encrypt',
			'swpm_postmark_message_stream'     => 'text',
			// Brevo.
			'swpm_brevo_api_key_enc'           => 'encrypt',
			// Amazon SES.
			'swpm_ses_access_key_enc'          => 'encrypt',
			'swpm_ses_secret_key_enc'          => 'encrypt',
			'swpm_ses_region'                  => 'text',
			// Resend.
			'swpm_resend_api_key_enc'          => 'encrypt',
			// SendLayer.
			'swpm_sendlayer_api_key_enc'       => 'encrypt',
			// SMTP.com.
			'swpm_smtpcom_api_key_enc'         => 'encrypt',
			'swpm_smtpcom_channel'             => 'text',
			// Gmail.
			'swpm_gmail_username'              => 'email',
			'swpm_gmail_app_password_enc'      => 'encrypt',
			// Outlook.
			'swpm_outlook_username'            => 'email',
			'swpm_outlook_password_enc'        => 'encrypt',
			// Elastic Email.
			'swpm_elasticemail_api_key_enc'    => 'encrypt',
			// Mailjet.
			'swpm_mailjet_api_key_enc'         => 'encrypt',
			'swpm_mailjet_secret_key_enc'      => 'encrypt',
			// MailerSend.
			'swpm_mailersend_api_token_enc'    => 'encrypt',
			// SMTP2GO.
			'swpm_smtp2go_api_key_enc'         => 'encrypt',
			// SparkPost.
			'swpm_sparkpost_api_key_enc'       => 'encrypt',
			'swpm_sparkpost_region'            => 'text',
			// Zoho.
			'swpm_zoho_username'               => 'email',
			'swpm_zoho_password_enc'           => 'encrypt',
			'swpm_zoho_region'                 => 'text',
		);

		foreach ( $fields as $option_name => $type ) {
			if ( ! isset( $data[ $option_name ] ) ) {
				continue;
			}

			$value = wp_unslash( $data[ $option_name ] );

			switch ( $type ) {
				case 'text':
					$value = sanitize_text_field( $value );
					break;
				case 'email':
					$value = sanitize_email( $value );
					break;
				case 'int':
					$value = absint( $value );
					break;
				case 'encrypt':
					$value = sanitize_text_field( $value );
					if ( '' === $value ) {
						continue 2; // Keep existing value.
					}
					$value = swpm_encrypt( $value );
					break;
			}

			update_option( $option_name, $value );
		}
	}
}
