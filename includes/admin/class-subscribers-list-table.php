<?php
/**
 * Subscribers WP_List_Table.
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

class SWPM_Subscribers_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'Subscriber', 'swpmail' ),
			'plural'   => __( 'Subscribers', 'swpmail' ),
			'ajax'     => false,
		) );
	}

	/**
	 * Define table columns.
	 */
	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox" />',
			'email'      => __( 'Email', 'swpmail' ),
			'name'       => __( 'Name', 'swpmail' ),
			'status'     => __( 'Status', 'swpmail' ),
			'frequency'  => __( 'Frequency', 'swpmail' ),
			'created_at' => __( 'Subscribed', 'swpmail' ),
		);
	}

	/**
	 * Sortable columns.
	 */
	public function get_sortable_columns(): array {
		return array(
			'email'      => array( 'email', false ),
			'status'     => array( 'status', false ),
			'frequency'  => array( 'frequency', false ),
			'created_at' => array( 'created_at', true ),
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
		return sprintf( '<input type="checkbox" name="subscriber_ids[]" value="%d" />', absint( $item->id ) );
	}

	/**
	 * Email column with row actions.
	 */
	public function column_email( $item ): string {
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'swpmail-subscribers',
					'action' => 'delete',
					'id'     => absint( $item->id ),
				),
				admin_url( 'admin.php' )
			),
			'swpm_delete_subscriber_' . $item->id
		);

		$actions = array(
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Are you sure you want to delete this subscriber?', 'swpmail' ) ),
				esc_html__( 'Delete', 'swpmail' )
			),
		);

		return sprintf( '%s %s', esc_html( $item->email ), $this->row_actions( $actions ) );
	}

	/**
	 * Name column.
	 */
	public function column_name( $item ): string {
		return esc_html( $item->name ?: '—' );
	}

	/**
	 * Status column with badge.
	 */
	public function column_status( $item ): string {
		$labels = array(
			'pending'      => __( 'Pending', 'swpmail' ),
			'confirmed'    => __( 'Confirmed', 'swpmail' ),
			'unsubscribed' => __( 'Unsubscribed', 'swpmail' ),
			'bounced'      => __( 'Bounced', 'swpmail' ),
		);
		$label = $labels[ $item->status ] ?? $item->status;

		return sprintf( '<span class="swpm-status swpm-status--%s">%s</span>', esc_attr( $item->status ), esc_html( $label ) );
	}

	/**
	 * Frequency column.
	 */
	public function column_frequency( $item ): string {
		$labels = array(
			'instant' => __( 'Instant', 'swpmail' ),
			'daily'   => __( 'Daily', 'swpmail' ),
			'weekly'  => __( 'Weekly', 'swpmail' ),
		);
		return esc_html( $labels[ $item->frequency ] ?? $item->frequency );
	}

	/**
	 * Date column.
	 */
	public function column_created_at( $item ): string {
		return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->created_at ) ) );
	}

	/**
	 * Fetch table data.
	 */
	public function prepare_items(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'swpm_subscribers';

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

		// Nonce verification for all list table requests (unconditional).
		if ( isset( $_REQUEST['_wpnonce'] ) || isset( $_REQUEST['s'] ) || isset( $_REQUEST['status'] ) || isset( $_REQUEST['filter_action'] ) || isset( $_REQUEST['orderby'] ) || isset( $_REQUEST['paged'] ) ) {
			if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'swpm_subscribers_filter' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'swpmail' ), 403 );
			}
		} elseif ( 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			// First page load without params — verify admin context via referer.
			check_admin_referer( -1, false );
		}

		// Search.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$where  = '';
		$params = array();

		if ( '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where  = 'WHERE email LIKE %s OR name LIKE %s';
			$params = array( $like, $like );
		}

		// Status filter.
		$status_filter = isset( $_REQUEST['status'] ) ? sanitize_key( $_REQUEST['status'] ) : '';
		if ( in_array( $status_filter, array( 'pending', 'confirmed', 'unsubscribed', 'bounced' ), true ) ) {
			$where .= empty( $where ) ? 'WHERE status = %s' : ' AND status = %s';
			$params[] = $status_filter;
		}

		// Order.
		$allowed_orderby = array( 'email', 'status', 'frequency', 'created_at' );
		$orderby         = isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], $allowed_orderby, true )
			? sanitize_sql_orderby( $_REQUEST['orderby'] . ' ASC' )
			: 'created_at';
		// sanitize_sql_orderby returns false on failure.
		if ( false === $orderby ) {
			$orderby = 'created_at';
		} else {
			// Just take the column name part.
			$orderby = explode( ' ', $orderby )[0];
		}

		$order = isset( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ? 'ASC' : 'DESC';

		// Total items.
		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $params ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $table ) );
		}

		// Query.
		$sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
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
	 * Extra table nav: status filter.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$current = isset( $_REQUEST['status'] ) ? sanitize_key( $_REQUEST['status'] ) : '';

		?>
		<div class="alignleft actions">
			<?php wp_nonce_field( 'swpm_subscribers_filter', '_wpnonce', false ); ?>
			<select name="status">
				<option value=""><?php esc_html_e( 'All Statuses', 'swpmail' ); ?></option>
				<option value="pending" <?php selected( $current, 'pending' ); ?>><?php esc_html_e( 'Pending', 'swpmail' ); ?></option>
				<option value="confirmed" <?php selected( $current, 'confirmed' ); ?>><?php esc_html_e( 'Confirmed', 'swpmail' ); ?></option>
				<option value="unsubscribed" <?php selected( $current, 'unsubscribed' ); ?>><?php esc_html_e( 'Unsubscribed', 'swpmail' ); ?></option>
				<option value="bounced" <?php selected( $current, 'bounced' ); ?>><?php esc_html_e( 'Bounced', 'swpmail' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter', 'swpmail' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Process bulk actions.
	 */
	private function process_bulk_action(): void {
		if ( 'delete' !== $this->current_action() ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'swpm_subscribers';

		// Single delete.
		if ( isset( $_REQUEST['id'] ) ) {
			$id = absint( $_REQUEST['id'] );
			check_admin_referer( 'swpm_delete_subscriber_' . $id );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
			return;
		}

		// Bulk delete.
		if ( ! empty( $_REQUEST['subscriber_ids'] ) && is_array( $_REQUEST['subscriber_ids'] ) ) {
			check_admin_referer( 'bulk-' . $this->_args['plural'] );
			$ids = array_map( 'absint', $_REQUEST['subscriber_ids'] );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) );
		}
	}

	/**
	 * No items message.
	 */
	public function no_items(): void {
		esc_html_e( 'No subscribers found.', 'swpmail' );
	}
}
