<?php
/**
 * DNS Domain Checker admin page.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* @var SWPM_DNS_Checker $dns_checker */
$dns_checker = swpm( 'dns_checker' );
$from_domain = $dns_checker ? $dns_checker->get_from_domain() : '';
$auto_result = $from_domain ? $dns_checker->check( $from_domain ) : null;
?>

<div class="swpm-wrap">

	<!-- Page Header -->
	<div class="swpm-page-header">
		<div>
			<h1><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'DNS Domain Checker', 'swpmail' ); ?></h1>
			<p class="swpm-page-subtitle"><?php esc_html_e( 'Verify SPF, DKIM, and DMARC records to maximize email deliverability.', 'swpmail' ); ?></p>
		</div>
	</div>

	<!-- Info Box -->
	<div class="swpm-info-box">
		<h3><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Why DNS Records Matter', 'swpmail' ); ?></h3>
		<p><?php esc_html_e( 'SPF, DKIM, and DMARC are email authentication protocols that prove you are authorized to send email from your domain. Without them, your emails are more likely to end up in spam or be rejected entirely.', 'swpmail' ); ?></p>
	</div>

	<!-- Domain Lookup -->
	<div class="swpm-card">
		<h3 class="swpm-section-title"><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Check a Domain', 'swpmail' ); ?></h3>
		<div class="swpm-dns-lookup-form">
			<input type="text" id="swpm-dns-domain" class="regular-text" placeholder="example.com" value="<?php echo esc_attr( $from_domain ); ?>">
			<button type="button" id="swpm-dns-check-btn" class="swpm-btn swpm-btn--primary">
				<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Check DNS', 'swpmail' ); ?>
			</button>
			<?php if ( $from_domain ) : ?>
				<button type="button" id="swpm-dns-auto-check-btn" class="swpm-btn swpm-btn--secondary">
					<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Re-check My Domain', 'swpmail' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php if ( $from_domain ) : ?>
			<p class="description" style="margin-top: 8px;">
				<?php
				printf(
					/* translators: %s: from-email domain */
					esc_html__( 'Your configured sender domain: %s', 'swpmail' ),
					'<strong>' . esc_html( $from_domain ) . '</strong>'
				);
				?>
			</p>
		<?php endif; ?>
	</div>

	<!-- Results Container -->
	<div id="swpm-dns-results" <?php echo $auto_result ? '' : 'style="display:none;"'; ?>>

		<?php if ( $auto_result ) : ?>
		<!-- Auto-check results (server-rendered on page load) -->
			<?php $r = $auto_result; ?>

		<!-- Overall Score -->
		<div class="swpm-dns-overall swpm-dns-overall--<?php echo esc_attr( $r['overall'] ); ?>">
			<div class="swpm-dns-overall__icon">
				<?php if ( 'pass' === $r['overall'] ) : ?>
					<span class="dashicons dashicons-yes-alt"></span>
				<?php elseif ( 'warning' === $r['overall'] ) : ?>
					<span class="dashicons dashicons-warning"></span>
				<?php else : ?>
					<span class="dashicons dashicons-dismiss"></span>
				<?php endif; ?>
			</div>
			<div class="swpm-dns-overall__text">
				<strong>
					<?php if ( 'pass' === $r['overall'] ) : ?>
						<?php esc_html_e( 'All checks passed', 'swpmail' ); ?>
					<?php elseif ( 'warning' === $r['overall'] ) : ?>
						<?php esc_html_e( 'Some issues found', 'swpmail' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Critical issues detected', 'swpmail' ); ?>
					<?php endif; ?>
				</strong>
				<span class="swpm-dns-overall__domain"><?php echo esc_html( $r['domain'] ); ?></span>
			</div>
			<div class="swpm-dns-overall__time">
				<?php
				printf(
					/* translators: %s: relative time */
					esc_html__( 'Checked %s ago', 'swpmail' ),
					esc_html( human_time_diff( $r['timestamp'] ) )
				);
				?>
			</div>
		</div>

		<!-- SPF Card -->
		<div class="swpm-card swpm-dns-record-card">
			<div class="swpm-dns-record-header">
				<div class="swpm-dns-record-badge swpm-dns-record-badge--<?php echo esc_attr( $r['spf']['status'] ); ?>">
					<?php echo esc_html( strtoupper( $r['spf']['status'] ) ); ?>
				</div>
				<h3>SPF <span class="swpm-dns-record-subtitle">(Sender Policy Framework)</span></h3>
			</div>
			<?php if ( ! empty( $r['spf']['record'] ) ) : ?>
				<div class="swpm-dns-record-raw">
					<code><?php echo esc_html( $r['spf']['record'] ); ?></code>
				</div>
			<?php endif; ?>
			<?php foreach ( $r['spf']['warnings'] as $w ) : ?>
				<div class="swpm-dns-msg swpm-dns-msg--warning"><span class="dashicons dashicons-warning"></span> <?php echo esc_html( $w ); ?></div>
			<?php endforeach; ?>
			<?php foreach ( $r['spf']['details'] as $d ) : ?>
				<div class="swpm-dns-msg swpm-dns-msg--info"><span class="dashicons dashicons-info-outline"></span> <?php echo esc_html( $d ); ?></div>
			<?php endforeach; ?>
		</div>

		<!-- DKIM Card -->
		<div class="swpm-card swpm-dns-record-card">
			<div class="swpm-dns-record-header">
				<div class="swpm-dns-record-badge swpm-dns-record-badge--<?php echo esc_attr( $r['dkim']['status'] ); ?>">
					<?php echo esc_html( strtoupper( $r['dkim']['status'] ) ); ?>
				</div>
				<h3>DKIM <span class="swpm-dns-record-subtitle">(DomainKeys Identified Mail)</span></h3>
			</div>
			<?php if ( ! empty( $r['dkim']['records'] ) ) : ?>
				<?php foreach ( $r['dkim']['records'] as $dkim_rec ) : ?>
					<div class="swpm-dns-dkim-entry">
						<span class="swpm-dns-dkim-selector"><?php echo esc_html( $dkim_rec['selector'] ); ?></span>
						<span class="swpm-dns-dkim-type"><?php echo esc_html( $dkim_rec['type'] ); ?></span>
						<code class="swpm-dns-record-raw-inline"><?php echo esc_html( mb_strimwidth( $dkim_rec['value'], 0, 120, '…' ) ); ?></code>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
			<?php foreach ( $r['dkim']['warnings'] as $w ) : ?>
				<div class="swpm-dns-msg swpm-dns-msg--warning"><span class="dashicons dashicons-warning"></span> <?php echo esc_html( $w ); ?></div>
			<?php endforeach; ?>
			<?php foreach ( $r['dkim']['details'] as $d ) : ?>
				<div class="swpm-dns-msg swpm-dns-msg--info"><span class="dashicons dashicons-info-outline"></span> <?php echo esc_html( $d ); ?></div>
			<?php endforeach; ?>
		</div>

		<!-- DMARC Card -->
		<div class="swpm-card swpm-dns-record-card">
			<div class="swpm-dns-record-header">
				<div class="swpm-dns-record-badge swpm-dns-record-badge--<?php echo esc_attr( $r['dmarc']['status'] ); ?>">
					<?php echo esc_html( strtoupper( $r['dmarc']['status'] ) ); ?>
				</div>
				<h3>DMARC <span class="swpm-dns-record-subtitle">(Domain-based Message Authentication)</span></h3>
			</div>
			<?php if ( ! empty( $r['dmarc']['record'] ) ) : ?>
				<div class="swpm-dns-record-raw">
					<code><?php echo esc_html( $r['dmarc']['record'] ); ?></code>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $r['dmarc']['policy'] ) ) : ?>
				<div class="swpm-dns-dmarc-policy">
					<?php
					printf(
						/* translators: %s: DMARC policy */
						esc_html__( 'Policy: %s', 'swpmail' ),
						'<strong>' . esc_html( $r['dmarc']['policy'] ) . '</strong>'
					);
					?>
				</div>
			<?php endif; ?>
			<?php foreach ( $r['dmarc']['warnings'] as $w ) : ?>
				<div class="swpm-dns-msg swpm-dns-msg--warning"><span class="dashicons dashicons-warning"></span> <?php echo esc_html( $w ); ?></div>
			<?php endforeach; ?>
			<?php foreach ( $r['dmarc']['details'] as $d ) : ?>
				<div class="swpm-dns-msg swpm-dns-msg--info"><span class="dashicons dashicons-info-outline"></span> <?php echo esc_html( $d ); ?></div>
			<?php endforeach; ?>
		</div>

		<?php endif; ?>
	</div>

	<!-- AJAX results will be injected here dynamically -->
	<div id="swpm-dns-ajax-results"></div>

</div>
