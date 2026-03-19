<?php
/**
 * WP-CLI commands for SWPMail.
 *
 * Provides command-line access to key plugin operations:
 *
 *   wp swpmail status          — Overall status and health.
 *   wp swpmail test            — Send a test email.
 *   wp swpmail queue list      — Show queue items.
 *   wp swpmail queue process   — Process the mail queue.
 *   wp swpmail queue flush     — Delete sent/failed items.
 *   wp swpmail log             — Show recent log entries.
 *   wp swpmail provider        — Show active provider info.
 *   wp swpmail provider list   — List available providers.
 *   wp swpmail provider switch — Switch the active provider.
 *   wp swpmail db diagnose     — Run database diagnostics.
 *   wp swpmail db repair       — Repair database issues.
 *   wp swpmail conflicts       — Detect plugin conflicts.
 *   wp swpmail cron list       — Show scheduled cron events.
 *   wp swpmail cron run        — Manually trigger a cron event.
 *   wp swpmail subscriber list — List subscribers.
 *   wp swpmail reset           — Reset all plugin settings.
 *
 * @package SWPMail
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class SWPM_CLI extends WP_CLI_Command {

	/* ==================================================================
	 * wp swpmail status
	 * ================================================================*/

	/**
	 * Show SWPMail overall status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail status
	 *
	 * @subcommand status
	 */
	public function status( $args, $assoc_args ) {
		global $wpdb;

		$provider_key = get_option( 'swpm_mail_provider', 'smtp' );
		$override     = get_option( 'swpm_override_wp_mail', true );
		$from_email   = get_option( 'swpm_from_email', '' );
		$from_name    = get_option( 'swpm_from_name', '' );
		$db_version   = get_option( 'swpm_db_version', 'N/A' );
		$last_run     = get_option( 'swpm_queue_last_run', 0 );

		// Queue stats.
		$queue_table = $wpdb->prefix . 'swpm_queue';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$queue_stats = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$queue_table} GROUP BY status" );
		$q = array( 'pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0 );
		foreach ( $queue_stats as $row ) {
			$q[ $row->status ] = (int) $row->cnt;
		}

		// Subscriber count.
		$sub_table = $wpdb->prefix . 'swpm_subscribers';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$sub_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sub_table} WHERE status = 'confirmed'" );

		$rows = array(
			array( 'Key', 'Value' ),
			array( 'Plugin Version', SWPM_VERSION ),
			array( 'DB Version', $db_version ),
			array( 'Active Provider', $provider_key ),
			array( 'wp_mail Override', $override ? 'Enabled' : 'Disabled' ),
			array( 'From Email', $from_email ),
			array( 'From Name', $from_name ),
			array( 'Queue (pending)', (string) $q['pending'] ),
			array( 'Queue (sent)', (string) $q['sent'] ),
			array( 'Queue (failed)', (string) $q['failed'] ),
			array( 'Confirmed Subscribers', (string) $sub_count ),
			array( 'Queue Last Run', $last_run ? human_time_diff( (int) $last_run ) . ' ago' : 'Never' ),
		);

		$table = new \cli\Table();
		$table->setHeaders( $rows[0] );
		foreach ( array_slice( $rows, 1 ) as $row ) {
			$table->addRow( $row );
		}
		$table->display();
	}

	/* ==================================================================
	 * wp swpmail test
	 * ================================================================*/

	/**
	 * Send a test email.
	 *
	 * ## OPTIONS
	 *
	 * [--to=<email>]
	 * : Recipient email address. Defaults to admin email.
	 *
	 * [--subject=<subject>]
	 * : Email subject. Defaults to "SWPMail Test".
	 *
	 * [--body=<body>]
	 * : Email body. Defaults to a standard test message.
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail test
	 *     wp swpmail test --to=user@example.com
	 *
	 * @subcommand test
	 */
	public function test( $args, $assoc_args ) {
		$to      = \WP_CLI\Utils\get_flag_value( $assoc_args, 'to', get_option( 'admin_email' ) );
		$subject = \WP_CLI\Utils\get_flag_value( $assoc_args, 'subject', 'SWPMail Test' );
		$body    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'body', sprintf(
			'This is a test email from SWPMail on %s. Sent at %s.',
			get_bloginfo( 'name' ),
			current_time( 'mysql' )
		) );

		if ( ! is_email( $to ) ) {
			WP_CLI::error( 'Invalid recipient email address.' );
		}

		WP_CLI::log( "Sending test email to {$to}..." );

		$result = wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );

		if ( $result ) {
			WP_CLI::success( "Test email sent to {$to}." );
		} else {
			WP_CLI::error( "Failed to send test email to {$to}." );
		}
	}

	/* ==================================================================
	 * wp swpmail queue
	 * ================================================================*/

	/**
	 * List queue items.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Filter by status (pending, sending, sent, failed).
	 *
	 * [--limit=<number>]
	 * : Number of items to show. Default: 20.
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv). Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail queue list
	 *     wp swpmail queue list --status=failed --limit=50
	 *
	 * @subcommand queue list
	 */
	public function queue_list( $args, $assoc_args ) {
		global $wpdb;

		$status = \WP_CLI\Utils\get_flag_value( $assoc_args, 'status', '' );
		$limit  = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 20 ) );
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$table  = $wpdb->prefix . 'swpm_queue';

		$where = '';
		$params = array();
		if ( $status && in_array( $status, array( 'pending', 'sending', 'sent', 'failed' ), true ) ) {
			$where    = 'WHERE status = %s';
			$params[] = $status;
		}

		$params[] = $limit;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, to_email, subject, status, attempts, provider_used, scheduled_at, sent_at, error_message
				 FROM {$table} {$where} ORDER BY id DESC LIMIT %d",
				...$params
			),
			ARRAY_A
		);

		if ( empty( $items ) ) {
			WP_CLI::log( 'No queue items found.' );
			return;
		}

		// Truncate long fields.
		foreach ( $items as &$item ) {
			$item['subject']       = mb_substr( $item['subject'] ?? '', 0, 40 );
			$item['error_message'] = mb_substr( $item['error_message'] ?? '', 0, 50 );
		}
		unset( $item );

		\WP_CLI\Utils\format_items(
			$format,
			$items,
			array( 'id', 'to_email', 'subject', 'status', 'attempts', 'provider_used', 'scheduled_at', 'error_message' )
		);
	}

	/**
	 * Process the mail queue now.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Max items to process. Default: 50.
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail queue process
	 *     wp swpmail queue process --limit=100
	 *
	 * @subcommand queue process
	 */
	public function queue_process( $args, $assoc_args ) {
		$limit = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 50 ) );

		/** @var SWPM_Queue|null $queue */
		$queue = swpm( 'queue' );
		if ( ! $queue ) {
			WP_CLI::error( 'Queue service not available.' );
		}

		WP_CLI::log( "Processing up to {$limit} queue items..." );
		$processed = $queue->process( $limit );
		WP_CLI::success( "Processed {$processed} queue items." );
	}

	/**
	 * Flush completed and/or failed items from the queue.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Status to flush: sent, failed, or all. Default: sent.
	 *
	 * [--older-than=<days>]
	 * : Only flush items older than N days. Default: 0 (all matching).
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail queue flush
	 *     wp swpmail queue flush --status=failed
	 *     wp swpmail queue flush --status=all --older-than=30
	 *
	 * @subcommand queue flush
	 */
	public function queue_flush( $args, $assoc_args ) {
		global $wpdb;

		$status    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'status', 'sent' );
		$older     = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'older-than', 0 ) );
		$table     = $wpdb->prefix . 'swpm_queue';
		$where     = array();
		$params    = array();

		if ( 'all' === $status ) {
			$where[] = "status IN ('sent', 'failed')";
		} elseif ( in_array( $status, array( 'sent', 'failed' ), true ) ) {
			$where[] = 'status = %s';
			$params[] = $status;
		} else {
			WP_CLI::error( 'Invalid status. Use: sent, failed, or all.' );
		}

		if ( $older > 0 ) {
			$cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $older * DAY_IN_SECONDS ) );
			$where[]  = 'created_at < %s';
			$params[] = $cutoff;
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var(
			empty( $params )
				? "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}"
				: $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", ...$params )
		);

		if ( 0 === $count ) {
			WP_CLI::log( 'No matching queue items to flush.' );
			return;
		}

		WP_CLI::confirm( "This will delete {$count} queue items. Continue?" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			empty( $params )
				? "DELETE FROM {$table} WHERE {$where_sql}"
				: $wpdb->prepare( "DELETE FROM {$table} WHERE {$where_sql}", ...$params )
		);

		WP_CLI::success( "Flushed {$deleted} queue items." );
	}

	/* ==================================================================
	 * wp swpmail log
	 * ================================================================*/

	/**
	 * Show recent log entries.
	 *
	 * ## OPTIONS
	 *
	 * [--level=<level>]
	 * : Filter by level (debug, info, warning, error).
	 *
	 * [--limit=<number>]
	 * : Number of entries. Default: 20.
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv). Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail log
	 *     wp swpmail log --level=error --limit=50
	 *
	 * @subcommand log
	 */
	public function log( $args, $assoc_args ) {
		global $wpdb;

		$level  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'level', '' );
		$limit  = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 20 ) );
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$table  = $wpdb->prefix . 'swpm_logs';

		$where  = '';
		$params = array();
		if ( $level && in_array( $level, array( 'debug', 'info', 'warning', 'error' ), true ) ) {
			$where    = 'WHERE level = %s';
			$params[] = $level;
		}

		$params[] = $limit;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, level, message, provider, created_at
				 FROM {$table} {$where} ORDER BY id DESC LIMIT %d",
				...$params
			),
			ARRAY_A
		);

		if ( empty( $items ) ) {
			WP_CLI::log( 'No log entries found.' );
			return;
		}

		foreach ( $items as &$item ) {
			$item['message'] = mb_substr( $item['message'] ?? '', 0, 80 );
		}
		unset( $item );

		\WP_CLI\Utils\format_items(
			$format,
			$items,
			array( 'id', 'level', 'provider', 'message', 'created_at' )
		);
	}

	/* ==================================================================
	 * wp swpmail provider
	 * ================================================================*/

	/**
	 * Show active provider information.
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail provider
	 *
	 * @subcommand provider
	 */
	public function provider( $args, $assoc_args ) {
		$key    = get_option( 'swpm_mail_provider', 'smtp' );
		$backup = get_option( 'swpm_backup_provider', '' );

		/** @var SWPM_Provider_Interface|null $provider */
		$provider = swpm( 'provider' );
		$label    = $provider ? $provider->get_label() : $key;

		$rows = array(
			array( 'Setting', 'Value' ),
			array( 'Active Provider', $label . " ({$key})" ),
			array( 'Backup Provider', $backup ?: 'None' ),
			array( 'From Email', get_option( 'swpm_from_email', '' ) ),
			array( 'From Name', get_option( 'swpm_from_name', '' ) ),
		);

		// Connection health.
		$health = get_option( 'swpm_connection_health_primary', array() );
		if ( is_array( $health ) && ! empty( $health ) ) {
			$rows[] = array( 'Primary Health', ( $health['healthy'] ?? true ) ? 'Healthy' : 'Unhealthy' );
			$rows[] = array( 'Failure Count', (string) ( $health['failures'] ?? 0 ) );
		}

		$table = new \cli\Table();
		$table->setHeaders( $rows[0] );
		foreach ( array_slice( $rows, 1 ) as $row ) {
			$table->addRow( $row );
		}
		$table->display();
	}

	/**
	 * List all available providers.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv). Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail provider list
	 *
	 * @subcommand provider list
	 */
	public function provider_list( $args, $assoc_args ) {
		$format  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		/** @var SWPM_Provider_Factory|null $factory */
		$factory = swpm( 'provider_factory' );
		if ( ! $factory ) {
			WP_CLI::error( 'Provider factory not available.' );
		}

		$registry = $factory->get_all();
		$current  = get_option( 'swpm_mail_provider', 'smtp' );
		$items    = array();

		foreach ( $registry as $key => $class ) {
			$items[] = array(
				'key'    => $key,
				'class'  => $class,
				'active' => $key === $current ? 'Yes' : '',
			);
		}

		\WP_CLI\Utils\format_items( $format, $items, array( 'key', 'class', 'active' ) );
	}

	/**
	 * Switch the active mail provider.
	 *
	 * ## OPTIONS
	 *
	 * <provider>
	 * : Provider key (e.g. smtp, mailgun, sendgrid).
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail provider switch mailgun
	 *
	 * @subcommand provider switch
	 */
	public function provider_switch( $args, $assoc_args ) {
		$key = sanitize_key( $args[0] );

		/** @var SWPM_Provider_Factory|null $factory */
		$factory = swpm( 'provider_factory' );
		if ( ! $factory ) {
			WP_CLI::error( 'Provider factory not available.' );
		}

		$registry = $factory->get_all();
		if ( ! isset( $registry[ $key ] ) ) {
			WP_CLI::error( "Unknown provider: {$key}. Use 'wp swpmail provider list' to see available providers." );
		}

		// Check if locked by constant.
		if ( defined( 'SWPM_MAIL_PROVIDER' ) ) {
			WP_CLI::error( 'Provider is locked via SWPM_MAIL_PROVIDER constant in wp-config.php.' );
		}

		update_option( 'swpm_mail_provider', $key );
		WP_CLI::success( "Active provider switched to: {$key}." );
	}

	/* ==================================================================
	 * wp swpmail db
	 * ================================================================*/

	/**
	 * Run database diagnostics.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json). Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail db diagnose
	 *
	 * @subcommand db diagnose
	 */
	public function db_diagnose( $args, $assoc_args ) {
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$repair = new SWPM_DB_Repair();
		$result = $repair->diagnose();

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT ) );
			return;
		}

		$summary = $result['summary'];
		WP_CLI::log( sprintf(
			"Database Health: %s | Critical: %d | Warnings: %d | Info: %d",
			$summary['healthy'] ? 'Healthy' : 'Issues Found',
			$summary['critical'],
			$summary['warning'],
			$summary['info']
		) );

		if ( empty( $result['issues'] ) ) {
			WP_CLI::success( 'No issues found. Database is healthy.' );
			return;
		}

		$items = array();
		foreach ( $result['issues'] as $issue ) {
			$items[] = array(
				'severity' => strtoupper( $issue['severity'] ),
				'code'     => $issue['code'],
				'message'  => $issue['message'],
				'fixable'  => ! empty( $issue['fixable'] ) ? 'Yes' : 'No',
			);
		}

		\WP_CLI\Utils\format_items( 'table', $items, array( 'severity', 'code', 'message', 'fixable' ) );
	}

	/**
	 * Repair database issues.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Only show what would be fixed without making changes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail db repair
	 *     wp swpmail db repair --dry-run
	 *
	 * @subcommand db repair
	 */
	public function db_repair( $args, $assoc_args ) {
		$dry_run = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		$repair    = new SWPM_DB_Repair();
		$diagnosis = $repair->diagnose();

		$fixable = array_filter( $diagnosis['issues'], fn( $i ) => ! empty( $i['fixable'] ) );

		if ( empty( $fixable ) ) {
			WP_CLI::success( 'No fixable issues found.' );
			return;
		}

		WP_CLI::log( sprintf( 'Found %d fixable issues:', count( $fixable ) ) );
		foreach ( $fixable as $issue ) {
			WP_CLI::log( "  [{$issue['severity']}] {$issue['message']}" );
		}

		if ( $dry_run ) {
			WP_CLI::log( '(Dry run — no changes made.)' );
			return;
		}

		WP_CLI::confirm( 'Proceed with repairs?' );

		$result = $repair->repair();

		if ( ! empty( $result['fixed'] ) ) {
			WP_CLI::success( sprintf( 'Fixed %d issues:', count( $result['fixed'] ) ) );
			foreach ( $result['fixed'] as $f ) {
				WP_CLI::log( "  ✓ {$f['message']}" );
			}
		}

		if ( ! empty( $result['errors'] ) ) {
			WP_CLI::warning( sprintf( '%d issues could not be fixed:', count( $result['errors'] ) ) );
			foreach ( $result['errors'] as $e ) {
				WP_CLI::log( "  ✗ {$e['message']} — {$e['error']}" );
			}
		}
	}

	/* ==================================================================
	 * wp swpmail conflicts
	 * ================================================================*/

	/**
	 * Detect plugin and environment conflicts.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json). Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail conflicts
	 *
	 * @subcommand conflicts
	 */
	public function conflicts( $args, $assoc_args ) {
		$format   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$detector = new SWPM_Conflict_Detector();
		$result   = $detector->detect();

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $result, JSON_PRETTY_PRINT ) );
			return;
		}

		$summary = $result['summary'];
		WP_CLI::log( sprintf(
			"Conflict Scan: %s | Critical: %d | Warnings: %d | Info: %d",
			$summary['clean'] ? 'Clean' : 'Issues Found',
			$summary['critical'],
			$summary['warning'],
			$summary['info']
		) );

		if ( empty( $result['conflicts'] ) ) {
			WP_CLI::success( 'No conflicts detected.' );
			return;
		}

		$items = array();
		foreach ( $result['conflicts'] as $conflict ) {
			$items[] = array(
				'severity'   => strtoupper( $conflict['severity'] ),
				'code'       => $conflict['code'],
				'message'    => $conflict['message'],
				'resolution' => $conflict['resolution'] ?? '',
			);
		}

		\WP_CLI\Utils\format_items( 'table', $items, array( 'severity', 'code', 'message', 'resolution' ) );
	}

	/* ==================================================================
	 * wp swpmail cron
	 * ================================================================*/

	/**
	 * List SWPMail scheduled cron events.
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail cron list
	 *
	 * @subcommand cron list
	 */
	public function cron_list( $args, $assoc_args ) {
		$hooks = array(
			'swpm_process_queue',
			'swpm_send_daily_digest',
			'swpm_send_weekly_digest',
			'swpm_cleanup_logs',
			'swpm_cleanup_queue',
			'swpm_cleanup_tracking',
		);

		$items = array();
		foreach ( $hooks as $hook ) {
			$next = wp_next_scheduled( $hook );
			$items[] = array(
				'hook'      => $hook,
				'scheduled' => false !== $next ? 'Yes' : 'No',
				'next_run'  => false !== $next ? gmdate( 'Y-m-d H:i:s', $next ) . ' UTC' : 'Not scheduled',
				'in'        => false !== $next ? human_time_diff( time(), $next ) : '—',
			);
		}

		\WP_CLI\Utils\format_items( 'table', $items, array( 'hook', 'scheduled', 'next_run', 'in' ) );
	}

	/**
	 * Manually trigger a SWPMail cron event.
	 *
	 * ## OPTIONS
	 *
	 * <hook>
	 * : The cron hook to run (e.g. swpm_process_queue).
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail cron run swpm_process_queue
	 *     wp swpmail cron run swpm_cleanup_logs
	 *
	 * @subcommand cron run
	 */
	public function cron_run( $args, $assoc_args ) {
		$hook = sanitize_key( $args[0] );

		$allowed = array(
			'swpm_process_queue',
			'swpm_send_daily_digest',
			'swpm_send_weekly_digest',
			'swpm_cleanup_logs',
			'swpm_cleanup_queue',
			'swpm_cleanup_tracking',
		);

		if ( ! in_array( $hook, $allowed, true ) ) {
			WP_CLI::error( "Unknown hook: {$hook}. Allowed: " . implode( ', ', $allowed ) );
		}

		WP_CLI::log( "Running {$hook}..." );
		do_action( $hook );
		WP_CLI::success( "{$hook} executed." );
	}

	/* ==================================================================
	 * wp swpmail subscriber
	 * ================================================================*/

	/**
	 * List subscribers.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Filter by status (pending, confirmed, unsubscribed, bounced).
	 *
	 * [--limit=<number>]
	 * : Number of items. Default: 20.
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv). Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail subscriber list
	 *     wp swpmail subscriber list --status=confirmed --limit=100
	 *
	 * @subcommand subscriber list
	 */
	public function subscriber_list( $args, $assoc_args ) {
		global $wpdb;

		$status = \WP_CLI\Utils\get_flag_value( $assoc_args, 'status', '' );
		$limit  = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 20 ) );
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$table  = $wpdb->prefix . 'swpm_subscribers';

		$where  = '';
		$params = array();
		if ( $status && in_array( $status, array( 'pending', 'confirmed', 'unsubscribed', 'bounced' ), true ) ) {
			$where    = 'WHERE status = %s';
			$params[] = $status;
		}

		$params[] = $limit;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, email, name, status, frequency, confirmed_at, created_at
				 FROM {$table} {$where} ORDER BY id DESC LIMIT %d",
				...$params
			),
			ARRAY_A
		);

		if ( empty( $items ) ) {
			WP_CLI::log( 'No subscribers found.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $items, array( 'id', 'email', 'name', 'status', 'frequency', 'confirmed_at', 'created_at' ) );
	}

	/* ==================================================================
	 * wp swpmail reset
	 * ================================================================*/

	/**
	 * Reset all SWPMail settings (dangerous).
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp swpmail reset --yes
	 *
	 * @subcommand reset
	 */
	public function reset( $args, $assoc_args ) {
		WP_CLI::confirm( 'This will delete ALL SWPMail options and tables. This cannot be undone. Continue?', $assoc_args );

		global $wpdb;

		// Drop tables.
		$tables = array( 'swpm_subscribers', 'swpm_queue', 'swpm_logs', 'swpm_tracking' );
		foreach ( $tables as $t ) {
			$full = $wpdb->prefix . $t;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $full ) );
			WP_CLI::log( "Dropped table: {$full}" );
		}

		// Delete options.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$options = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'swpm\_%'"
		);
		foreach ( $options as $opt ) {
			delete_option( $opt );
		}
		WP_CLI::log( sprintf( 'Deleted %d options.', count( $options ) ) );

		// Delete transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_swpm\_%' OR option_name LIKE '_transient_timeout_swpm\_%'"
		);

		// Re-create tables with defaults.
		SWPM_Activator::activate();
		WP_CLI::success( 'SWPMail has been reset to factory defaults.' );
	}
}
