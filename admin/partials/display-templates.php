<?php
/**
 * Template editor page.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* @var SWPM_Template_Editor $editor */
$editor    = swpm( 'template_editor' );
$templates = $editor->get_template_list();
$variables = $editor->get_template_variables();

/* @var SWPM_Template_Engine $engine */
$engine = swpm( 'template_engine' );

// Determine current locale so the editor can show the language context.
$current_locale = sanitize_key( determine_locale() );
// Strip charset suffix (e.g. "de_DE.UTF-8" → "de_DE").
$current_locale = (string) preg_replace( '/[^a-zA-Z_].*$/', '', $current_locale );

// Map locale codes to human-readable language names shown in the UI badge.
$locale_names = array(
	'de_DE' => 'Deutsch',
	'es_ES' => 'Español',
	'fr_FR' => 'Français',
	'it_IT' => 'Italiano',
	'ja'    => '日本語',
	'nl_NL' => 'Nederlands',
	'pt_BR' => 'Português (BR)',
	'tr_TR' => 'Türkçe',
);
$locale_label = $locale_names[ $current_locale ] ?? 'English';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current = isset( $_GET['template'] ) ? sanitize_key( $_GET['template'] ) : 'confirm-subscription';
if ( ! array_key_exists( $current, $templates ) ) {
	$current = 'confirm-subscription';
}

$content    = $engine->get_raw( $current, $current_locale );
$is_custom  = ! $editor->is_builtin( $current );
$is_builtin = $editor->is_builtin( $current );
?>

<div class="swpm-wrap">

	<!-- Page Header -->
	<div class="swpm-page-header">
		<div>
			<h1><span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Email Templates', 'swpmail' ); ?></h1>
			<p class="swpm-page-subtitle"><?php esc_html_e( 'Customise the HTML content of each email your site sends.', 'swpmail' ); ?></p>
		</div>
	</div>

	<!-- Info Box -->
	<div class="swpm-info-box">
		<h3><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Template Tips', 'swpmail' ); ?></h3>
		<p><?php esc_html_e( 'Use the available variables listed below the editor to insert dynamic content. Templates support full HTML and inline CSS for styling. You can also create custom templates and link them to triggers.', 'swpmail' ); ?></p>
		<p><span class="dashicons dashicons-warning" style="color: #dba617;"></span> <?php esc_html_e( 'Tip: Keep your HTML well-formed. Use inline CSS instead of &lt;style&gt; blocks for maximum email client compatibility. Test with tools like Litmus or Email on Acid.', 'swpmail' ); ?></p>
	</div>

	<!-- Template Layout -->
	<div class="swpm-template-layout">

		<!-- Sidebar -->
		<div class="swpm-template-sidebar">
			<div class="swpm-card" style="padding: 12px;">
				<h3 class="swpm-section-title" style="font-size: 13px; margin-bottom: 10px;">
					<span class="dashicons dashicons-media-text"></span> <?php esc_html_e( 'Templates', 'swpmail' ); ?>
				</h3>
				<ul>
					<?php // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
					<?php foreach ( $templates as $id => $label ) : ?>
						<li>
							<a href="
							<?php
							echo esc_url(
								add_query_arg(
									array(
										'page'     => 'swpmail-templates',
										'template' => $id,
									),
									admin_url( 'admin.php' )
								)
							);
							?>
										"
								class="<?php echo esc_attr( $id === $current ? 'active' : '' ); ?>">
								<?php echo esc_html( $label ); ?>
								<?php if ( ! $editor->is_builtin( $id ) ) : ?>
									<span class="swpm-badge swpm-badge--custom"><?php esc_html_e( 'Custom', 'swpmail' ); ?></span>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<button type="button" id="swpm-new-template-btn" class="swpm-btn swpm-btn--primary" style="width: 100%; margin-top: 10px; justify-content: center;">
					<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'New Template', 'swpmail' ); ?>
				</button>
			</div>
		</div>

		<!-- Editor -->
		<div class="swpm-template-main">
			<div class="swpm-card">
				<div class="swpm-card-header" style="display: flex; align-items: center; justify-content: space-between;">
					<h2>
						<?php echo esc_html( $templates[ $current ] ); ?>
						<?php if ( $is_custom ) : ?>
							<span class="swpm-badge swpm-badge--custom"><?php esc_html_e( 'Custom', 'swpmail' ); ?></span>
						<?php endif; ?>
						<span class="swpm-badge swpm-badge--locale" title="<?php echo esc_attr( $current_locale ); ?>" style="background:#e8f0fe;color:#1a56db;font-size:11px;padding:2px 8px;border-radius:4px;font-weight:600;vertical-align:middle;margin-left:6px;">
							🌐 <?php echo esc_html( $locale_label ); ?>
						</span>
					</h2>
					<?php if ( $is_custom ) : ?>
						<button type="button" id="swpm-delete-template" class="swpm-btn swpm-btn--danger swpm-btn--sm" data-template="<?php echo esc_attr( $current ); ?>">
							<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete', 'swpmail' ); ?>
						</button>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $variables[ $current ] ) ) : ?>
					<div class="swpm-template-variables">
						<span class="swpm-template-variables__label"><?php esc_html_e( 'Available variables:', 'swpmail' ); ?></span>
						<?php foreach ( $variables[ $current ] as $v ) : ?>
							<code>{{<?php echo esc_html( $v ); ?>}}</code>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<textarea id="swpm-template-editor" name="template_content" rows="20"><?php echo esc_textarea( $content ); ?></textarea>

				<input type="hidden" id="swpm-template-id" value="<?php echo esc_attr( $current ); ?>" />
				<input type="hidden" id="swpm-template-locale" value="<?php echo esc_attr( $current_locale ); ?>" />

				<div style="display: flex; align-items: center; gap: 10px; margin-top: 14px; flex-wrap: wrap;">
					<button type="button" id="swpm-save-template" class="swpm-btn swpm-btn--primary">
						<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Template', 'swpmail' ); ?>
					</button>
					<button type="button" id="swpm-preview-template" class="swpm-btn swpm-btn--secondary">
						<span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Preview', 'swpmail' ); ?>
					</button>
					<?php if ( $is_builtin ) : ?>
						<button type="button" id="swpm-reset-template" class="swpm-btn swpm-btn--secondary" onclick="return confirm('<?php echo esc_js( __( 'Reset to default? This will overwrite your changes.', 'swpmail' ) ); ?>');">
							<span class="dashicons dashicons-undo"></span> <?php esc_html_e( 'Reset to Default', 'swpmail' ); ?>
						</button>
					<?php endif; ?>
					<span id="swpm-template-result"></span>
				</div>
			</div>
		</div>
	</div>

	<!-- New Template Modal -->
	<div id="swpm-new-template-modal" class="swpm-preview-modal" style="display: none;">
		<div class="swpm-preview-modal__backdrop"></div>
		<div class="swpm-preview-modal__container" style="max-width: 520px; height: auto;">
			<div class="swpm-preview-modal__header">
				<h3><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Create New Template', 'swpmail' ); ?></h3>
				<div class="swpm-preview-modal__actions">
					<button type="button" class="swpm-preview-modal__close swpm-new-template-close" title="<?php esc_attr_e( 'Close', 'swpmail' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			</div>
			<div style="padding: 24px;">
				<div class="swpm-form-row" style="margin-bottom: 16px;">
					<label for="swpm-new-tpl-name" style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">
						<?php esc_html_e( 'Template Name', 'swpmail' ); ?> <span style="color: #e53e3e;">*</span>
					</label>
					<input type="text" id="swpm-new-tpl-name" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Order Confirmation', 'swpmail' ); ?>" style="width: 100%;" />
				</div>
				<div class="swpm-form-row" style="margin-bottom: 20px;">
					<label for="swpm-new-tpl-vars" style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">
						<?php esc_html_e( 'Variables (comma-separated)', 'swpmail' ); ?>
					</label>
					<input type="text" id="swpm-new-tpl-vars" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. order_id, customer_name, total', 'swpmail' ); ?>" style="width: 100%;" />
					<p class="description" style="margin-top: 4px;"><?php esc_html_e( 'These become {{variable}} placeholders in your template. Global variables (site_name, year, etc.) are always available.', 'swpmail' ); ?></p>
				</div>
				<div style="display: flex; gap: 10px; justify-content: flex-end;">
					<button type="button" class="swpm-btn swpm-btn--secondary swpm-new-template-close">
						<?php esc_html_e( 'Cancel', 'swpmail' ); ?>
					</button>
					<button type="button" id="swpm-create-template-submit" class="swpm-btn swpm-btn--primary">
						<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Create Template', 'swpmail' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Preview Modal -->
	<div id="swpm-preview-modal" class="swpm-preview-modal" style="display: none;">
		<div class="swpm-preview-modal__backdrop"></div>
		<div class="swpm-preview-modal__container">
			<div class="swpm-preview-modal__header">
				<h3><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Template Preview', 'swpmail' ); ?></h3>
				<div class="swpm-preview-modal__actions">
					<button type="button" class="swpm-preview-device active" data-device="desktop" title="<?php esc_attr_e( 'Desktop', 'swpmail' ); ?>">
						<span class="dashicons dashicons-desktop"></span>
					</button>
					<button type="button" class="swpm-preview-device" data-device="mobile" title="<?php esc_attr_e( 'Mobile', 'swpmail' ); ?>">
						<span class="dashicons dashicons-smartphone"></span>
					</button>
					<button type="button" id="swpm-preview-close" class="swpm-preview-modal__close" title="<?php esc_attr_e( 'Close', 'swpmail' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			</div>
			<div class="swpm-preview-modal__body">
				<iframe id="swpm-preview-iframe" sandbox=""></iframe>
			</div>
		</div>
	</div>

</div>
