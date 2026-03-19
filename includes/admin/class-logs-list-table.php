<?php
/**
 * Email Logs WP_List_Table.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class SWPM_Logs_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'Email Log', 'swpmail' ),
			'plural'   => __( 'Email Logs', 'swpmail' ),
			'ajax'     => false,
		) );
	}

	/**
	 * Define table columns.
	 */
	public function get_columns(): array {
		return array(
			'cb'            => '<input type="checkbox" />',
			'to_email'      => __( 'Recipient', 'swpmail' ),
			'subject'       => __( 'Subject', 'swpmail' ),
			'status'        => __( 'Status', 'swpmail' ),
			'provider_used' => __( 'Provider', 'swpmail' ),
			'opens'         => __( 'Opens', 'swpmail' ),
			'clicks'        => __( 'Clicks', 'swpmail' ),
			'sent_at'       => __( 'Sent', 'swpmail' ),
		);
	}

	/**
	 * Sortable columns.
	 */
	public function get_sortable_columns(): array {
		return array(
			'to_email'      => array( 'to_email', false ),
			'status'        => array( 'status', false ),
			'provider_used' => array( 'provider_used', false ),
			'sent_at'       => array( 'sent_at', true ),
		);
	}

	/**
	 * Bulk actions.
	 */
	public function get_bulk_actions(): array {
		return array(
			'delete' => __( 'Delete', 'swpmail' ),
		);
	}

	/**
	 * Checkbox column.
	 */
	public function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="log_ids[]" value="%d" />', absint( $item->id ) );
	}

	/**
	 * Recipient column with row actions.
	 */
	public function column_to_email( $item ): string {
		$detail_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'swpmail-logs',
					'action' => 'view',
					'id'     => absint( $item->id ),
				),
				admin_url( 'admin.php' )
			),
			'swpm_view_log_' . $item->id
		);

		$actions = array(
			'view'   => sprintf(
				'<a href="#" class="swpm-log-detail-link" data-id="%d">%s</a>',
				absint( $item->id ),
				esc_html__( 'View Details', 'swpmail' )
			),
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'page'   => 'swpmail-logs',
								'action' => 'delete',
								'id'     => absint( $item->id ),
							),
							admin_url( 'admin.php' )
						),
						'swpm_delete_log_' . $item->id
					)
				),
				esc_js( __( 'Are you sure?', 'swpmail' ) ),
				esc_html__( 'Delete', 'swpmail' )
			),
		);

		return sprintf( '%s %s', esc_html( $item->to_email ), $this->row_actions( $actions ) );
	}

	/**
	 * Subject column.
	 */
	public function column_subject( $item ): string {
		$subject = $item->subject ?: '—';
		return sprintf(
			'<span class="swpm-log-subject" title="%s">%s</span>',
			esc_attr( $subject ),
			esc_html( mb_strimwidth( $subject, 0, 60, '…' ) )
		);
	}

	/**
	 * Status column with badge.
	 */
	public function column_status( $item ): string {
		$labels = array(
			'pending' => __( 'Pending', 'swpmail' ),
			'sending' => __( 'Sending', 'swpmail' ),
			'sent'    => __( 'Sent', 'swpmail' ),
			'failed'  => __( 'Failed', 'swpmail' ),
		);
		$label = $labels[ $item->status ] ?? $item->status;

		$class_map = array(
			'pending' => 'warning',
			'sending' => 'info',
			'sent'    => 'success',
			'failed'  => 'danger',
		);
		$class = $class_map[ $item->status ] ?? 'default';

		$output = sprintf(
			'<span class="swpm-log-badge swpm-log-badge--%s">%s</span>',
			esc_attr( $class ),
			esc_html( $label )
		);

		if ( 'failed' === $item->status && ! empty( $item->error_message ) ) {
			$output .= sprintf(
				'<span class="swpm-log-error" title="%s">%s</span>',
				esc_attr( $item->error_message ),
				esc_html( mb_strimwidth( $item->error_message, 0, 40, '…' ) )
			);
		}

		return $output;
	}

	/**
	 * Provider column.
	 */
	public function column_provider_used( $item ): string {
		return esc_html( $item->provider_used ?: '—' );
	}

	/**
	 * Open count column.
	 */
	public function column_opens( $item ): string {
		$count = (int) ( $item->open_count ?? 0 );
		if ( 0 === $count ) {
			return '<span class="swpm-log-tracking-zero">—</span>';
		}
		return sprintf(
			'<span class="swpm-log-tracking-count swpm-log-tracking-count--open" title="%s">%s</span>',
			esc_attr( sprintf( _n( '%d open', '%d opens', $count, 'swpmail' ), $count ) ),
			esc_html( number_format_i18n( $count ) )
		);
	}

	/**
	 * Click count column.
	 */
	public function column_clicks( $item ): string {
		$count = (int) ( $item->click_count ?? 0 );
		if ( 0 === $count ) {
			return '<span class="swpm-log-tracking-zero">—</span>';
		}
		return sprintf(
			'<span class="swpm-log-tracking-count swpm-log-tracking-count--click" title="%s">%s</span>',
			esc_attr( sprintf( _n( '%d click', '%d clicks', $count, 'swpmail' ), $count ) ),
			esc_html( number_format_i18n( $count ) )
		);
	}

	/**
	 * Sent at column.
	 */
	public function column_sent_at( $item ): string {
		$date = $item->sent_at ?: $item->created_at;
		if ( empty( $date ) ) {
			return '—';
		}
		$ts = strtotime( $date );

		return sprintf(
			'<span title="%s">%s</span>',
			esc_attr( wp_date( 'Y-m-d H:i:s', $ts ) ),
			esc_html( human_time_diff( $ts, time() ) . ' ' . __( 'ago', 'swpmail' ) )
		);
	}

	/**
	 * Fetch table data.
	 */
	public function prepare_items(): void {
		global $wpdb;

		$queue_table    = $wpdb->prefix . 'swpm_queue';
		$tracking_table = $wpdb->prefix . 'swpm_tracking';

		$per_page = 20;
		$page     = $this->get_pagenum();
		$offset   = ( $page - 1 ) * $per_page;

		// Column headers.
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		// Handle bulk delete.
		$this->process_bulk_action();

		// Nonce verification.
		if ( isset( $_REQUEST['_wpnonce'] ) || isset( $_REQUEST['s'] ) || isset( $_REQUEST['log_status'] ) || isset( $_REQUEST['filter_action'] ) || isset( $_REQUEST['orderby'] ) || isset( $_REQUEST['paged'] ) ) {
			if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'swpm_logs_filter' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'swpmail' ), 403 );
			}
		} elseif ( 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			check_admin_referer( -1, false );
		}

		// Search.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$where  = '';
		$params = array();

		if ( '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where  = 'WHERE q.to_email LIKE %s OR q.subject LIKE %s';
			$params = array( $like, $like );
		}

		// Status filter.
		$status_filter = isset( $_REQUEST['log_status'] ) ? sanitize_key( $_REQUEST['log_status'] ) : '';
		if ( in_array( $status_filter, array( 'pending', 'sending', 'sent', 'failed' ), true ) ) {
			$where .= empty( $where ) ? 'WHERE q.status = %s' : ' AND q.status = %s';
			$params[] = $status_filter;
		}

		// Provider filter.
		$provider_filter = isset( $_REQUEST['provider'] ) ? sanitize_key( $_REQUEST['provider'] ) : '';
		if ( '' !== $provider_filter ) {
			$where .= empty( $where ) ? 'WHERE q.provider_used = %s' : ' AND q.provider_used = %s';
			$params[] = $provider_filter;
		}

		// Order.
		$allowed_orderby = array( 'to_email', 'status', 'provider_used', 'sent_at' );
		$orderby_raw     = isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], $allowed_orderby, true )
			? $_REQUEST['orderby']
			: 'sent_at';
		// Map to fully-qualified column to avoid ambiguity.
		$orderby_map = array(
			'to_email'      => 'q.to_email',
			'status'        => 'q.status',
			'provider_used' => 'q.provider_used',
			'sent_at'       => 'q.sent_at',
		);
		$orderby = $orderby_map[ $orderby_raw ] ?? 'q.sent_at';

		$order = isset( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ? 'ASC' : 'DESC';

		// Total items.
		$count_sql = "SELECT COUNT(*) FROM {$queue_table} q {$where}";
		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		// Main query — join tracking counts.
		$sql = "SELECT q.*,
				COALESCE(t_opens.cnt,  0) AS open_count,
				COALESCE(t_clicks.cnt, 0) AS click_count
			FROM {$queue_table} q
			LEFT JOIN (
				SELECT queue_id, COUNT(*) AS cnt
				FROM {$tracking_table}
				WHERE event_type = 'open'
				GROUP BY queue_id
			) t_opens ON t_opens.queue_id = q.id
			LEFT JOIN (
				SELECT queue_id, COUNT(*) AS cnt
				FROM {$tracking_table}
				WHERE event_type = 'click'
				GROUP BY queue_id
			) t_clicks ON t_clicks.queue_id = q.id
			{$where}
			ORDER BY {$orderby} {$order}
			LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$this->items = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
		) );
	}

	/**
	 * Extra table nav: status + provider filters.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		global $wpdb;
		$queue_table = $wpdb->prefix . 'swpm_queue';

		$current_status   = isset( $_REQUEST['log_status'] ) ? sanitize_key( $_REQUEST['log_status'] ) : '';
		$current_provider = isset( $_REQUEST['provider'] ) ? sanitize_key( $_REQUEST['provider'] ) : '';

		// Get distinct providers.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$providers = $wpdb->get_col( "SELECT DISTINCT provider_used FROM {$queue_table} WHERE provider_used IS NOT NULL AND provider_used != '' ORDER BY provider_used" );

		?>
		<div class="alignleft actions">
			<?php wp_nonce_field( 'swpm_logs_filter', '_wpnonce', false ); ?>
			<select name="log_status">
				<option value=""><?php esc_html_e( 'All Statuses', 'swpmail' ); ?></option>
				<option value="pending" <?php selected( $current_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'swpmail' ); ?></option>
				<option value="sending" <?php selected( $current_status, 'sending' ); ?>><?php esc_html_e( 'Sending', 'swpmail' ); ?></option>
				<option value="sent" <?php selected( $current_status, 'sent' ); ?>><?php esc_html_e( 'Sent', 'swpmail' ); ?></option>
				<option value="failed" <?php selected( $current_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'swpmail' ); ?></option>
			</select>
			<select name="provider">
				<option value=""><?php esc_html_e( 'All Providers', 'swpmail' ); ?></option>
				<?php foreach ( $providers as $p ) : ?>
					<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $current_provider, $p ); ?>><?php echo esc_html( $p ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'swpmail' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Process bulk/single delete actions.
	 */
	private function process_bulk_action(): void {
		if ( 'delete' !== $this->current_action() ) {
			return;
		}

		global $wpdb;
		$queue_table    = $wpdb->prefix . 'swpm_queue';
		$tracking_table = $wpdb->prefix . 'swpm_tracking';

		// Single delete.
		if ( isset( $_REQUEST['id'] ) ) {
			$id = absint( $_REQUEST['id'] );
			check_admin_referer( 'swpm_delete_log_' . $id );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $tracking_table, array( 'queue_id' => $id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $queue_table, array( 'id' => $id ), array( '%d' ) );
			return;
		}

		// Bulk delete.
		if ( ! empty( $_REQUEST['log_ids'] ) && is_array( $_REQUEST['log_ids'] ) ) {
			check_admin_referer( 'bulk-' . $this->_args['plural'] );
			$ids = array_map( 'absint', $_REQUEST['log_ids'] );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$tracking_table} WHERE queue_id IN ({$placeholders})", $ids ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$queue_table} WHERE id IN ({$placeholders})", $ids ) );
		}
	}

	/**
	 * No items message.
	 */
	public function no_items(): void {
		esc_html_e( 'No email logs found.', 'swpmail' );
	}

	/* ------------------------------------------------------------------
	 * AJAX: Tracking Detail for a Queue Item
	 * ----------------------------------------------------------------*/

	/**
	 * Return tracking events for a specific queue item.
	 */
	public static function ajax_tracking_detail(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$queue_id = absint( $_POST['queue_id'] ?? 0 );
		if ( 0 === $queue_id ) {
			wp_send_json_error( array( 'message' => 'Invalid ID' ), 400 );
		}

		global $wpdb;
		$queue_table    = $wpdb->prefix . 'swpm_queue';
		$tracking_table = $wpdb->prefix . 'swpm_tracking';

		// Get queue item.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$queue_item = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, to_email, subject, status, provider_used, provider_msg_id, attempts, max_attempts, error_message, error_code, scheduled_at, sent_at, created_at FROM {$queue_table} WHERE id = %d", $queue_id ),
			ARRAY_A
		);

		if ( ! $queue_item ) {
			wp_send_json_error( array( 'message' => 'Not found' ), 404 );
		}

		// Get tracking events.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, url, ip_address, user_agent, created_at
				 FROM {$tracking_table}
				 WHERE queue_id = %d
				 ORDER BY created_at DESC",
				$queue_id
			),
			ARRAY_A
		);

		// Aggregate per-link clicks.
		$link_clicks = array();
		foreach ( $events as $e ) {
			if ( 'click' === $e['event_type'] && ! empty( $e['url'] ) ) {
				if ( ! isset( $link_clicks[ $e['url'] ] ) ) {
					$link_clicks[ $e['url'] ] = 0;
				}
				++$link_clicks[ $e['url'] ];
			}
		}
		arsort( $link_clicks );

		// Summary counts.
		$open_count  = 0;
		$click_count = 0;
		foreach ( $events as $e ) {
			if ( 'open' === $e['event_type'] ) {
				++$open_count;
			} else {
				++$click_count;
			}
		}

		wp_send_json_success( array(
			'queue'       => $queue_item,
			'events'      => $events,
			'link_clicks' => $link_clicks,
			'open_count'  => $open_count,
			'click_count' => $click_count,
		) );
	}
}
