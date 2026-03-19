<?php
/**
 * Dashboard page.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Fetch all dashboard data via the data-layer class (MVC separation).
$dashboard_data = swpm( 'dashboard_data' );
if ( ! $dashboard_data instanceof SWPM_Dashboard_Data ) {
	$dashboard_data = new SWPM_Dashboard_Data();
}
$data = $dashboard_data->get_all();

// Unpack for template usage.
$sub_stats         = $data['subscribers'];
$total_subscribers = $sub_stats['total'];
$confirmed         = $sub_stats['confirmed'];
$pending           = $sub_stats['pending'];
$unsubscribed      = $sub_stats['unsubscribed'];

$queue_stats   = $data['queue'];
$queue_pending = $queue_stats['pending'];
$queue_sent    = $queue_stats['sent'];
$queue_failed  = $queue_stats['failed'];

$provider_info  = $data['provider'];
$provider_key   = $provider_info['key'];
$provider_label = $provider_info['label'];

$last_run_text = $data['last_run_text'];
$recent_logs   = $data['recent_logs'];

$tracking_stats  = $data['tracking']['stats'];
$tracking_trend  = $data['tracking']['trend'];
$top_links       = $data['tracking']['top_links'];
$t_opens         = $tracking_stats['total_opens'] ?? 0;
$t_unique_opens  = $tracking_stats['unique_opens'] ?? 0;
$t_clicks        = $tracking_stats['total_clicks'] ?? 0;
$t_unique_clicks = $tracking_stats['unique_clicks'] ?? 0;
$t_open_rate     = $tracking_stats['open_rate'] ?? 0;
$t_click_rate    = $tracking_stats['click_rate'] ?? 0;

$failover_info   = $data['failover'];
$backup_key      = $failover_info['backup_key'];
$failover_status = $failover_info['status'];

$automation      = $data['automation'];
$trigger_count   = $automation['trigger_count'];
$routing_enabled = $automation['routing_enabled'];
$routing_count   = $automation['routing_count'];
$routing_rules   = ( swpm( 'router' ) instanceof SWPM_Router ) ? swpm( 'router' )->get_rules() : array();
$active_triggers = (array) get_option( 'swpm_active_triggers', array() );
$connections     = swpm( 'connections' );
?>

<div class="swpm-wrap">

	<!-- Page Header -->
	<div class="swpm-page-header">
		<div>
			<h1><span class="dashicons dashicons-email"></span> <?php esc_html_e( 'SWPMail Dashboard', 'swpmail' ); ?></h1>
			<p class="swpm-page-subtitle"><?php esc_html_e( 'Overview of your email subscription system.', 'swpmail' ); ?></p>
		</div>
	</div>

	<!-- Quick Start Guide -->
	<div class="swpm-info-box">
		<h3><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Quick Start', 'swpmail' ); ?></h3>
		<p>
			<?php
			printf(
				/* translators: %s: shortcode */
				esc_html__( 'Add the subscribe form to any page or post with the shortcode: %s', 'swpmail' ),
				'<code>[swpmail_subscribe]</code>'
			);
			?>
		</p>
		<p>
			<?php
			printf(
				/* translators: 1: opening PHP tag, 2: function call, 3: closing PHP tag */
				esc_html__( 'Or use in your theme template: %1$s%2$s%3$s', 'swpmail' ),
				'<code>&lt;?php ',
				'echo do_shortcode( \'[swpmail_subscribe]\' );',
				' ?&gt;</code>'
			);
			?>
		</p>
		<p style="margin-top: 4px;">
			<?php
			printf(
				/* translators: %s: link to settings */
				esc_html__( 'Configure your mail provider in %s to start sending emails.', 'swpmail' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=swpmail-mail-settings' ) ) . '">' . esc_html__( 'Mail Settings', 'swpmail' ) . '</a>'
			);
			?>
		</p>
	</div>

	<!-- Stats Grid -->
	<div class="swpm-stats-grid">
		<div class="swpm-stat-card swpm-stat-card--primary">
			<div class="swpm-stat-card__icon"><span class="dashicons dashicons-groups"></span></div>
			<div class="swpm-stat-card__label"><?php esc_html_e( 'Total Subscribers', 'swpmail' ); ?></div>
			<div class="swpm-stat-card__value"><?php echo esc_html( number_format_i18n( $total_subscribers ) ); ?></div>
			<div class="swpm-stat-card__meta">
				<?php
				printf(
					/* translators: 1: confirmed, 2: pending */
					esc_html__( '%1$s confirmed, %2$s pending', 'swpmail' ),
					'<strong>' . esc_html( number_format_i18n( $confirmed ) ) . '</strong>',
					'<strong>' . esc_html( number_format_i18n( $pending ) ) . '</strong>'
				);
				?>
			</div>
		</div>

		<div class="swpm-stat-card swpm-stat-card--success">
			<div class="swpm-stat-card__icon"><span class="dashicons dashicons-email-alt"></span></div>
			<div class="swpm-stat-card__label"><?php esc_html_e( 'Emails Sent', 'swpmail' ); ?></div>
			<div class="swpm-stat-card__value"><?php echo esc_html( number_format_i18n( $queue_sent ) ); ?></div>
			<div class="swpm-stat-card__meta">
				<?php
				printf(
					/* translators: %s: pending count */
					esc_html__( '%s in queue', 'swpmail' ),
					'<strong>' . esc_html( number_format_i18n( $queue_pending ) ) . '</strong>'
				);
				?>
			</div>
		</div>

		<div class="swpm-stat-card swpm-stat-card--warning">
			<div class="swpm-stat-card__icon"><span class="dashicons dashicons-cloud"></span></div>
			<div class="swpm-stat-card__label"><?php esc_html_e( 'Mail Provider', 'swpmail' ); ?></div>
			<div class="swpm-stat-card__value" style="font-size: 20px;"><?php echo esc_html( $provider_label ); ?></div>
			<div class="swpm-stat-card__meta">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=swpmail-mail-settings' ) ); ?>">
					<?php esc_html_e( 'Change provider', 'swpmail' ); ?> &rarr;
				</a>
			</div>
		</div>

		<div class="swpm-stat-card swpm-stat-card--danger">
			<div class="swpm-stat-card__icon"><span class="dashicons dashicons-warning"></span></div>
			<div class="swpm-stat-card__label"><?php esc_html_e( 'Failed', 'swpmail' ); ?></div>
			<div class="swpm-stat-card__value"><?php echo esc_html( number_format_i18n( $queue_failed ) ); ?></div>
			<div class="swpm-stat-card__meta">
				<?php
				printf(
					/* translators: %s: last cron run time */
					esc_html__( 'Last cron: %s', 'swpmail' ),
					esc_html( $last_run_text )
				);
				?>
			</div>
		</div>
	</div>

	<!-- Quick Actions -->
	<div class="swpm-quick-actions">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=swpmail-subscribers' ) ); ?>" class="swpm-quick-action">
			<span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'View Subscribers', 'swpmail' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=swpmail-templates' ) ); ?>" class="swpm-quick-action">
			<span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Edit Templates', 'swpmail' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=swpmail-triggers' ) ); ?>" class="swpm-quick-action">
			<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Manage Triggers', 'swpmail' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=swpmail-mail-settings' ) ); ?>" class="swpm-quick-action">
			<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Mail Settings', 'swpmail' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=swpmail-logs' ) ); ?>" class="swpm-quick-action">
			<span class="dashicons dashicons-email-alt2"></span> <?php esc_html_e( 'Email Logs', 'swpmail' ); ?>
		</a>
	</div>

	<!-- System Status Grid -->
	<div class="swpm-stats-grid swpm-stats-grid--3">
		<!-- Failover / Backup Provider -->
		<div class="swpm-card">
			<div class="swpm-card-header">
				<h2><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Failover Status', 'swpmail' ); ?></h2>
			</div>
			<div class="swpm-card-body">
				<?php if ( $failover_status ) : ?>
					<div class="swpm-failover-row">
						<span class="swpm-failover-label"><?php esc_html_e( 'Primary', 'swpmail' ); ?></span>
						<span class="swpm-badge swpm-badge--<?php echo esc_attr( ( $failover_status['primary_healthy'] ?? true ) ? 'success' : 'danger' ); ?>">
							<?php echo esc_html( $provider_label ); ?>
						</span>
					</div>
					<?php if ( ! empty( $backup_key ) ) : ?>
						<div class="swpm-failover-row">
							<span class="swpm-failover-label"><?php esc_html_e( 'Backup', 'swpmail' ); ?></span>
							<span class="swpm-badge swpm-badge--<?php echo esc_attr( ( $failover_status['backup_healthy'] ?? true ) ? 'success' : 'warning' ); ?>">
								<?php echo esc_html( $failover_status['backup_label'] ?? ucfirst( $backup_key ) ); ?>
							</span>
						</div>
					<?php else : ?>
						<p class="swpm-muted"><?php esc_html_e( 'No backup provider configured.', 'swpmail' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=swpmail-mail-settings#swpm-failover' ) ); ?>" class="swpm-btn swpm-btn--secondary swpm-btn--sm">
							<?php esc_html_e( 'Set up failover', 'swpmail' ); ?> &rarr;
						</a>
					<?php endif; ?>
				<?php else : ?>
					<p class="swpm-muted"><?php esc_html_e( 'Connection manager not available.', 'swpmail' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<!-- Active Triggers -->
		<div class="swpm-card">
			<div class="swpm-card-header">
				<h2><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Active Triggers', 'swpmail' ); ?></h2>
			</div>
			<div class="swpm-card-body">
				<div class="swpm-stat-card__value"><?php echo esc_html( number_format_i18n( $trigger_count ) ); ?></div>
				<?php if ( $trigger_count > 0 ) : ?>
					<p class="swpm-muted">
						<?php esc_html_e( 'Automated email triggers are running.', 'swpmail' ); ?>
					</p>
				<?php else : ?>
					<p class="swpm-muted"><?php esc_html_e( 'No active triggers configured.', 'swpmail' ); ?></p>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=swpmail-triggers' ) ); ?>" class="swpm-btn swpm-btn--secondary swpm-btn--sm">
					<?php esc_html_e( 'Manage Triggers', 'swpmail' ); ?> &rarr;
				</a>
			</div>
		</div>

		<!-- Smart Routing -->
		<div class="swpm-card">
			<div class="swpm-card-header">
				<h2><span class="dashicons dashicons-randomize"></span> <?php esc_html_e( 'Smart Routing', 'swpmail' ); ?></h2>
			</div>
			<div class="swpm-card-body">
				<span class="swpm-badge swpm-badge--<?php echo $routing_enabled ? 'success' : 'secondary'; ?>">
					<?php echo $routing_enabled ? esc_html__( 'Enabled', 'swpmail' ) : esc_html__( 'Disabled', 'swpmail' ); ?>
				</span>
				<?php if ( $routing_enabled && $routing_count > 0 ) : ?>
					<p class="swpm-muted">
						<?php
						printf(
							/* translators: %s: number of routing rules */
							esc_html( _n( '%s routing rule active.', '%s routing rules active.', $routing_count, 'swpmail' ) ),
							esc_html( number_format_i18n( $routing_count ) )
						);
						?>
					</p>
				<?php elseif ( $routing_enabled ) : ?>
					<p class="swpm-muted"><?php esc_html_e( 'No routing rules defined yet.', 'swpmail' ); ?></p>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=swpmail-routing' ) ); ?>" class="swpm-btn swpm-btn--secondary swpm-btn--sm">
					<?php esc_html_e( 'Manage Routing', 'swpmail' ); ?> &rarr;
				</a>
			</div>
		</div>
	</div>

	<!-- Email Tracking Stats -->
	<div class="swpm-card">
		<div class="swpm-card-header">
			<h2><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'Email Tracking (Last 30 Days)', 'swpmail' ); ?></h2>
		</div>
		<div class="swpm-tracking-stats-grid">
			<div class="swpm-tracking-stat">
				<span class="swpm-tracking-stat__icon dashicons dashicons-visibility"></span>
				<div class="swpm-tracking-stat__value"><?php echo esc_html( number_format_i18n( $t_opens ) ); ?></div>
				<div class="swpm-tracking-stat__label"><?php esc_html_e( 'Total Opens', 'swpmail' ); ?></div>
				<div class="swpm-tracking-stat__meta">
					<?php
					printf(
						/* translators: %s: unique opens count */
						esc_html__( '%s unique', 'swpmail' ),
						'<strong>' . esc_html( number_format_i18n( $t_unique_opens ) ) . '</strong>'
					);
					?>
				</div>
			</div>
			<div class="swpm-tracking-stat">
				<span class="swpm-tracking-stat__icon dashicons dashicons-admin-links"></span>
				<div class="swpm-tracking-stat__value"><?php echo esc_html( number_format_i18n( $t_clicks ) ); ?></div>
				<div class="swpm-tracking-stat__label"><?php esc_html_e( 'Total Clicks', 'swpmail' ); ?></div>
				<div class="swpm-tracking-stat__meta">
					<?php
					printf(
						/* translators: %s: unique clicks count */
						esc_html__( '%s unique', 'swpmail' ),
						'<strong>' . esc_html( number_format_i18n( $t_unique_clicks ) ) . '</strong>'
					);
					?>
				</div>
			</div>
			<div class="swpm-tracking-stat swpm-tracking-stat--highlight">
				<span class="swpm-tracking-stat__icon dashicons dashicons-chart-bar"></span>
				<div class="swpm-tracking-stat__value"><?php echo esc_html( $t_open_rate ); ?>%</div>
				<div class="swpm-tracking-stat__label"><?php esc_html_e( 'Open Rate', 'swpmail' ); ?></div>
			</div>
			<div class="swpm-tracking-stat swpm-tracking-stat--highlight">
				<span class="swpm-tracking-stat__icon dashicons dashicons-chart-line"></span>
				<div class="swpm-tracking-stat__value"><?php echo esc_html( $t_click_rate ); ?>%</div>
				<div class="swpm-tracking-stat__label"><?php esc_html_e( 'Click Rate', 'swpmail' ); ?></div>
			</div>
		</div>

		<?php if ( ! empty( $tracking_trend ) ) : ?>
		<!-- Trend Mini Chart -->
		<div class="swpm-tracking-chart">
			<h3><?php esc_html_e( 'Daily Trend (Last 14 Days)', 'swpmail' ); ?></h3>
			<div class="swpm-tracking-bars" id="swpm-tracking-bars">
				<?php
				$max_val = 1;
				foreach ( $tracking_trend as $day ) {
					$day_total = (int) $day['opens'] + (int) $day['clicks'];
					if ( $day_total > $max_val ) {
						$max_val = $day_total;
					}
				}
				foreach ( $tracking_trend as $day ) :
					$opens_h  = $max_val > 0 ? round( ( (int) $day['opens'] / $max_val ) * 100 ) : 0;
					$clicks_h = $max_val > 0 ? round( ( (int) $day['clicks'] / $max_val ) * 100 ) : 0;
					$label    = wp_date( 'M j', strtotime( $day['day'] ) );
					?>
				<div class="swpm-tracking-bar-group" title="<?php echo esc_attr( $label . ': ' . (int) $day['opens'] . ' opens, ' . (int) $day['clicks'] . ' clicks' ); ?>">
					<div class="swpm-tracking-bar-col">
						<div class="swpm-tracking-bar swpm-tracking-bar--opens" style="height:<?php echo esc_attr( max( $opens_h, 2 ) ); ?>%"></div>
						<div class="swpm-tracking-bar swpm-tracking-bar--clicks" style="height:<?php echo esc_attr( max( $clicks_h, 2 ) ); ?>%"></div>
					</div>
					<span class="swpm-tracking-bar-label"><?php echo esc_html( $label ); ?></span>
				</div>
				<?php endforeach; ?>
			</div>
			<div class="swpm-tracking-legend">
				<span class="swpm-tracking-legend-item"><span class="swpm-tracking-dot swpm-tracking-dot--opens"></span> <?php esc_html_e( 'Opens', 'swpmail' ); ?></span>
				<span class="swpm-tracking-legend-item"><span class="swpm-tracking-dot swpm-tracking-dot--clicks"></span> <?php esc_html_e( 'Clicks', 'swpmail' ); ?></span>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $top_links ) ) : ?>
		<!-- Top Clicked Links -->
		<div class="swpm-tracking-top-links">
			<h3><?php esc_html_e( 'Top Clicked Links', 'swpmail' ); ?></h3>
			<table class="swpm-logs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'URL', 'swpmail' ); ?></th>
						<th style="width:80px;text-align:right;"><?php esc_html_e( 'Clicks', 'swpmail' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
					<?php foreach ( $top_links as $link ) : ?>
					<tr>
						<td style="word-break:break-all;font-size:13px;"><?php echo esc_html( $link['url'] ); ?></td>
						<td style="text-align:right;font-weight:600;"><?php echo esc_html( number_format_i18n( (int) $link['clicks'] ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<?php if ( empty( $tracking_trend ) && 0 === $t_opens && 0 === $t_clicks ) : ?>
		<div class="swpm-empty-state">
			<span class="dashicons dashicons-chart-area"></span>
			<p><?php esc_html_e( 'No tracking data yet. Opens and clicks will appear here once emails are sent with tracking enabled.', 'swpmail' ); ?></p>
		</div>
		<?php endif; ?>
	</div>

	<!-- Recent Logs -->
	<div class="swpm-card">
		<div class="swpm-card-header">
			<h2><?php esc_html_e( 'Recent Activity', 'swpmail' ); ?></h2>
		</div>
		<?php if ( ! empty( $recent_logs ) ) : ?>
			<table class="swpm-logs-table">
				<thead>
					<tr>
						<th style="width:140px;"><?php esc_html_e( 'Date', 'swpmail' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( 'Level', 'swpmail' ); ?></th>
						<th><?php esc_html_e( 'Message', 'swpmail' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_logs as $log ) : ?>
						<tr>
							<td style="color: var(--swpm-gray-400); font-size: 12px;"><?php echo esc_html( wp_date( 'M j, H:i', strtotime( $log->created_at ) ) ); ?></td>
							<td>
								<span class="swpm-log-level swpm-log-level--<?php echo esc_attr( $log->level ); ?>">
									<?php echo esc_html( $log->level ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $log->message ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div class="swpm-empty-state">
				<span class="dashicons dashicons-format-chat"></span>
				<p><?php esc_html_e( 'No activity yet. Logs will appear here once emails start flowing.', 'swpmail' ); ?></p>
			</div>
		<?php endif; ?>
	</div>

</div>
