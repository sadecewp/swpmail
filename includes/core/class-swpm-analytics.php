<?php
/**
 * Tracking Analytics — aggregate queries for the dashboard.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects and reports email sending analytics.
 */
class SWPM_Analytics {

	/**

	 * Variable.
	 *
	 * @var \wpdb
	 */
	private $db;

	/**

	 * Variable.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'swpm_tracking';
	}

	// ------------------------------------------------------------------
	// Summary Stats
	// ----------------------------------------------------------------

	/**
	 * Return totals for the dashboard stats grid.
	 *
	 * @param int $days Number of days to look back (0 = all time).
	 * @return array{total_opens: int, unique_opens: int, total_clicks: int, unique_clicks: int, open_rate: float, click_rate: float}
	 */
	public function get_summary( int $days = 30 ): array {
		$where = '';
		if ( $days > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$where = $this->db->prepare( ' AND created_at >= %s', gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$opens = $this->db->get_row(
			"SELECT COUNT(*) AS total, COUNT(DISTINCT CONCAT(hash)) AS uniq
			 FROM {$this->table} WHERE event_type = 'open'{$where}",
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clicks = $this->db->get_row(
			"SELECT COUNT(*) AS total, COUNT(DISTINCT hash) AS uniq
			 FROM {$this->table} WHERE event_type = 'click'{$where}",
			ARRAY_A
		);

		// Emails sent count (from logs) for rate calculation.
		$logs_table = $this->db->prefix . 'swpm_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sent = (int) $this->db->get_var(
			"SELECT COUNT(*) FROM {$logs_table} WHERE status = 'sent'{$where}"
		);

		$total_opens  = (int) ( $opens['total'] ?? 0 );
		$unique_opens = (int) ( $opens['uniq'] ?? 0 );
		$total_clicks = (int) ( $clicks['total'] ?? 0 );

		return array(
			'total_opens'   => $total_opens,
			'unique_opens'  => $unique_opens,
			'total_clicks'  => $total_clicks,
			'unique_clicks' => (int) ( $clicks['uniq'] ?? 0 ),
			'open_rate'     => $sent > 0 ? round( ( $unique_opens / $sent ) * 100, 1 ) : 0,
			'click_rate'    => $sent > 0 ? round( ( (int) ( $clicks['uniq'] ?? 0 ) / $sent ) * 100, 1 ) : 0,
			'total_sent'    => $sent,
		);
	}

	// ------------------------------------------------------------------
	// Daily Trend (for charts)
	// ----------------------------------------------------------------

	/**
	 * Return daily open/click counts for the last N days.
	 *
	 * @param int $days Number of days.
	 * @return array Array of { date, opens, clicks }.
	 */
	public function get_daily_trend( int $days = 30 ): array {
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT DATE(created_at) AS day,
				        SUM(event_type = 'open')  AS opens,
				        SUM(event_type = 'click') AS clicks
				 FROM {$this->table}
				 WHERE created_at >= %s
				 GROUP BY day ORDER BY day ASC",
				$since
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	// ------------------------------------------------------------------
	// Top Clicked Links
	// ----------------------------------------------------------------

	/**
	 * Return the most-clicked URLs.
	 *
	 * @param int $limit Number of results.
	 * @param int $days  Number of days.
	 * @return array Array of { url, clicks }.
	 */
	public function get_top_links( int $limit = 10, int $days = 30 ): array {
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT url, COUNT(*) AS clicks
				 FROM {$this->table}
				 WHERE event_type = 'click' AND url IS NOT NULL AND created_at >= %s
				 GROUP BY url ORDER BY clicks DESC LIMIT %d",
				$since,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	// ------------------------------------------------------------------
	// Cleanup
	// ----------------------------------------------------------------

	/**
	 * Delete tracking records older than the retention period.
	 *
	 * Runs in batches to avoid long-running queries on large tables.
	 *
	 * @param int $retention_days Days to retain data (default 90).
	 */
	public function cleanup_old_tracking( int $retention_days = 90 ): void {
		$retention_days = (int) apply_filters( 'swpm_tracking_retention_days', $retention_days );
		$cutoff         = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );
		$batch_size     = (int) apply_filters( 'swpm_cleanup_batch_size', 1000 );

		// Delete in batches to prevent locking the table for extended periods.
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$deleted = (int) $this->db->query(
				$this->db->prepare(
					"DELETE FROM {$this->table} WHERE created_at < %s LIMIT %d",
					$cutoff,
					$batch_size
				)
			);
		} while ( $deleted >= $batch_size );
	}
}
