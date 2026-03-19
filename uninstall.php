<?php
/**
 * Uninstall.php
 *
 * @package SWPMail
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only delete data if the user explicitly opted in.
if ( ! get_option( 'swpm_delete_data_on_uninstall', false ) ) {
	return;
}

/**
 * Clean up data for a single site.
 */
function swpm_uninstall_site(): void {
	global $wpdb;

	// Drop tables.
	foreach ( array( 'swpm_subscribers', 'swpm_queue', 'swpm_logs', 'swpm_tracking' ) as $table ) {
		$full = $wpdb->prefix . $table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $full ) );
	}

	// Delete options.
	$options = array(
		'swpm_db_version',
		'swpm_from_name',
		'swpm_from_email',
		'swpm_mail_provider',
		'swpm_override_wp_mail',
		'swpm_smtp_host',
		'swpm_smtp_port',
		'swpm_smtp_username',
		'swpm_smtp_password_enc',
		'swpm_smtp_encryption',
		'swpm_mailgun_api_key_enc',
		'swpm_mailgun_domain',
		'swpm_mailgun_region',
		'swpm_sendgrid_api_key_enc',
		'swpm_postmark_server_token_enc',
		'swpm_postmark_message_stream',
		'swpm_brevo_api_key_enc',
		'swpm_ses_access_key_enc',
		'swpm_ses_secret_key_enc',
		'swpm_ses_region',
		'swpm_resend_api_key_enc',
		'swpm_show_frequency_choice',
		'swpm_double_opt_in',
		'swpm_gdpr_checkbox',
		'swpm_active_triggers',
		'swpm_daily_send_hour',
		'swpm_weekly_send_day',
		'swpm_notify_admin_on_failure',
		'swpm_delete_data_on_uninstall',
		'swpm_queue_last_run',
		'swpm_form_title',
		'swpm_gdpr_privacy_page',
		'swpm_custom_templates',
		'swpm_custom_triggers',
		'swpm_setup_complete',
		'swpm_sendlayer_api_key_enc',
		'swpm_smtpcom_api_key_enc',
		'swpm_smtpcom_channel',
		'swpm_gmail_username',
		'swpm_gmail_app_password_enc',
		'swpm_gmail_oauth_client_id',
		'swpm_gmail_oauth_client_secret_enc',
		'swpm_gmail_oauth_tokens_enc',
		'swpm_outlook_username',
		'swpm_outlook_password_enc',
		'swpm_outlook_oauth_client_id',
		'swpm_outlook_oauth_client_secret_enc',
		'swpm_outlook_oauth_tokens_enc',
		'swpm_elasticemail_api_key_enc',
		'swpm_mailjet_api_key_enc',
		'swpm_mailjet_secret_key_enc',
		'swpm_mailersend_api_token_enc',
		'swpm_smtp2go_api_key_enc',
		'swpm_sparkpost_api_key_enc',
		'swpm_sparkpost_region',
		'swpm_zoho_username',
		'swpm_zoho_password_enc',
		'swpm_zoho_region',
		'swpm_backup_provider',
		'swpm_connection_health_primary',
		'swpm_connection_health_backup',
		'swpm_enable_open_tracking',
		'swpm_enable_click_tracking',
		'swpm_tracking_rules_flushed',
		'swpm_routing_rules',
		'swpm_enable_smart_routing',
		'swpm_alarm_enabled_channels',
		'swpm_alarm_events',
		'swpm_alarm_cooldown',
		'swpm_alarm_slack_webhook_enc',
		'swpm_alarm_discord_webhook_enc',
		'swpm_alarm_teams_webhook_enc',
		'swpm_alarm_twilio_sid_enc',
		'swpm_alarm_twilio_token_enc',
		'swpm_alarm_twilio_from',
		'swpm_alarm_twilio_to',
		'swpm_alarm_custom_webhook_enc',
		'swpm_alarm_custom_secret_enc',
	);
	foreach ( $options as $opt ) {
		delete_option( $opt );
	}

	// Delete template options.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			'swpm\_template\_%'
		)
	);

	// Delete tracking hash transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_swpm\_th\_%',
			'_transient_timeout_swpm\_th\_%'
		)
	);

	// Delete alarm throttle transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_swpm\_alarm\_t\_%',
			'_transient_timeout_swpm\_alarm\_t\_%'
		)
	);

	// Clear scheduled cron hooks.
	$hooks = array( 'swpm_process_queue', 'swpm_send_daily_digest', 'swpm_send_weekly_digest', 'swpm_cleanup_logs', 'swpm_cleanup_queue', 'swpm_cleanup_tracking' );
	foreach ( $hooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
}

// Multisite support: clean each site individually.
if ( is_multisite() ) {
	$sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		swpm_uninstall_site();
		restore_current_blog();
	}
} else {
	swpm_uninstall_site();
}
