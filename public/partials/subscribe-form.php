<?php
/**
 * Subscribe form template.
 *
 * Available variables:
 *   $show_name, $show_frequency, $frequency_default,
 *   $style, $title, $button_text, $nonce
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wrapper_class = 'swpm-subscribe-wrapper';
$form_class    = 'swpm-subscribe-form';
if ( 'minimal' === $style ) {
	$form_class .= ' swpm-subscribe-form--minimal';
}
?>

<div class="<?php echo esc_attr( $wrapper_class ); ?>">
	<?php if ( ! empty( $title ) ) : ?>
		<h3><?php echo esc_html( $title ); ?></h3>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" class="<?php echo esc_attr( $form_class ); ?>">
		<input type="hidden" name="action" value="swpm_subscribe" />
		<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>" />

		<?php if ( $show_name ) : ?>
			<div class="swpm-form-group">
				<label for="swpm-name"><?php esc_html_e( 'Name', 'swpmail' ); ?></label>
				<input type="text" id="swpm-name" name="name" placeholder="<?php esc_attr_e( 'Your name', 'swpmail' ); ?>" />
			</div>
		<?php endif; ?>

		<div class="swpm-form-group">
			<label for="swpm-email"><?php esc_html_e( 'Email', 'swpmail' ); ?> <span aria-hidden="true">*</span></label>
			<input type="email" id="swpm-email" name="email" required placeholder="<?php esc_attr_e( 'your@email.com', 'swpmail' ); ?>" />
		</div>

		<?php if ( $show_frequency ) : ?>
			<div class="swpm-form-group">
				<label for="swpm-frequency"><?php esc_html_e( 'Frequency', 'swpmail' ); ?></label>
				<select id="swpm-frequency" name="frequency">
					<option value="instant" <?php selected( $frequency_default, 'instant' ); ?>><?php esc_html_e( 'Instant', 'swpmail' ); ?></option>
					<option value="daily" <?php selected( $frequency_default, 'daily' ); ?>><?php esc_html_e( 'Daily Digest', 'swpmail' ); ?></option>
					<option value="weekly" <?php selected( $frequency_default, 'weekly' ); ?>><?php esc_html_e( 'Weekly Digest', 'swpmail' ); ?></option>
				</select>
			</div>
		<?php else : ?>
			<input type="hidden" name="frequency" value="<?php echo esc_attr( $frequency_default ); ?>" />
		<?php endif; ?>

		<!-- Honeypot -->
		<div class="swpm-hp-field" aria-hidden="true">
			<label for="swpm-website">Website</label>
			<input type="text" id="swpm-website" name="swpm_website" value="" tabindex="-1" autocomplete="off" />
		</div>

		<?php if ( get_option( 'swpm_gdpr_checkbox', true ) ) : ?>
			<div class="swpm-gdpr-group">
				<label>
					<input type="checkbox" name="gdpr" value="1" required />
					<?php
					$privacy_url = get_privacy_policy_url();
					if ( $privacy_url ) {
						printf(
							/* translators: %s: privacy policy link */
							wp_kses(
								// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
								__( 'I agree to the <a href="%s" target="_blank">Privacy Policy</a>.', 'swpmail' ),
								array(
									'a' => array(
										'href'   => array(),
										'target' => array(),
									),
								)
							),
							esc_url( $privacy_url )
						);
					} else {
						esc_html_e( 'I agree to the Privacy Policy.', 'swpmail' );
					}
					?>
				</label>
			</div>
		<?php endif; ?>

		<button type="submit" class="swpm-btn--subscribe"><?php echo esc_html( $button_text ); ?></button>

		<div class="swpm-message" style="display:none;"></div>
	</form>
</div>
