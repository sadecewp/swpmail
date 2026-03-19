<?php
/**
 * Tools page — DB repair & conflict detector.
 *
 * @package SWPMail
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="swpm-wrap">

	<!-- Page Header -->
	<div class="swpm-page-header">
		<div>
			<h1><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'SWPMail Tools', 'swpmail' ); ?></h1>
			<p class="swpm-page-subtitle"><?php esc_html_e( 'Database repair, conflict detection, and system diagnostics.', 'swpmail' ); ?></p>
		</div>
	</div>

	<!-- Tab Navigation -->
	<div class="swpm-ms-tabs" role="tablist">
		<button type="button" class="swpm-ms-tab active" role="tab" aria-selected="true"  data-tab="tools-db-repair">
			<span class="dashicons dashicons-database"></span> <?php esc_html_e( 'DB Repair', 'swpmail' ); ?>
		</button>
		<button type="button" class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="tools-conflicts">
			<span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Conflict Detector', 'swpmail' ); ?>
		</button>
		<button type="button" class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="tools-system-info">
			<span class="dashicons dashicons-info"></span> <?php esc_html_e( 'System Info', 'swpmail' ); ?>
		</button>
	</div>

	<!-- DB Repair Tab -->
	<div class="swpm-ms-panel" id="swpm-tab-tools-db-repair" role="tabpanel">

		<div class="swpm-info-box">
			<h3><span class="dashicons dashicons-database"></span> <?php esc_html_e( 'Database Health Check', 'swpmail' ); ?></h3>
			<p><?php esc_html_e( 'Diagnose and repair database issues such as missing tables, orphaned records, stuck queue items, and corrupted options.', 'swpmail' ); ?></p>
		</div>

		<div class="swpm-tools-actions">
			<button type="button" id="swpm-btn-diagnose" class="swpm-btn swpm-btn--primary">
				<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Run Diagnosis', 'swpmail' ); ?>
			</button>
			<button type="button" id="swpm-btn-repair" class="swpm-btn swpm-btn--secondary" disabled>
				<span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Repair Issues', 'swpmail' ); ?>
			</button>
			<span id="swpm-db-spinner" class="spinner"></span>
		</div>

		<!-- Diagnosis Summary -->
		<div id="swpm-db-summary" class="swpm-tools-summary" style="display:none;">
			<div class="swpm-tools-summary__badge" id="swpm-db-badge"></div>
			<div class="swpm-tools-summary__stats">
				<span id="swpm-db-critical" class="swpm-badge swpm-badge--danger"></span>
				<span id="swpm-db-warning" class="swpm-badge swpm-badge--warning"></span>
				<span id="swpm-db-info" class="swpm-badge swpm-badge--info"></span>
			</div>
		</div>

		<!-- Issues Table -->
		<div id="swpm-db-issues" style="display:none;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Severity', 'swpmail' ); ?></th>
						<th><?php esc_html_e( 'Issue', 'swpmail' ); ?></th>
						<th><?php esc_html_e( 'Fixable', 'swpmail' ); ?></th>
					</tr>
				</thead>
				<tbody id="swpm-db-issues-body"></tbody>
			</table>
		</div>

		<!-- Repair Results -->
		<div id="swpm-repair-results" style="display:none;">
			<h4><?php esc_html_e( 'Repair Results', 'swpmail' ); ?></h4>
			<div id="swpm-repair-results-body"></div>
		</div>

	</div>

	<!-- Conflicts Tab -->
	<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-tools-conflicts" role="tabpanel">

		<div class="swpm-info-box">
			<h3><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Plugin & Environment Conflicts', 'swpmail' ); ?></h3>
			<p><?php esc_html_e( 'Scan for conflicting plugins, missing PHP extensions, cron issues, and WordPress configuration problems.', 'swpmail' ); ?></p>
		</div>

		<div class="swpm-tools-actions">
			<button type="button" id="swpm-btn-detect-conflicts" class="swpm-btn swpm-btn--primary">
				<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Scan for Conflicts', 'swpmail' ); ?>
			</button>
			<span id="swpm-conflict-spinner" class="spinner"></span>
		</div>

		<!-- Conflict Summary -->
		<div id="swpm-conflict-summary" class="swpm-tools-summary" style="display:none;">
			<div class="swpm-tools-summary__badge" id="swpm-conflict-badge"></div>
			<div class="swpm-tools-summary__stats">
				<span id="swpm-conflict-critical" class="swpm-badge swpm-badge--danger"></span>
				<span id="swpm-conflict-warning" class="swpm-badge swpm-badge--warning"></span>
				<span id="swpm-conflict-info" class="swpm-badge swpm-badge--info"></span>
			</div>
		</div>

		<!-- Conflicts Table -->
		<div id="swpm-conflict-issues" style="display:none;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Severity', 'swpmail' ); ?></th>
						<th><?php esc_html_e( 'Issue', 'swpmail' ); ?></th>
						<th><?php esc_html_e( 'Resolution', 'swpmail' ); ?></th>
					</tr>
				</thead>
				<tbody id="swpm-conflict-issues-body"></tbody>
			</table>
		</div>

	</div>

	<!-- System Info Tab -->
	<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-tools-system-info" role="tabpanel">

		<div class="swpm-info-box">
			<h3><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'System Information', 'swpmail' ); ?></h3>
			<p><?php esc_html_e( 'Environment details useful for troubleshooting and support requests.', 'swpmail' ); ?></p>
		</div>

		<?php
		global $wpdb, $wp_version;

		$provider_key   = get_option( 'swpm_mail_provider', 'smtp' );
		$backup_key     = get_option( 'swpm_backup_provider', '' );
		$db_version     = get_option( 'swpm_db_version', 'N/A' );
		$last_run       = get_option( 'swpm_queue_last_run', 0 );
		$last_run_text  = $last_run ? human_time_diff( (int) $last_run ) . ' ' . __( 'ago', 'swpmail' ) : __( 'Never', 'swpmail' );
		$queue_table    = $wpdb->prefix . 'swpm_queue';
		$sub_table      = $wpdb->prefix . 'swpm_subscribers';
		$log_table      = $wpdb->prefix . 'swpm_logs';
		$tracking_table = $wpdb->prefix . 'swpm_tracking';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared		$queue_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$queue_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared		$sub_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sub_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared		$log_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared		$track_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tracking_table}" );

		$info_items = array(
			__( 'Plugin Version', 'swpmail' )     => SWPM_VERSION,
			__( 'DB Version', 'swpmail' )         => $db_version,
			__( 'Active Provider', 'swpmail' )    => $provider_key,
			__( 'Backup Provider', 'swpmail' )    => $backup_key ? $backup_key : '—',
			__( 'Queue Last Run', 'swpmail' )     => $last_run_text,
			__( 'Queue Records', 'swpmail' )      => number_format_i18n( $queue_count ),
			__( 'Subscriber Records', 'swpmail' ) => number_format_i18n( $sub_count ),
			__( 'Log Records', 'swpmail' )        => number_format_i18n( $log_count ),
			__( 'Tracking Records', 'swpmail' )   => number_format_i18n( $track_count ),
			'',
			__( 'WordPress Version', 'swpmail' )  => $wp_version,
			__( 'PHP Version', 'swpmail' )        => PHP_VERSION,
			__( 'MySQL Version', 'swpmail' )      => $wpdb->db_version(),
			__( 'Server Software', 'swpmail' )    => sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ) ),
			__( 'PHP Memory Limit', 'swpmail' )   => ini_get( 'memory_limit' ),
			__( 'PHP Max Execution', 'swpmail' )  => ini_get( 'max_execution_time' ) . 's',
			__( 'WP_DEBUG', 'swpmail' )           => defined( 'WP_DEBUG' ) && WP_DEBUG ? __( 'Enabled', 'swpmail' ) : __( 'Disabled', 'swpmail' ),
			__( 'DISABLE_WP_CRON', 'swpmail' )    => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'Yes', 'swpmail' ) : __( 'No', 'swpmail' ),
			__( 'Multisite', 'swpmail' )          => is_multisite() ? __( 'Yes', 'swpmail' ) : __( 'No', 'swpmail' ),
			__( 'Active Theme', 'swpmail' )       => wp_get_theme()->get( 'Name' ),
			__( 'Loaded Extensions', 'swpmail' )  => implode( ', ', array_filter( array( 'openssl', 'mbstring', 'curl', 'json', 'intl', 'fileinfo' ), 'extension_loaded' ) ),
		);
		?>

		<table class="widefat striped swpm-system-info-table">
			<tbody>
				<?php foreach ( $info_items as $label => $value ) : ?>
					<?php if ( '' === $label ) : ?>
						<tr><td colspan="2"><hr></td></tr>
					<?php else : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td><?php echo esc_html( $value ); ?></td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div style="margin-top: 15px;">
			<p class="description">
				<span class="dashicons dashicons-editor-code" style="vertical-align: middle;"></span>
				<?php
				printf(
					/* translators: %s: WP-CLI example */
					esc_html__( 'WP-CLI: Use %s for command-line management.', 'swpmail' ),
					'<code>wp swpmail status</code>'
				);
				?>
			</p>
		</div>

		<!-- Re-run Setup Wizard -->
		<div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #dcdcde;">
			<h4><span class="dashicons dashicons-admin-generic" style="vertical-align: middle;"></span> <?php esc_html_e( 'Setup Wizard', 'swpmail' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Need to reconfigure your mail provider? Re-run the initial setup wizard.', 'swpmail' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=swpmail-setup' ) ); ?>" class="swpm-btn swpm-btn--secondary swpm-btn--sm" style="margin-top: 8px;">
				<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Re-run Setup Wizard', 'swpmail' ); ?>
			</a>
		</div>
	</div>

</div>

<script>
(function($) {
	'use strict';

	// Tab switching.
	$('.swpm-ms-tab').on('click', function() {
		var target = $(this).data('tab');
		$('.swpm-ms-tab').removeClass('active').attr('aria-selected', 'false');
		$(this).addClass('active').attr('aria-selected', 'true');
		$('.swpm-ms-panel').addClass('swpm-ms-panel--hidden');
		$('#swpm-tab-' + target).removeClass('swpm-ms-panel--hidden');
	});

	// Severity badge helper.



	/**
	 * Severitybadge.
	 */
	function severityBadge(severity) {
		var cls = 'swpm-severity-badge swpm-severity-badge--' + severity;
		return '<span class="' + cls + '">' + severity.toUpperCase() + '</span>';
	}




	/**
	 * Rendersummary.
	 */
	function renderSummary(prefix, summary, healthyLabel, issuesLabel) {
		var $badge = $('#' + prefix + '-badge');
		if (summary.healthy || summary.clean) {
			$badge.html('<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> ' + healthyLabel);
		} else {
			$badge.html('<span class="dashicons dashicons-warning" style="color:#dc3232;"></span> ' + issuesLabel);
		}
		$('#' + prefix + '-critical').text(summary.critical + ' Critical');
		$('#' + prefix + '-warning').text(summary.warning + ' Warnings');
		$('#' + prefix + '-info').text(summary.info + ' Info');
		$('#' + prefix + '-summary').show();
	}

	// ==================== DB Repair ====================

	$('#swpm-btn-diagnose').on('click', function() {
		var $btn = $(this);
		$btn.prop('disabled', true);
		$('#swpm-db-spinner').addClass('is-active');
		$('#swpm-db-issues, #swpm-repair-results').hide();
		$('#swpm-btn-repair').prop('disabled', true);

		$.post(swpmAdmin.ajaxUrl, {
			action: 'swpm_db_diagnose',
			nonce: swpmAdmin.nonce
		}, function(resp) {
			$btn.prop('disabled', false);
			$('#swpm-db-spinner').removeClass('is-active');

			if (!resp.success) {
				alert(resp.data || 'Diagnosis failed.');
				return;
			}

			var data = resp.data;
			renderSummary('swpm-db', data.summary, '<?php echo esc_js( __( 'Database is healthy', 'swpmail' ) ); ?>', '<?php echo esc_js( __( 'Issues found', 'swpmail' ) ); ?>');

			var $tbody = $('#swpm-db-issues-body').empty();
			if (data.issues.length === 0) {
				$tbody.append('<tr><td colspan="3"><?php echo esc_js( __( 'No issues found.', 'swpmail' ) ); ?></td></tr>');
			} else {
				$.each(data.issues, function(i, issue) {
					$tbody.append(
						'<tr>' +
						'<td>' + severityBadge(issue.severity) + '</td>' +
						'<td>' + $('<span>').text(issue.message).html() + '</td>' +
						'<td>' + (issue.fixable ? '<span class="dashicons dashicons-yes" style="color:#46b450;"></span>' : '<span class="dashicons dashicons-minus"></span>') + '</td>' +
						'</tr>'
					);
				});
				// Enable repair button if there are fixable issues.
				var hasFixable = data.issues.some(function(i) { return i.fixable; });
				$('#swpm-btn-repair').prop('disabled', !hasFixable);
			}
			$('#swpm-db-issues').show();
		}).fail(function() {
			$btn.prop('disabled', false);
			$('#swpm-db-spinner').removeClass('is-active');
			alert('Request failed.');
		});
	});

	$('#swpm-btn-repair').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'This will attempt to repair all fixable issues. Continue?', 'swpmail' ) ); ?>')) {
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true);
		$('#swpm-db-spinner').addClass('is-active');

		$.post(swpmAdmin.ajaxUrl, {
			action: 'swpm_db_repair',
			nonce: swpmAdmin.nonce
		}, function(resp) {
			$btn.prop('disabled', true);
			$('#swpm-db-spinner').removeClass('is-active');

			if (!resp.success) {
				alert(resp.data || 'Repair failed.');
				return;
			}

			var data = resp.data;
			var $results = $('#swpm-repair-results-body').empty();

			if (data.fixed.length > 0) {
				$results.append('<div class="notice notice-success inline"><p><strong><?php echo esc_js( __( 'Fixed:', 'swpmail' ) ); ?></strong></p><ul></ul></div>');
				var $ul = $results.find('ul');
				$.each(data.fixed, function(i, f) {
					$ul.append('<li>&#10003; ' + $('<span>').text(f.message).html() + '</li>');
				});
			}

			if (data.errors.length > 0) {
				$results.append('<div class="notice notice-error inline"><p><strong><?php echo esc_js( __( 'Failed:', 'swpmail' ) ); ?></strong></p><ul></ul></div>');
				var $ul2 = $results.find('.notice-error ul');
				$.each(data.errors, function(i, e) {
					$ul2.append('<li>&#10007; ' + $('<span>').text(e.message + ' — ' + e.error).html() + '</li>');
				});
			}

			$('#swpm-repair-results').show();
		}).fail(function() {
			$btn.prop('disabled', false);
			$('#swpm-db-spinner').removeClass('is-active');
			alert('Request failed.');
		});
	});

	// ==================== Conflict Detector ====================

	$('#swpm-btn-detect-conflicts').on('click', function() {
		var $btn = $(this);
		$btn.prop('disabled', true);
		$('#swpm-conflict-spinner').addClass('is-active');
		$('#swpm-conflict-issues').hide();

		$.post(swpmAdmin.ajaxUrl, {
			action: 'swpm_detect_conflicts',
			nonce: swpmAdmin.nonce
		}, function(resp) {
			$btn.prop('disabled', false);
			$('#swpm-conflict-spinner').removeClass('is-active');

			if (!resp.success) {
				alert(resp.data || 'Scan failed.');
				return;
			}

			var data = resp.data;
			renderSummary('swpm-conflict', data.summary, '<?php echo esc_js( __( 'No conflicts detected', 'swpmail' ) ); ?>', '<?php echo esc_js( __( 'Conflicts found', 'swpmail' ) ); ?>');

			var $tbody = $('#swpm-conflict-issues-body').empty();
			if (data.conflicts.length === 0) {
				$tbody.append('<tr><td colspan="3"><?php echo esc_js( __( 'No conflicts detected. Your environment is clean.', 'swpmail' ) ); ?></td></tr>');
			} else {
				$.each(data.conflicts, function(i, conflict) {
					$tbody.append(
						'<tr>' +
						'<td>' + severityBadge(conflict.severity) + '</td>' +
						'<td>' + $('<span>').text(conflict.message).html() + '</td>' +
						'<td>' + $('<span>').text(conflict.resolution || '').html() + '</td>' +
						'</tr>'
					);
				});
			}
			$('#swpm-conflict-issues').show();
		}).fail(function() {
			$btn.prop('disabled', false);
			$('#swpm-conflict-spinner').removeClass('is-active');
			alert('Request failed.');
		});
	});

})(jQuery);
</script>

