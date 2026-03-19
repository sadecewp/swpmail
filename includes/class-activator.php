<?php
/**
 * Plugin activator.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Activator {

	/**
	 * Run on activation.
	 */
	public static function activate(): void {
		self::create_tables();
		self::set_default_options();
		set_transient( 'swpm_activation_redirect', 1, 30 );
	}

	/**
	 * Create database tables.
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix;

		$sql = "CREATE TABLE {$prefix}swpm_subscribers (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email         VARCHAR(200) NOT NULL,
			name          VARCHAR(100) DEFAULT NULL,
			status        ENUM('pending','confirmed','unsubscribed','bounced') NOT NULL DEFAULT 'pending',
			frequency     ENUM('instant','daily','weekly') NOT NULL DEFAULT 'instant',
			token         VARCHAR(64) NOT NULL,
			ip_address    VARCHAR(45) DEFAULT NULL,
			confirmed_at  DATETIME DEFAULT NULL,
			created_at    DATETIME NOT NULL,
			updated_at    DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_email (email),
			KEY idx_status (status),
			KEY idx_frequency (frequency)
		) ENGINE=InnoDB {$charset};

		CREATE TABLE {$prefix}swpm_queue (
			id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			subscriber_id   BIGINT(20) UNSIGNED DEFAULT NULL,
			template_id     VARCHAR(100) DEFAULT NULL,
			to_email        VARCHAR(200) NOT NULL,
			subject         VARCHAR(500) NOT NULL,
			body            LONGTEXT NOT NULL,
			headers         TEXT DEFAULT NULL,
			attachments     TEXT DEFAULT NULL,
			status          ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
			attempts        TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
			max_attempts    TINYINT(3) UNSIGNED NOT NULL DEFAULT 3,
			provider_used   VARCHAR(50) DEFAULT NULL,
			provider_msg_id VARCHAR(200) DEFAULT NULL,
			scheduled_at    DATETIME NOT NULL,
			sent_at         DATETIME DEFAULT NULL,
			error_message   TEXT DEFAULT NULL,
			error_code      VARCHAR(50) DEFAULT NULL,
			created_at      DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_status_scheduled (status, scheduled_at),
			KEY idx_subscriber (subscriber_id),
			KEY idx_to_email (to_email(50))
		) ENGINE=InnoDB {$charset};

		CREATE TABLE {$prefix}swpm_logs (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			queue_id     BIGINT(20) UNSIGNED DEFAULT NULL,
			trigger_key  VARCHAR(100) DEFAULT NULL,
			provider     VARCHAR(50) DEFAULT NULL,
			level        ENUM('debug','info','warning','error') NOT NULL DEFAULT 'info',
			message      TEXT NOT NULL,
			context      LONGTEXT DEFAULT NULL,
			created_at   DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_level (level),
			KEY idx_created (created_at),
			KEY idx_queue (queue_id)
		) ENGINE=InnoDB {$charset};

		CREATE TABLE {$prefix}swpm_tracking (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			hash         VARCHAR(64) NOT NULL,
			queue_id     BIGINT(20) UNSIGNED DEFAULT NULL,
			to_email     VARCHAR(200) NOT NULL,
			subject      VARCHAR(500) DEFAULT NULL,
			event_type   ENUM('open','click') NOT NULL,
			url          TEXT DEFAULT NULL,
			ip_address   VARCHAR(45) DEFAULT NULL,
			user_agent   VARCHAR(500) DEFAULT NULL,
			created_at   DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_hash (hash),
			KEY idx_queue (queue_id),
			KEY idx_event (event_type),
			KEY idx_created (created_at),
			KEY idx_email_event (to_email(50), event_type)
		) ENGINE=InnoDB {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'swpm_db_version', SWPM_VERSION );
	}

	/**
	 * Set default options.
	 */
	private static function set_default_options(): void {
		add_option( 'swpm_mail_provider', 'smtp' );
		add_option( 'swpm_override_wp_mail', true );
		add_option( 'swpm_from_name', get_bloginfo( 'name' ) );
		add_option( 'swpm_from_email', get_option( 'admin_email' ) );
		add_option( 'swpm_double_opt_in', true );
		add_option( 'swpm_gdpr_checkbox', true );
		add_option( 'swpm_show_frequency_choice', true );
		add_option( 'swpm_active_triggers', array( 'new_post' ) );
		add_option( 'swpm_daily_send_hour', 9, '', false );
		add_option( 'swpm_weekly_send_day', 'monday', '', false );
		add_option( 'swpm_notify_admin_on_failure', true, '', false );
		add_option( 'swpm_form_title', '', '', false );
		add_option( 'swpm_queue_last_run', time(), '', false );
		add_option( 'swpm_enable_open_tracking', true, '', false );
		add_option( 'swpm_enable_click_tracking', true, '', false );
	}
}
