<?php
/**
 * Triggers management page.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle save.
if ( isset( $_POST['swpm_save_triggers'] ) && check_admin_referer( 'swpm_triggers_nonce' ) ) {
	$active = isset( $_POST['swpm_active_triggers'] ) && is_array( $_POST['swpm_active_triggers'] )
		? array_map( 'sanitize_key', $_POST['swpm_active_triggers'] )
		: array();
	update_option( 'swpm_active_triggers', $active );
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Triggers saved.', 'swpmail' ) . '</p></div>';
}

/** @var SWPM_Trigger_Manager $manager */
$manager         = swpm( 'trigger_manager' );
$triggers        = $manager->get_all();
$active_triggers = (array) get_option( 'swpm_active_triggers', array() );

/** @var SWPM_Template_Editor $editor */
$editor    = swpm( 'template_editor' );
$templates = $editor->get_template_list();
?>

<div class="swpm-wrap">

	<!-- Page Header -->
	<div class="swpm-page-header">
		<div>
			<h1><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Triggers', 'swpmail' ); ?></h1>
			<p class="swpm-page-subtitle"><?php esc_html_e( 'Enable or disable automatic email triggers for WordPress events.', 'swpmail' ); ?></p>
		</div>
	</div>

	<!-- Info Box -->
	<div class="swpm-info-box">
		<h3><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'How Triggers Work', 'swpmail' ); ?></h3>
		<p><?php esc_html_e( 'Each trigger listens for a specific WordPress event (hook) and automatically sends an email using its linked template. Enable the triggers you want, then customise their templates in the Email Templates page. You can also create custom triggers from the panel below.', 'swpmail' ); ?></p>
	</div>

	<!-- Trigger Table -->
	<div class="swpm-card">
		<div class="swpm-card-header" style="display: flex; align-items: center; justify-content: space-between;">
			<h2><?php esc_html_e( 'Available Triggers', 'swpmail' ); ?></h2>
			<button type="button" id="swpm-new-trigger-btn" class="swpm-btn swpm-btn--primary swpm-btn--sm">
				<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'New Trigger', 'swpmail' ); ?>
			</button>
		</div>

		<form method="post">
			<?php wp_nonce_field( 'swpm_triggers_nonce' ); ?>

			<?php if ( ! empty( $triggers ) ) : ?>
				<table class="swpm-triggers-table">
					<thead>
						<tr>
							<th style="width:60px;"><?php esc_html_e( 'Active', 'swpmail' ); ?></th>
							<th><?php esc_html_e( 'Trigger', 'swpmail' ); ?></th>
							<th><?php esc_html_e( 'Hook', 'swpmail' ); ?></th>
							<th><?php esc_html_e( 'Template', 'swpmail' ); ?></th>
							<th style="width:80px;"><?php esc_html_e( 'Actions', 'swpmail' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $triggers as $trigger ) :
							$is_custom = ! $manager->is_builtin( $trigger->get_key() );
						?>
							<tr>
								<td>
									<input type="checkbox"
										   name="swpm_active_triggers[]"
										   value="<?php echo esc_attr( $trigger->get_key() ); ?>"
										   <?php checked( in_array( $trigger->get_key(), $active_triggers, true ) ); ?>
									/>
								</td>
								<td>
									<strong><?php echo esc_html( $trigger->get_label() ); ?></strong>
									<?php if ( $is_custom ) : ?>
										<span class="swpm-badge swpm-badge--custom"><?php esc_html_e( 'Custom', 'swpmail' ); ?></span>
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html( $trigger->get_hook() ); ?></code></td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'swpmail-templates', 'template' => $trigger->get_template_id() ), admin_url( 'admin.php' ) ) ); ?>">
										<?php echo esc_html( $trigger->get_template_id() ); ?> &rarr;
									</a>
								</td>
								<td>
									<?php if ( $is_custom ) : ?>
										<button type="button" class="swpm-delete-trigger-btn swpm-btn swpm-btn--danger swpm-btn--xs" data-key="<?php echo esc_attr( $trigger->get_key() ); ?>" data-label="<?php echo esc_attr( $trigger->get_label() ); ?>">
											<span class="dashicons dashicons-trash"></span>
										</button>
									<?php else : ?>
										<span style="color: var(--swpm-gray-300);">—</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<div class="swpm-empty-state">
					<span class="dashicons dashicons-update"></span>
					<p><?php esc_html_e( 'No triggers registered.', 'swpmail' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="swpm-ms-save-bar">
				<button type="submit" name="swpm_save_triggers" class="swpm-btn swpm-btn--primary">
					<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Triggers', 'swpmail' ); ?>
				</button>
			</div>
		</form>
	</div>

	<!-- Developer Guide -->
	<div class="swpm-card">
		<div class="swpm-card-header">
			<h2><?php esc_html_e( 'Custom Triggers (Developers)', 'swpmail' ); ?></h2>
		</div>
		<p style="font-size: 13px; color: var(--swpm-gray-600); margin: 0 0 12px;">
			<?php esc_html_e( 'You can also register triggers programmatically via code using the action hook:', 'swpmail' ); ?>
		</p>
		<div class="swpm-template-variables">
			<code>swpm_register_triggers</code>
		</div>
		<p style="font-size: 13px; color: var(--swpm-gray-500); margin: 8px 0 0;">
			<?php esc_html_e( 'Hook into this action and use the Trigger Manager API to add custom triggers that fire on any WordPress hook.', 'swpmail' ); ?>
		</p>
	</div>

	<!-- New Trigger Modal -->
	<div id="swpm-new-trigger-modal" class="swpm-preview-modal" style="display: none;">
		<div class="swpm-preview-modal__backdrop"></div>
		<div class="swpm-preview-modal__container" style="max-width: 580px; height: auto;">
			<div class="swpm-preview-modal__header">
				<h3><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Create Custom Trigger', 'swpmail' ); ?></h3>
				<div class="swpm-preview-modal__actions">
					<button type="button" class="swpm-preview-modal__close swpm-new-trigger-close" title="<?php esc_attr_e( 'Close', 'swpmail' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			</div>
			<div style="padding: 24px;">
				<div class="swpm-form-row" style="margin-bottom: 14px;">
					<label for="swpm-new-trg-label" style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">
						<?php esc_html_e( 'Trigger Name', 'swpmail' ); ?> <span style="color: #e53e3e;">*</span>
					</label>
					<input type="text" id="swpm-new-trg-label" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. WooCommerce Order Complete', 'swpmail' ); ?>" style="width: 100%;" />
				</div>

				<div class="swpm-form-row" style="margin-bottom: 14px;">
					<label for="swpm-new-trg-hook" style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">
						<?php esc_html_e( 'WordPress Hook', 'swpmail' ); ?> <span style="color: #e53e3e;">*</span>
					</label>
					<input type="text" id="swpm-new-trg-hook" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. woocommerce_order_status_completed', 'swpmail' ); ?>" style="width: 100%;" />
					<p class="description"><?php esc_html_e( 'The WordPress action hook that will fire this trigger.', 'swpmail' ); ?></p>
				</div>

				<div style="display: flex; gap: 14px; margin-bottom: 14px;">
					<div style="flex: 1;">
						<label for="swpm-new-trg-hook-args" style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">
							<?php esc_html_e( 'Hook Arguments', 'swpmail' ); ?>
						</label>
						<input type="number" id="swpm-new-trg-hook-args" class="small-text" value="1" min="1" max="10" style="width: 100%;" />
					</div>
					<div style="flex: 1;">
						<label for="swpm-new-trg-recipients" style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">
							<?php esc_html_e( 'Recipients', 'swpmail' ); ?>
						</label>
						<select id="swpm-new-trg-recipients" style="width: 100%;">
							<option value="subscribers"><?php esc_html_e( 'All Subscribers', 'swpmail' ); ?></option>
							<option value="admin"><?php esc_html_e( 'Admin Only', 'swpmail' ); ?></option>
							<option value="hook_user"><?php esc_html_e( 'Hook User (auto-detect)', 'swpmail' ); ?></option>
						</select>
					</div>
				</div>

				<div class="swpm-form-row" style="margin-bottom: 14px;">
					<label for="swpm-new-trg-template" style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">
						<?php esc_html_e( 'Email Template', 'swpmail' ); ?> <span style="color: #e53e3e;">*</span>
					</label>
					<select id="swpm-new-trg-template" style="width: 100%;">
						<option value=""><?php esc_html_e( '— Select Template —', 'swpmail' ); ?></option>
						<?php foreach ( $templates as $tpl_id => $tpl_label ) : ?>
							<option value="<?php echo esc_attr( $tpl_id ); ?>"><?php echo esc_html( $tpl_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="swpm-form-row" style="margin-bottom: 20px;">
					<label for="swpm-new-trg-subject" style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px;">
						<?php esc_html_e( 'Email Subject', 'swpmail' ); ?> <span style="color: #e53e3e;">*</span>
					</label>
					<input type="text" id="swpm-new-trg-subject" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Your order {{arg_0}} is complete!', 'swpmail' ); ?>" style="width: 100%;" />
					<p class="description"><?php esc_html_e( 'You can use {{variable}} placeholders in the subject line.', 'swpmail' ); ?></p>
				</div>

				<div style="display: flex; gap: 10px; justify-content: flex-end;">
					<button type="button" class="swpm-btn swpm-btn--secondary swpm-new-trigger-close">
						<?php esc_html_e( 'Cancel', 'swpmail' ); ?>
					</button>
					<button type="button" id="swpm-create-trigger-submit" class="swpm-btn swpm-btn--primary">
						<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Create Trigger', 'swpmail' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
