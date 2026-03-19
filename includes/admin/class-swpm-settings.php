<?php
/**
 * Settings API registration.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SWPM_Settings.
 */
class SWPM_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_swpm_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_swpm_export_subscribers', array( $this, 'ajax_export_subscribers' ) );
		add_action( 'wp_ajax_swpm_purge_logs', array( $this, 'ajax_purge_logs' ) );
	}

	/**
	 * Register all settings groups.
	 */
	public function register_settings(): void {
		$this->register_mail_settings();
		$this->register_failover_settings();
		$this->register_general_settings();
	}

	/**
	 * Register mail provider settings.
	 */
	private function register_mail_settings(): void {
		register_setting(
			'swpm_mail_settings_group',
			'swpm_mail_provider',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_provider' ),
				'default'           => 'phpmail',
			)
		);

		register_setting(
			'swpm_mail_settings_group',
			'swpm_from_name',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => get_bloginfo( 'name' ),
			)
		);

		register_setting(
			'swpm_mail_settings_group',
			'swpm_from_email',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'default'           => get_option( 'admin_email' ),
			)
		);

		// SMTP settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_smtp_host',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_smtp_port',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 587,
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_smtp_encryption',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encryption' ),
				'default'           => 'tls',
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_smtp_username',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_smtp_password_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);

		// Mailgun settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_mailgun_api_key_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_mailgun_domain',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_mailgun_region',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_mailgun_region' ),
				'default'           => 'us',
			)
		);

		// SendGrid settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_sendgrid_api_key_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);

		// Postmark settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_postmark_server_token_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_postmark_message_stream',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'outbound',
			)
		);

		// Brevo settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_brevo_api_key_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);

		// Amazon SES settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_ses_access_key_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_ses_secret_key_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_ses_region',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'us-east-1',
			)
		);

		// Resend settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_resend_api_key_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);

		// SendLayer settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_sendlayer_api_key_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);

		// SMTP.com settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_smtpcom_api_key_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_smtpcom_channel',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Gmail settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_gmail_username',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_gmail_app_password_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_gmail_oauth_client_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_gmail_oauth_client_secret_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);

		// Outlook / 365 settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_outlook_username',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_outlook_password_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_outlook_oauth_client_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_outlook_oauth_client_secret_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);

		// Elastic Email settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_elasticemail_api_key_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);

		// Mailjet settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_mailjet_api_key_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_mailjet_secret_key_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);

		// MailerSend settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_mailersend_api_token_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);

		// SMTP2GO settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_smtp2go_api_key_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);

		// SparkPost settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_sparkpost_api_key_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_sparkpost_region',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_sparkpost_region' ),
				'default'           => 'us',
			)
		);

		// Zoho Mail settings.
		register_setting(
			'swpm_mail_settings_group',
			'swpm_zoho_username',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_zoho_password_enc',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted_field' ),
			)
		);
		register_setting(
			'swpm_mail_settings_group',
			'swpm_zoho_region',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_zoho_region' ),
				'default'           => 'com',
			)
		);
	}

	/**
	 * Register failover settings.
	 */
	private function register_failover_settings(): void {
		register_setting(
			'swpm_failover_settings_group',
			'swpm_backup_provider',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_backup_provider' ),
				'default'           => '',
			)
		);
	}

	/**
	 * Register general settings.
	 */
	private function register_general_settings(): void {
		register_setting(
			'swpm_general_settings_group',
			'swpm_double_opt_in',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		register_setting(
			'swpm_general_settings_group',
			'swpm_gdpr_checkbox',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		register_setting(
			'swpm_general_settings_group',
			'swpm_gdpr_privacy_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);
		register_setting(
			'swpm_general_settings_group',
			'swpm_show_frequency_choice',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		register_setting(
			'swpm_general_settings_group',
			'swpm_form_title',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => __( 'Subscribe to Newsletter', 'swpmail' ),
			)
		);
		register_setting(
			'swpm_general_settings_group',
			'swpm_notify_admin_on_failure',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		register_setting(
			'swpm_general_settings_group',
			'swpm_daily_send_hour',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 9,
			)
		);
		register_setting(
			'swpm_general_settings_group',
			'swpm_weekly_send_day',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_weekday' ),
				'default'           => 'monday',
			)
		);
		register_setting(
			'swpm_general_settings_group',
			'swpm_override_wp_mail',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		register_setting(
			'swpm_general_settings_group',
			'swpm_active_triggers',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_active_triggers' ),
				'default'           => array(),
			)
		);

		// Email Tracking settings.
		register_setting(
			'swpm_general_settings_group',
			'swpm_enable_open_tracking',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		register_setting(
			'swpm_general_settings_group',
			'swpm_enable_click_tracking',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		// Data deletion on uninstall.
		register_setting(
			'swpm_general_settings_group',
			'swpm_delete_data_on_uninstall',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	// ------------------------------------------------------------------
	// Sanitize Callbacks
	// ----------------------------------------------------------------

	/**
	 * Sanitize provider selection.
	 *
	 * @param mixed $value Value.
	 */
	public function sanitize_provider( $value ): string {
		$allowed = array( 'phpmail', 'smtp', 'sendlayer', 'smtpcom', 'gmail', 'outlook', 'mailgun', 'sendgrid', 'postmark', 'brevo', 'ses', 'resend', 'elasticemail', 'mailjet', 'mailersend', 'smtp2go', 'sparkpost', 'zoho' );
		return in_array( $value, $allowed, true ) ? $value : 'phpmail';
	}

	/**
	 * Sanitize backup provider selection.
	 *
	 * @param mixed $value Value.
	 */
	public function sanitize_backup_provider( $value ): string {
		if ( empty( $value ) || 'none' === $value ) {
			return '';
		}
		$allowed = array( 'phpmail', 'smtp', 'sendlayer', 'smtpcom', 'gmail', 'outlook', 'mailgun', 'sendgrid', 'postmark', 'brevo', 'ses', 'resend', 'elasticemail', 'mailjet', 'mailersend', 'smtp2go', 'sparkpost', 'zoho' );
		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Sanitize SMTP encryption.
	 *
	 * @param mixed $value Value.
	 */
	public function sanitize_encryption( $value ): string {
		return in_array( $value, array( 'tls', 'ssl', '' ), true ) ? $value : 'tls';
	}

	/**
	 * Sanitize Mailgun region.
	 *
	 * @param mixed $value Value.
	 */
	public function sanitize_mailgun_region( $value ): string {
		return in_array( $value, array( 'us', 'eu' ), true ) ? $value : 'us';
	}

	/**
	 * Sanitize SparkPost region.
	 *
	 * @param mixed $value Value.
	 */
	public function sanitize_sparkpost_region( $value ): string {
		return in_array( $value, array( 'us', 'eu' ), true ) ? $value : 'us';
	}

	/**
	 * Sanitize Zoho region.
	 *
	 * @param mixed $value Value.
	 */
	public function sanitize_zoho_region( $value ): string {
		return in_array( $value, array( 'com', 'eu', 'in', 'com.au', 'jp' ), true ) ? $value : 'com';
	}

	/**
	 * Sanitize weekday selection.
	 *
	 * @param mixed $value Value.
	 */
	public function sanitize_weekday( $value ): string {
		$days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		return in_array( $value, $days, true ) ? $value : 'monday';
	}

	/**
	 * Sanitize active triggers array.
	 *
	 * @param mixed $value Value.
	 */
	public function sanitize_active_triggers( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_map( 'sanitize_key', $value );
	}

	/**
	 * Encrypt sensitive field before saving.
	 * Empty value means "keep current" (don't overwrite).
	 *
	 * @param mixed $value Value.
	 */
	public function sanitize_encrypted_field( $value ): string {
		$value = sanitize_text_field( $value );

		if ( '' === $value ) {
			// Keep the existing encrypted value.
			$option_name = current_filter();
			// Extract option name from the filter: sanitize_option_{option_name}.
			$option_name = str_replace( 'sanitize_option_', '', $option_name );
			return get_option( $option_name, '' );
		}

		return swpm_encrypt( $value );
	}

	// ------------------------------------------------------------------
	// AJAX: Test Connection
	// ----------------------------------------------------------------

	/**
	 * Send a test email via the configured provider.
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		$provider = swpm( 'provider' );

		if ( ! $provider ) {
			wp_send_json_error( array( 'message' => __( 'No provider configured.', 'swpmail' ) ) );
		}

		// Use custom recipient if provided, otherwise fallback to admin email.
		$recipient = isset( $_POST['recipient'] ) ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : '';
		if ( empty( $recipient ) || ! is_email( $recipient ) ) {
			$recipient = get_option( 'admin_email' );
		}

		$subject = __( '[SWPMail] Test Connection', 'swpmail' );
		$body    = sprintf(
			/* translators: %s: site name */
			__( 'This is a test email from SWPMail on %s. Your mail provider is working correctly!', 'swpmail' ),
			get_bloginfo( 'name' )
		);

		$result = $provider->send( $recipient, $subject, $body );

		if ( $result->is_success() ) {
			wp_send_json_success(
				array(
					'message'    => __( 'Connection successful! Test email sent.', 'swpmail' ),
					'message_id' => $result->get_message_id(),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message'    => $result->get_error_message(),
					'error_code' => $result->get_error_code(),
				)
			);
		}
	}

	// ------------------------------------------------------------------
	// AJAX: Export Subscribers CSV
	// ----------------------------------------------------------------

	/**
	 * Stream a CSV download of all subscribers.
	 */
	public function ajax_export_subscribers(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'swpmail' ), 403 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'swpm_subscribers';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT email, status, frequency, created_at FROM {$table} ORDER BY created_at DESC", ARRAY_A );

		$filename = 'swpmail-subscribers-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Email', 'Status', 'Frequency', 'Subscribed Date' ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( $rows ) {
			foreach ( $rows as $row ) {
				fputcsv( $output, $row );
			}
		}

		fclose( $output );
		exit;
	}

	// ------------------------------------------------------------------
	// AJAX: Purge Old Logs
	// ----------------------------------------------------------------

	/**
	 * Delete queue & tracking records older than N days.
	 */
	public function ajax_purge_logs(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'swpmail' ), 403 );
		}

		$days = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 90;
		if ( $days < 1 ) {
			$days = 90;
		}

		global $wpdb;
		$queue_table    = $wpdb->prefix . 'swpm_queue';
		$tracking_table = $wpdb->prefix . 'swpm_tracking';
		$cutoff         = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted_queue = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$queue_table} WHERE created_at < %s", $cutoff ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted_tracking = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$tracking_table} WHERE created_at < %s", $cutoff ) );

		wp_send_json_success(
			array(
				'message' => sprintf(
				/* translators: 1: queue count, 2: tracking count */
					__( 'Purged %1$d queue records and %2$d tracking records.', 'swpmail' ),
					$deleted_queue,
					$deleted_tracking
				),
			)
		);
	}
}
