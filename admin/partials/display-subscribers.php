<?php
/**
 * Subscribers list page.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$list_table = new SWPM_Subscribers_List_Table();
$list_table->prepare_items();

global $wpdb;
$sub_table = $wpdb->prefix . 'swpm_subscribers';

// Single GROUP BY query instead of 4 separate COUNT queries.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$status_raw = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$sub_table} GROUP BY status" );
$sub_stats  = array(
	'confirmed'    => 0,
	'pending'      => 0,
	'unsubscribed' => 0,
	'bounced'      => 0,
);
foreach ( $status_raw as $row ) {
	$sub_stats[ $row->status ] = (int) $row->cnt;
}
$total         = array_sum( $sub_stats );
$confirmed     = $sub_stats['confirmed'];
$pending_count = $sub_stats['pending'];
$unsubscribed  = $sub_stats['unsubscribed'];
$bounced       = $sub_stats['bounced'];
?>

<div class="swpm-wrap">

	<!-- Page Header -->
	<div class="swpm-page-header">
		<div>
			<h1><span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Subscribers', 'swpmail' ); ?></h1>
			<p class="swpm-page-subtitle"><?php esc_html_e( 'Manage your email subscribers and their subscription preferences.', 'swpmail' ); ?></p>
		</div>
	</div>

	<!-- How to Add Subscribe Form -->
	<div class="swpm-info-box">
		<h3><span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'How to Collect Subscribers', 'swpmail' ); ?></h3>
		<p>
			<strong><?php esc_html_e( 'Shortcode (pages, posts, widgets):', 'swpmail' ); ?></strong><br>
			<code>[swpmail_subscribe]</code>
		</p>
		<p>
			<strong><?php esc_html_e( 'PHP (theme templates, header, footer, sidebar):', 'swpmail' ); ?></strong><br>
			<code>&lt;?php echo do_shortcode( '[swpmail_subscribe]' ); ?&gt;</code>
		</p>
		<p>
			<strong><?php esc_html_e( 'Block Editor:', 'swpmail' ); ?></strong>
			<?php esc_html_e( 'Add a "Shortcode" block and paste', 'swpmail' ); ?> <code>[swpmail_subscribe]</code>
		</p>
		<p style="margin-top: 4px;">
			<?php
			printf(
				/* translators: %s: link to settings */
				esc_html__( 'Customize the form title, double opt-in, and frequency options in %s.', 'swpmail' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=swpmail-settings' ) ) . '">' . esc_html__( 'Settings', 'swpmail' ) . '</a>'
			);
			?>
		</p>
	</div>

	<!-- Summary Stats -->
	<div class="swpm-stats-grid" style="margin-bottom: 20px;">
		<div class="swpm-stat-card swpm-stat-card--primary">
			<div class="swpm-stat-card__icon"><span class="dashicons dashicons-groups"></span></div>
			<div class="swpm-stat-card__label"><?php esc_html_e( 'Total', 'swpmail' ); ?></div>
			<div class="swpm-stat-card__value"><?php echo esc_html( number_format_i18n( $total ) ); ?></div>
		</div>
		<div class="swpm-stat-card swpm-stat-card--success">
			<div class="swpm-stat-card__icon"><span class="dashicons dashicons-yes-alt"></span></div>
			<div class="swpm-stat-card__label"><?php esc_html_e( 'Confirmed', 'swpmail' ); ?></div>
			<div class="swpm-stat-card__value"><?php echo esc_html( number_format_i18n( $confirmed ) ); ?></div>
		</div>
		<div class="swpm-stat-card swpm-stat-card--warning">
			<div class="swpm-stat-card__icon"><span class="dashicons dashicons-clock"></span></div>
			<div class="swpm-stat-card__label"><?php esc_html_e( 'Pending', 'swpmail' ); ?></div>
			<div class="swpm-stat-card__value"><?php echo esc_html( number_format_i18n( $pending_count ) ); ?></div>
		</div>
		<div class="swpm-stat-card swpm-stat-card--danger">
			<div class="swpm-stat-card__icon"><span class="dashicons dashicons-dismiss"></span></div>
			<div class="swpm-stat-card__label"><?php esc_html_e( 'Unsubscribed', 'swpmail' ); ?></div>
			<div class="swpm-stat-card__value"><?php echo esc_html( number_format_i18n( $unsubscribed ) ); ?></div>
		</div>
		<div class="swpm-stat-card" style="border-top: 3px solid #8c8f94;">
			<div class="swpm-stat-card__icon"><span class="dashicons dashicons-migrate"></span></div>
			<div class="swpm-stat-card__label"><?php esc_html_e( 'Bounced', 'swpmail' ); ?></div>
			<div class="swpm-stat-card__value"><?php echo esc_html( number_format_i18n( $bounced ) ); ?></div>
		</div>
	</div>

	<!-- Subscribers Table -->
	<div class="swpm-card">
		<div class="swpm-card-header" style="display: flex; justify-content: space-between; align-items: center;">
			<h2><?php esc_html_e( 'All Subscribers', 'swpmail' ); ?></h2>
			<button type="button" id="swpm-export-subscribers" class="swpm-btn swpm-btn--secondary swpm-btn--sm">
				<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export CSV', 'swpmail' ); ?>
			</button>
		</div>
		<form method="get">
			<input type="hidden" name="page" value="swpmail-subscribers" />
			<?php
			$list_table->search_box( __( 'Search Subscribers', 'swpmail' ), 'swpm-subscriber-search' );
			$list_table->display();
			?>
		</form>
	</div>
</div>
