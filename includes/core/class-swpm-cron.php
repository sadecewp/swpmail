<?php
/**
 * Cron & digest scheduler.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages scheduled tasks and WP-Cron hooks.
 */
class SWPM_Cron {

	/**

	 * Variable.
	 *
	 * @var SWPM_Queue
	 */
	private SWPM_Queue $queue;

	/**

	 * Variable.
	 *
	 * @var SWPM_Subscriber
	 */
	private SWPM_Subscriber $subscriber;

	/**
	 * Constructor.
	 *
	 * @param SWPM_Queue $queue Queue.
	 * @param SWPM_Subscriber $subscriber Subscriber.
	 */
	// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
	/**
	 * Constructor.
	 *
	 * @param SWPM_Queue      $queue Queue.
	 * @param SWPM_Subscriber $subscriber Subscriber.
	 */
	public function __construct( SWPM_Queue $queue, SWPM_Subscriber $subscriber ) {
		$this->queue      = $queue;
		$this->subscriber = $subscriber;
	}

	/**
	 * Register cron schedules and events.
	 */
	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );

		if ( ! wp_next_scheduled( 'swpm_process_queue' ) ) {
			wp_schedule_event( time(), 'every_5_minutes', 'swpm_process_queue' );
		}

		if ( ! wp_next_scheduled( 'swpm_send_daily_digest' ) ) {
			$this->schedule_daily_digest();
		}

		if ( ! wp_next_scheduled( 'swpm_send_weekly_digest' ) ) {
			$this->schedule_weekly_digest();
		}

		add_action( 'swpm_process_queue', array( $this, 'process_queue' ) );
		add_action( 'swpm_send_daily_digest', array( $this, 'send_daily_digest' ) );
		add_action( 'swpm_send_weekly_digest', array( $this, 'send_weekly_digest' ) );

		// Log cleanup.
		if ( ! wp_next_scheduled( 'swpm_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'swpm_cleanup_logs' );
		}
		add_action( 'swpm_cleanup_logs', array( $this, 'cleanup_old_logs' ) );

		// Queue retention cleanup.
		if ( ! wp_next_scheduled( 'swpm_cleanup_queue' ) ) {
			wp_schedule_event( time(), 'daily', 'swpm_cleanup_queue' );
		}
		add_action( 'swpm_cleanup_queue', array( $this, 'cleanup_old_queue' ) );

		// Tracking data cleanup.
		if ( ! wp_next_scheduled( 'swpm_cleanup_tracking' ) ) {
			wp_schedule_event( time(), 'daily', 'swpm_cleanup_tracking' );
		}
		add_action( 'swpm_cleanup_tracking', array( $this, 'cleanup_old_tracking' ) );

		// Reschedule when settings change.
		add_action( 'update_option_swpm_daily_send_hour', array( $this, 'reschedule_daily_digest' ) );
		add_action( 'update_option_swpm_weekly_send_day', array( $this, 'reschedule_weekly_digest' ) );
	}

	/**
	 * Schedule the daily digest cron event.
	 */
	private function schedule_daily_digest(): void {
		$hour = (int) get_option( 'swpm_daily_send_hour', 9 );
		$ts   = $this->wp_strtotime( "today {$hour}:00:00" );
		wp_schedule_event( $ts < time() ? $ts + DAY_IN_SECONDS : $ts, 'daily', 'swpm_send_daily_digest' );
	}

	/**
	 * Schedule the weekly digest cron event.
	 */
	private function schedule_weekly_digest(): void {
		$day  = sanitize_key( get_option( 'swpm_weekly_send_day', 'monday' ) );
		$hour = (int) get_option( 'swpm_daily_send_hour', 9 );
		wp_schedule_event( $this->wp_strtotime( "next {$day} {$hour}:00:00" ), 'weekly', 'swpm_send_weekly_digest' );
	}

	/**
	 * Parse a date/time string using the WordPress-configured timezone.
	 *
	 * @param string $datetime Date/time expression.
	 * @return int Unix timestamp (UTC).
	 */
	private function wp_strtotime( string $datetime ): int {
		$tz = wp_timezone();
		$dt = date_create( $datetime, $tz );
		return $dt ? (int) $dt->getTimestamp() : time();
	}

	/**
	 * Reschedule daily digest when the send hour changes.
	 */
	public function reschedule_daily_digest(): void {
		wp_clear_scheduled_hook( 'swpm_send_daily_digest' );
		$this->schedule_daily_digest();
	}

	/**
	 * Reschedule weekly digest when the send day changes.
	 */
	public function reschedule_weekly_digest(): void {
		wp_clear_scheduled_hook( 'swpm_send_weekly_digest' );
		$this->schedule_weekly_digest();
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_schedules( array $schedules ): array {
		$schedules['every_5_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 Minutes', 'swpmail' ),
		);
		return $schedules;
	}

	/**
	 * Process the mail queue.
	 */
	public function process_queue(): void {
		update_option( 'swpm_queue_last_run', time() );
		$this->queue->process( (int) apply_filters( 'swpm_queue_batch_size', 50 ) );
	}

	/**
	 * Send daily digest.
	 */
	public function send_daily_digest(): void {
		$this->dispatch_digest( 'daily', 1 );
	}

	/**
	 * Send weekly digest.
	 */
	public function send_weekly_digest(): void {
		$this->dispatch_digest( 'weekly', 7 );
	}

	/**
	 * Dispatch a digest email.
	 *
	 * @param string $type Type: daily or weekly.
	 * @param int    $days Number of days to look back.
	 */
	private function dispatch_digest( string $type, int $days ): void {
		$posts = get_posts(
			array(
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'date_query'     => array( array( 'after' => "{$days} days ago" ) ),
			)
		);

		if ( empty( $posts ) ) {
			swpm_log( 'info', "No posts for {$type} digest. Skipping." );
			return;
		}

		$post_list = '<ul>';
		foreach ( $posts as $post ) {
			$post_list .= sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( get_permalink( $post ) ),
				esc_html( get_the_title( $post ) )
			);
		}
		$post_list .= '</ul>';

		$recipients = $this->subscriber->get_confirmed_by_frequency( $type );

		if ( empty( $recipients ) ) {
			swpm_log( 'info', "No {$type} recipients. Skipping digest." );
			return;
		}

		if ( 'daily' === $type ) {
			$subject = sprintf(
				/* translators: %s: date */
				__( 'Daily Digest — %s', 'swpmail' ),
				wp_date( 'F j, Y' )
			);
		} else {
			$subject = sprintf(
				/* translators: %s: date */
				__( 'Weekly Digest — Week of %s', 'swpmail' ),
				wp_date( 'F j' )
			);
		}

		do_action( 'swpm_digest_before_send', $type, $posts, $recipients );

		$this->queue->enqueue_bulk(
			$recipients,
			"digest-{$type}",
			$subject,
			array(
				'post_list'  => $post_list,
				'date'       => wp_date( 'F j, Y' ),
				'week_start' => wp_date( 'F j', strtotime( "-{$days} days" ) ),
				'week_end'   => wp_date( 'F j' ),
			)
		);
	}

	/**
	 * Clean up old log entries.
	 */
	public function cleanup_old_logs(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$retention_days = (int) apply_filters( 'swpm_log_retention_days', 30 );
		$table          = $wpdb->prefix . 'swpm_logs';
		$cutoff         = gmdate( 'Y-m-d H:i:s', time() - $retention_days * DAY_IN_SECONDS );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Paginated delete to avoid table locks on large datasets.
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted = $wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"DELETE FROM {$table} WHERE created_at < %s LIMIT 1000",
					$cutoff
				)
			);
		} while ( $deleted > 0 );
	}

	/**
	 * Clean up old completed/failed queue entries.
	 */
	public function cleanup_old_queue(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$retention_days = (int) apply_filters( 'swpm_queue_retention_days', 30 );
		$table          = $wpdb->prefix . 'swpm_queue';
		$cutoff         = gmdate( 'Y-m-d H:i:s', time() - $retention_days * DAY_IN_SECONDS );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Paginated delete to avoid table locks on large datasets.
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$deleted = $wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"DELETE FROM {$table} WHERE status IN ('sent', 'failed') AND created_at < %s LIMIT 1000",
					$cutoff
				)
			);
		} while ( $deleted > 0 );
	}

	/**
	 * Clean up old tracking data.
	 */
	public function cleanup_old_tracking(): void {
		$analytics = swpm( 'analytics' );
		if ( $analytics instanceof SWPM_Analytics ) {
			$retention = (int) apply_filters( 'swpm_analytics_retention_days', 90 );
			$analytics->cleanup_old_tracking( $retention );
		}
	}
}
