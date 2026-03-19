<?php
/**
 * Email Logs page — Tabbed UX redesign.
 *
 * Tabs:
 *  1 — Email Log  : list table
 *  2 — Purge Logs : retention cleanup tool
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$list_table = new SWPM_Logs_List_Table();
$list_table->prepare_items();

global $wpdb;
$queue_table    = $wpdb->prefix . 'swpm_queue';
$tracking_table = $wpdb->prefix . 'swpm_tracking';

// Summary stats (single grouped query).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$status_stats_raw = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$queue_table} GROUP BY status" );
$status_stats = array( 'pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0 );
foreach ( $status_stats_raw as $row ) {
	$status_stats[ $row->status ] = (int) $row->cnt;
}
$total_emails = array_sum( $status_stats );

// Tracking totals.
$analytics     = swpm( 'analytics' );
$track_summary = ( $analytics instanceof SWPM_Analytics ) ? $analytics->get_summary() : array();
$total_opens   = $track_summary['total_opens'] ?? 0;
$total_clicks  = $track_summary['total_clicks'] ?? 0;
?>

<div class="swpm-wrap">

	<!-- Page Header -->
	<div class="swpm-page-header">
		<div>
			<h1><span class="dashicons dashicons-email-alt2"></span> <?php esc_html_e( 'Email Logs', 'swpmail' ); ?></h1>
			<p class="swpm-page-subtitle"><?php esc_html_e( 'View sent emails, delivery status, and per-email open/click tracking.', 'swpmail' ); ?></p>
		</div>
	</div>

	<!-- Stats Grid -->
	<div class="swpm-stats-grid">
		<div class="swpm-stat-card swpm-stat-card--primary">
			<div class="swpm-stat-card__icon"><span class="dashicons dashicons-email-alt"></span></div>
			<div class="swpm-stat-card__label"><?php esc_html_e( 'Total Emails', 'swpmail' ); ?></div>
			<div class="swpm-stat-card__value"><?php echo esc_html( number_format_i18n( $total_emails ) ); ?></div>
		</div>
		<div class="swpm-stat-card swpm-stat-card--success">
			<div class="swpm-stat-card__icon"><span class="dashicons dashicons-yes-alt"></span></div>
			<div class="swpm-stat-card__label"><?php esc_html_e( 'Delivered', 'swpmail' ); ?></div>
			<div class="swpm-stat-card__value"><?php echo esc_html( number_format_i18n( $status_stats['sent'] ) ); ?></div>
		</div>
		<div class="swpm-stat-card swpm-stat-card--danger">
			<div class="swpm-stat-card__icon"><span class="dashicons dashicons-dismiss"></span></div>
			<div class="swpm-stat-card__label"><?php esc_html_e( 'Failed', 'swpmail' ); ?></div>
			<div class="swpm-stat-card__value"><?php echo esc_html( number_format_i18n( $status_stats['failed'] ) ); ?></div>
		</div>
		<div class="swpm-stat-card swpm-stat-card--warning">
			<div class="swpm-stat-card__icon"><span class="dashicons dashicons-visibility"></span></div>
			<div class="swpm-stat-card__label"><?php esc_html_e( 'Opens / Clicks', 'swpmail' ); ?></div>
			<div class="swpm-stat-card__value" style="font-size: 20px;">
				<?php echo esc_html( number_format_i18n( $total_opens ) ); ?> / <?php echo esc_html( number_format_i18n( $total_clicks ) ); ?>
			</div>
		</div>
	</div>

	<!-- Tabs -->
	<div class="swpm-ms-tabs" role="tablist">
		<button class="swpm-ms-tab active" role="tab" aria-selected="true"  data-tab="logs-table"  type="button">
			<span class="dashicons dashicons-list-view"></span>
			<?php esc_html_e( 'Email Log', 'swpmail' ); ?>
		</button>
		<button class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="logs-purge"  type="button">
			<span class="dashicons dashicons-trash"></span>
			<?php esc_html_e( 'Purge Logs', 'swpmail' ); ?>
		</button>
	</div>

	<!-- ═══════════════ TAB 1 — EMAIL LOG ═══════════════ -->
	<div class="swpm-ms-panel" id="swpm-tab-logs-table" role="tabpanel">
		<div class="swpm-card">
			<div class="swpm-card-header">
				<h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'All Emails', 'swpmail' ); ?></h2>
			</div>
			<div class="swpm-card-body" style="padding: 0;">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="swpmail-logs" />
					<?php
					$list_table->search_box( __( 'Search', 'swpmail' ), 'swpm-log-search' );
					$list_table->display();
					?>
				</form>
			</div>
		</div>
	</div>

	<!-- ═══════════════ TAB 2 — PURGE LOGS ═══════════════ -->
	<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-logs-purge" role="tabpanel">
		<div class="swpm-card">

			<div class="swpm-ms-config-notice" style="margin-bottom: 24px;">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Purging logs is permanent and cannot be undone. This removes queue records and tracking data older than the selected period.', 'swpmail' ); ?>
			</div>

			<div class="swpm-ms-field-grid">
				<div class="swpm-ms-field">
					<label for="swpm-purge-age"><?php esc_html_e( 'Delete logs older than', 'swpmail' ); ?></label>
					<select id="swpm-purge-age">
						<option value="30"><?php esc_html_e( '30 days', 'swpmail' ); ?></option>
						<option value="60"><?php esc_html_e( '60 days', 'swpmail' ); ?></option>
						<option value="90" selected><?php esc_html_e( '90 days', 'swpmail' ); ?></option>
						<option value="180"><?php esc_html_e( '6 months', 'swpmail' ); ?></option>
						<option value="365"><?php esc_html_e( '1 year', 'swpmail' ); ?></option>
					</select>
				</div>
			</div>

			<div class="swpm-ms-save-bar">
				<span id="swpm-purge-result"></span>
				<button type="button" id="swpm-purge-logs" class="swpm-btn swpm-btn--danger">
					<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Purge Logs', 'swpmail' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>

<!-- Tracking Detail Modal -->
<div id="swpm-log-detail-modal" class="swpm-modal" style="display:none;">
	<div class="swpm-modal-overlay"></div>
	<div class="swpm-modal-content">
		<div class="swpm-modal-header">
			<h3><span class="dashicons dashicons-email-alt2"></span> <?php esc_html_e( 'Email Details', 'swpmail' ); ?></h3>
			<button type="button" class="swpm-modal-close">&times;</button>
		</div>
		<div class="swpm-modal-body">
			<div id="swpm-log-detail-loading" class="swpm-modal-loading">
				<span class="spinner is-active"></span>
			</div>
			<div id="swpm-log-detail-content" style="display:none;">
				<div class="swpm-detail-meta">
					<div class="swpm-detail-row"><strong><?php esc_html_e( 'Recipient:', 'swpmail' ); ?></strong><span id="swpm-detail-to"></span></div>
					<div class="swpm-detail-row"><strong><?php esc_html_e( 'Subject:', 'swpmail' ); ?></strong><span id="swpm-detail-subject"></span></div>
					<div class="swpm-detail-row"><strong><?php esc_html_e( 'Status:', 'swpmail' ); ?></strong><span id="swpm-detail-status"></span></div>
					<div class="swpm-detail-row"><strong><?php esc_html_e( 'Provider:', 'swpmail' ); ?></strong><span id="swpm-detail-provider"></span></div>
					<div class="swpm-detail-row"><strong><?php esc_html_e( 'Sent:', 'swpmail' ); ?></strong><span id="swpm-detail-sent-at"></span></div>
					<div class="swpm-detail-row" id="swpm-detail-error-row" style="display:none;">
						<strong><?php esc_html_e( 'Error:', 'swpmail' ); ?></strong>
						<span id="swpm-detail-error" class="swpm-text-danger"></span>
					</div>
				</div>
				<div class="swpm-detail-tracking-summary">
					<div class="swpm-detail-stat">
						<span class="swpm-detail-stat-value" id="swpm-detail-opens">0</span>
						<span class="swpm-detail-stat-label"><?php esc_html_e( 'Opens', 'swpmail' ); ?></span>
					</div>
					<div class="swpm-detail-stat">
						<span class="swpm-detail-stat-value" id="swpm-detail-clicks">0</span>
						<span class="swpm-detail-stat-label"><?php esc_html_e( 'Clicks', 'swpmail' ); ?></span>
					</div>
					<div class="swpm-detail-stat">
						<span class="swpm-detail-stat-value" id="swpm-detail-links">0</span>
						<span class="swpm-detail-stat-label"><?php esc_html_e( 'Unique Links', 'swpmail' ); ?></span>
					</div>
				</div>
				<div id="swpm-detail-links-section" style="display:none;">
					<h4><?php esc_html_e( 'Clicked Links', 'swpmail' ); ?></h4>
					<table class="swpm-detail-links-table">
						<thead><tr><th><?php esc_html_e( 'URL', 'swpmail' ); ?></th><th><?php esc_html_e( 'Clicks', 'swpmail' ); ?></th></tr></thead>
						<tbody id="swpm-detail-links-body"></tbody>
					</table>
				</div>
				<div id="swpm-detail-events-section" style="display:none;">
					<h4><?php esc_html_e( 'Activity Timeline', 'swpmail' ); ?></h4>
					<div id="swpm-detail-events-list" class="swpm-timeline"></div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
(function() {
	var tabs   = document.querySelectorAll('.swpm-ms-tab');
	var panels = document.querySelectorAll('.swpm-ms-panel');
	tabs.forEach(function(tab) {
		tab.addEventListener('click', function() {
			var target = this.dataset.tab;
			tabs.forEach(function(t) { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
			panels.forEach(function(p) { p.classList.add('swpm-ms-panel--hidden'); });
			this.classList.add('active');
			this.setAttribute('aria-selected', 'true');
			var panel = document.getElementById('swpm-tab-' + target);
			if (panel) { panel.classList.remove('swpm-ms-panel--hidden'); }
		});
	});
})();
</script>
