<?php
/**
 * Database repair & integrity checker.
 *
 * Diagnoses and repairs common database issues:
 *  - Missing tables or columns.
 *  - Orphaned records (queue items referencing deleted subscribers).
 *  - Stuck queue items (sending status for too long).
 *  - Missing indexes.
 *  - Corrupted option values.
 *
 * @package SWPMail
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database table creation and repair.
 */
class SWPM_DB_Repair {

	/**
	 * Database instance.
	 *
	 * @var \wpdb
	 */
	private $db;

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	private string $prefix;

	/** Expected tables and their required columns. */
	private const SCHEMA = array(
		'swpm_subscribers' => array(
			'id',
			'email',
			'name',
			'status',
			'frequency',
			'token',
			'ip_address',
			'confirmed_at',
			'created_at',
			'updated_at',
		),
		'swpm_queue'       => array(
			'id',
			'subscriber_id',
			'template_id',
			'to_email',
			'subject',
			'body',
			'headers',
			'attachments',
			'status',
			'attempts',
			'max_attempts',
			'provider_used',
			'provider_msg_id',
			'scheduled_at',
			'sent_at',
			'error_message',
			'error_code',
			'created_at',
		),
		'swpm_logs'        => array(
			'id',
			'queue_id',
			'trigger_key',
			'provider',
			'level',
			'message',
			'context',
			'created_at',
		),
		'swpm_tracking'    => array(
			'id',
			'hash',
			'queue_id',
			'to_email',
			'subject',
			'event_type',
			'url',
			'ip_address',
			'user_agent',
			'created_at',
		),
	);

	/** Expected indexes per table. */
	private const EXPECTED_INDEXES = array(
		'swpm_subscribers' => array( 'uq_email', 'idx_status', 'idx_frequency' ),
		'swpm_queue'       => array( 'idx_status_scheduled', 'idx_subscriber', 'idx_to_email' ),
		'swpm_logs'        => array( 'idx_level', 'idx_created', 'idx_queue' ),
		'swpm_tracking'    => array( 'idx_hash', 'idx_queue', 'idx_event', 'idx_created', 'idx_email_event' ),
	);

	/** Required WP options with default types. */
	private const REQUIRED_OPTIONS = array(
		'swpm_mail_provider'    => 'string',
		'swpm_from_name'        => 'string',
		'swpm_from_email'       => 'string',
		'swpm_override_wp_mail' => 'boolean',
		'swpm_db_version'       => 'string',
	);

	/** Seconds before a "sending" item is considered stuck. */
	private const STUCK_THRESHOLD = 900; // 15 minutes.

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->db     = $wpdb;
		$this->prefix = $wpdb->prefix;
	}

	// ------------------------------------------------------------------
	// Diagnosis (read-only)
	// ----------------------------------------------------------------

	/**
	 * Run all diagnostic checks and return issues found.
	 *
	 * @return array{issues: array, summary: array} Structured report.
	 */
	public function diagnose(): array {
		$issues = array();

		$issues = array_merge( $issues, $this->check_tables() );
		$issues = array_merge( $issues, $this->check_columns() );
		$issues = array_merge( $issues, $this->check_indexes() );
		$issues = array_merge( $issues, $this->check_orphaned_queue_items() );
		$issues = array_merge( $issues, $this->check_stuck_queue_items() );
		$issues = array_merge( $issues, $this->check_options() );
		$issues = array_merge( $issues, $this->check_autoload_bloat() );

		$critical = count( array_filter( $issues, fn( $i ) => 'critical' === $i['severity'] ) );
		$warning  = count( array_filter( $issues, fn( $i ) => 'warning' === $i['severity'] ) );
		$info     = count( array_filter( $issues, fn( $i ) => 'info' === $i['severity'] ) );

		return array(
			'issues'  => $issues,
			'summary' => array(
				'total'    => count( $issues ),
				'critical' => $critical,
				'warning'  => $warning,
				'info'     => $info,
				'healthy'  => 0 === $critical && 0 === $warning,
			),
		);
	}

	/**
	 * Check that all required tables exist.
	 *
	 * @return array
	 */
	public function check_tables(): array {
		$issues = array();

		foreach ( array_keys( self::SCHEMA ) as $table ) {
			$full = $this->prefix . $table;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$exists = $this->db->get_var(
				$this->db->prepare( 'SHOW TABLES LIKE %s', $full )
			);

			if ( ! $exists ) {
				$issues[] = array(
					'code'     => 'missing_table',
					'severity' => 'critical',
					'message'  => sprintf( 'Table %s is missing.', $full ),
					'table'    => $table,
					'fixable'  => true,
				);
			}
		}

		return $issues;
	}

	/**
	 * Check that all expected columns exist in each table.
	 *
	 * @return array
	 */
	public function check_columns(): array {
		$issues = array();

		foreach ( self::SCHEMA as $table => $columns ) {
			$full = $this->prefix . $table;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$exists = $this->db->get_var(
				$this->db->prepare( 'SHOW TABLES LIKE %s', $full )
			);
			if ( ! $exists ) {
				continue; // Already flagged by check_tables.
			}

// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $this->db->get_results(
				$this->db->prepare( 'SHOW COLUMNS FROM %i', $full )
			);
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$found = array_map( fn( $r ) => $r->Field, $rows );

			foreach ( $columns as $col ) {
				if ( ! in_array( $col, $found, true ) ) {
					$issues[] = array(
						'code'     => 'missing_column',
						'severity' => 'critical',
						'message'  => sprintf( 'Column %s.%s is missing.', $full, $col ),
						'table'    => $table,
						'column'   => $col,
						'fixable'  => true,
					);
				}
			}
		}

		return $issues;
	}

	/**
	 * Check for missing indexes.
	 *
	 * @return array
	 */
	public function check_indexes(): array {
		$issues = array();

		foreach ( self::EXPECTED_INDEXES as $table => $indexes ) {
			$full = $this->prefix . $table;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$exists = $this->db->get_var(
				$this->db->prepare( 'SHOW TABLES LIKE %s', $full )
			);
			if ( ! $exists ) {
				continue;
			}

// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $this->db->get_results(
				$this->db->prepare( 'SHOW INDEX FROM %i', $full )
			);
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$found_keys = array_unique( array_map( fn( $r ) => $r->Key_name, $rows ) );

			foreach ( $indexes as $index ) {
				if ( ! in_array( $index, $found_keys, true ) ) {
					$issues[] = array(
						'code'     => 'missing_index',
						'severity' => 'warning',
						'message'  => sprintf( 'Index %s on %s is missing.', $index, $full ),
						'table'    => $table,
						'index'    => $index,
						'fixable'  => true,
					);
				}
			}
		}

		return $issues;
	}

	/**
	 * Check for orphaned queue items (subscriber deleted but queue record remains).
	 *
	 * @return array
	 */
	public function check_orphaned_queue_items(): array {
		$issues = array();
		$queue  = $this->prefix . 'swpm_queue';
		$subs   = $this->prefix . 'swpm_subscribers';

		// Skip if tables don't exist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$q_exists = $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $queue ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$s_exists = $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $subs ) );

		if ( ! $q_exists || ! $s_exists ) {
			return $issues;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$orphan_count = (int) $this->db->get_var(
			"SELECT COUNT(*) FROM {$queue} q
			 LEFT JOIN {$subs} s ON q.subscriber_id = s.id
			 WHERE q.subscriber_id IS NOT NULL AND s.id IS NULL"
		);

		if ( $orphan_count > 0 ) {
			$issues[] = array(
				'code'     => 'orphaned_queue_items',
				'severity' => 'warning',
				'message'  => sprintf( '%d orphaned queue items found (subscriber deleted).', $orphan_count ),
				'count'    => $orphan_count,
				'fixable'  => true,
			);
		}

		return $issues;
	}

	/**
	 * Check for stuck queue items (status=sending for too long).
	 *
	 * @return array
	 */
	public function check_stuck_queue_items(): array {
		$issues = array();
		$table  = $this->prefix . 'swpm_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			return $issues;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::STUCK_THRESHOLD );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$stuck_count = (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = 'sending' AND created_at < %s",
				$cutoff
			)
		);

		if ( $stuck_count > 0 ) {
			$issues[] = array(
				'code'     => 'stuck_queue_items',
				'severity' => 'warning',
				'message'  => sprintf( '%d queue items stuck in "sending" status for over %d minutes.', $stuck_count, self::STUCK_THRESHOLD / 60 ),
				'count'    => $stuck_count,
				'fixable'  => true,
			);
		}

		return $issues;
	}

	/**
	 * Check for missing or corrupted core WP options.
	 *
	 * @return array
	 */
	public function check_options(): array {
		$issues = array();

		foreach ( self::REQUIRED_OPTIONS as $option => $type ) {
			$value = get_option( $option, '__SWPM_MISSING__' );

			if ( '__SWPM_MISSING__' === $value ) {
				$issues[] = array(
					'code'     => 'missing_option',
					'severity' => 'warning',
					'message'  => sprintf( 'Option "%s" is missing.', $option ),
					'option'   => $option,
					'fixable'  => true,
				);
				continue;
			}

			if ( 'string' === $type && ! is_string( $value ) && ! is_numeric( $value ) ) {
				$issues[] = array(
					'code'     => 'corrupted_option',
					'severity' => 'warning',
					'message'  => sprintf( 'Option "%s" has unexpected type (%s).', $option, gettype( $value ) ),
					'option'   => $option,
					'fixable'  => false,
				);
			}
		}

		return $issues;
	}

	/**
	 * Check for autoload option bloat from swpm_ options.
	 *
	 * @return array
	 */
	public function check_autoload_bloat(): array {
		$issues = array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$bloat = $this->db->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$this->db->options}
			 WHERE option_name LIKE 'swpm\_%' AND autoload = 'yes'"
		);

		$bloat_bytes = (int) $bloat;
		// Warn if swpm autoloaded options exceed 500 KB.
		if ( $bloat_bytes > 512000 ) {
			$issues[] = array(
				'code'     => 'autoload_bloat',
				'severity' => 'info',
				'message'  => sprintf( 'SWPMail autoloaded options total %s.', size_format( $bloat_bytes ) ),
				'bytes'    => $bloat_bytes,
				'fixable'  => false,
			);
		}

		return $issues;
	}

	// ------------------------------------------------------------------
	// Repair actions
	// ----------------------------------------------------------------

	/**
	 * Run all auto-fixable repairs and return results.
	 *
	 * @return array{fixed: array, errors: array}
	 */
	public function repair(): array {
		$diagnosis = $this->diagnose();
		$fixed     = array();
		$errors    = array();

		foreach ( $diagnosis['issues'] as $issue ) {
			if ( empty( $issue['fixable'] ) ) {
				continue;
			}

			$result = $this->fix_issue( $issue );
			if ( true === $result ) {
				$fixed[] = $issue;
			} else {
				$issue['error'] = $result;
				$errors[]       = $issue;
			}
		}

		swpm_log(
			'info',
			'DB repair completed.',
			array(
				'fixed'  => count( $fixed ),
				'errors' => count( $errors ),
			)
		);

		return array(
			'fixed'  => $fixed,
			'errors' => $errors,
		);
	}

	/**
	 * Fix a single issue.
	 *
	 * @param array $issue Issue descriptor from diagnose().
	 * @return true|string True on success, error message on failure.
	 */
	private function fix_issue( array $issue ) {
		switch ( $issue['code'] ) {
			case 'missing_table':
			case 'missing_column':
			case 'missing_index':
				return $this->repair_schema();

			case 'orphaned_queue_items':
				return $this->fix_orphaned_queue_items();

			case 'stuck_queue_items':
				return $this->fix_stuck_queue_items();

			case 'missing_option':
				return $this->fix_missing_option( $issue['option'] );

			default:
				return 'No automatic fix available.';
		}
	}

	/**
	 * Re-run dbDelta to repair schema (tables, columns, indexes).
	 *
	 * @return true|string
	 */
	private function repair_schema() {
		SWPM_Activator::activate();
		return true;
	}

	/**
	 * Null out subscriber_id on orphaned queue items.
	 *
	 * @return true|string
	 */
	private function fix_orphaned_queue_items() {
		$queue = $this->prefix . 'swpm_queue';
		$subs  = $this->prefix . 'swpm_subscribers';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$affected = $this->db->query(
			"UPDATE {$queue} q
			 LEFT JOIN {$subs} s ON q.subscriber_id = s.id
			 SET q.subscriber_id = NULL
			 WHERE q.subscriber_id IS NOT NULL AND s.id IS NULL"
		);

		return false !== $affected ? true : 'Failed to clean orphaned queue items.';
	}

	/**
	 * Reset stuck "sending" items back to "pending" for retry.
	 *
	 * @return true|string
	 */
	private function fix_stuck_queue_items() {
		$table  = $this->prefix . 'swpm_queue';
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::STUCK_THRESHOLD );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$affected = $this->db->query(
			$this->db->prepare(
				"UPDATE {$table}
				 SET status = 'pending'
				 WHERE status = 'sending' AND created_at < %s",
				$cutoff
			)
		);

		return false !== $affected ? true : 'Failed to reset stuck queue items.';
	}

	/**
	 * Restore a missing core option with default value.
	 *
	 * @param string $option Option name.
	 * @return true|string
	 */
	private function fix_missing_option( string $option ) {
		$defaults = array(
			'swpm_mail_provider'    => 'smtp',
			'swpm_from_name'        => get_bloginfo( 'name' ),
			'swpm_from_email'       => get_option( 'admin_email' ),
			'swpm_override_wp_mail' => true,
			'swpm_db_version'       => SWPM_VERSION,
		);

		if ( ! isset( $defaults[ $option ] ) ) {
			return 'No default value defined.';
		}

		add_option( $option, $defaults[ $option ] );
		return true;
	}

	// ------------------------------------------------------------------
	// AJAX handlers
	// ----------------------------------------------------------------

	/**
	 * AJAX: Run diagnosis.
	 */
	public function ajax_diagnose(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		wp_send_json_success( $this->diagnose() );
	}

	/**
	 * AJAX: Run repair.
	 */
	public function ajax_repair(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		wp_send_json_success( $this->repair() );
	}
}
