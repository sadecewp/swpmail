<?php
/**
 * Dashboard data provider — encapsulates all dashboard queries.
 *
 * Extracted from display-dashboard.php to enforce MVC separation.
 *
 * @package SWPMail
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Dashboard_Data {

	/** @var \wpdb */
	private $db;

	public function __construct() {
		global $wpdb;
		$this->db = $wpdb;
	}

	/**
	 * Get subscriber stats grouped by status.
	 *
	 * @return array{pending: int, confirmed: int, unsubscribed: int, bounced: int, total: int}
	 */
	public function get_subscriber_stats(): array {
		$table = $this->db->prefix . 'swpm_subscribers';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db->get_results( "SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status" );

		$stats = array( 'pending' => 0, 'confirmed' => 0, 'unsubscribed' => 0, 'bounced' => 0 );
		foreach ( $rows as $row ) {
			if ( isset( $stats[ $row->status ] ) ) {
				$stats[ $row->status ] = (int) $row->cnt;
			}
		}
		$stats['total'] = array_sum( $stats );

		return $stats;
	}

	/**
	 * Get queue stats grouped by status.
	 *
	 * @return array{pending: int, sending: int, sent: int, failed: int}
	 */
	public function get_queue_stats(): array {
		$table = $this->db->prefix . 'swpm_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db->get_results( "SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status" );

		$stats = array( 'pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0 );
		foreach ( $rows as $row ) {
			if ( isset( $stats[ $row->status ] ) ) {
				$stats[ $row->status ] = (int) $row->cnt;
			}
		}

		return $stats;
	}

	/**
	 * Get the provider label for the active mail provider.
	 *
	 * @return array{key: string, label: string}
	 */
	public function get_provider_info(): array {
		$key    = get_option( 'swpm_mail_provider', 'smtp' );
		$labels = array(
			'phpmail'      => 'PHP Mail',
			'smtp'         => 'Generic SMTP',
			'sendlayer'    => 'SendLayer',
			'smtpcom'      => 'SMTP.com',
			'gmail'        => 'Gmail',
			'outlook'      => 'Outlook / 365',
			'mailgun'      => 'Mailgun',
			'sendgrid'     => 'SendGrid',
			'postmark'     => 'Postmark',
			'brevo'        => 'Brevo',
			'ses'          => 'Amazon SES',
			'resend'       => 'Resend',
			'elasticemail' => 'Elastic Email',
			'mailjet'      => 'Mailjet',
			'mailersend'   => 'MailerSend',
			'smtp2go'      => 'SMTP2GO',
			'sparkpost'    => 'SparkPost',
			'zoho'         => 'Zoho Mail',
		);

		return array(
			'key'   => $key,
			'label' => $labels[ $key ] ?? $key,
		);
	}

	/**
	 * Get formatted "last run" text for queue cron.
	 *
	 * @return string
	 */
	public function get_last_run_text(): string {
		$last_run = get_option( 'swpm_queue_last_run', false );
		if ( false !== $last_run && (int) $last_run > 0 ) {
			return human_time_diff( (int) $last_run, time() ) . ' ' . __( 'ago', 'swpmail' );
		}
		return __( 'Never', 'swpmail' );
	}

	/**
	 * Get recent log entries.
	 *
	 * @param int $limit Number of entries.
	 * @return array
	 */
	public function get_recent_logs( int $limit = 10 ): array {
		$table = $this->db->prefix . 'swpm_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get tracking analytics summary.
	 *
	 * @return array{stats: array, trend: array, top_links: array}
	 */
	public function get_tracking_data(): array {
		$analytics = swpm( 'analytics' );

		if ( ! $analytics instanceof SWPM_Analytics ) {
			return array(
				'stats'     => array(),
				'trend'     => array(),
				'top_links' => array(),
			);
		}

		return array(
			'stats'     => $analytics->get_summary( 30 ),
			'trend'     => $analytics->get_daily_trend( 14 ),
			'top_links' => $analytics->get_top_links( 5, 30 ),
		);
	}

	/**
	 * Get failover / connection status.
	 *
	 * @return array{backup_key: string, status: array|null}
	 */
	public function get_failover_info(): array {
		$connections = swpm( 'connections' );

		return array(
			'backup_key' => get_option( 'swpm_backup_provider', '' ),
			'status'     => ( $connections instanceof SWPM_Connections_Manager )
				? $connections->get_status_summary()
				: null,
		);
	}

	/**
	 * Get active triggers and routing summary.
	 *
	 * @return array{trigger_count: int, routing_enabled: bool, routing_count: int}
	 */
	public function get_automation_summary(): array {
		$active_triggers = (array) get_option( 'swpm_active_triggers', array() );
		$router          = swpm( 'router' );
		$routing_rules   = ( $router instanceof SWPM_Router ) ? $router->get_rules() : array();

		return array(
			'trigger_count'   => count( $active_triggers ),
			'routing_enabled' => (bool) get_option( 'swpm_enable_smart_routing', false ),
			'routing_count'   => count( $routing_rules ),
		);
	}

	/**
	 * Get all dashboard data in a single call.
	 *
	 * @return array
	 */
	public function get_all(): array {
		$sub_stats      = $this->get_subscriber_stats();
		$queue_stats    = $this->get_queue_stats();
		$provider       = $this->get_provider_info();
		$tracking       = $this->get_tracking_data();
		$failover       = $this->get_failover_info();
		$automation     = $this->get_automation_summary();

		return array(
			'subscribers'    => $sub_stats,
			'queue'          => $queue_stats,
			'provider'       => $provider,
			'last_run_text'  => $this->get_last_run_text(),
			'recent_logs'    => $this->get_recent_logs(),
			'tracking'       => $tracking,
			'failover'       => $failover,
			'automation'     => $automation,
		);
	}
}
