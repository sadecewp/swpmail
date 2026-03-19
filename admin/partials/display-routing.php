<?php
/**
 * Smart Routing admin page — Tabbed UX redesign.
 *
 * Tabs:
 *  1 — Rules      : enable toggle + rule builder
 *  2 — Test Route : simulate provider selection
 *
 * @package SWPMail
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$router = swpm( 'router' );
$rules  = ( $router instanceof SWPM_Router ) ? $router->get_rules() : array();
$enabled = get_option( 'swpm_enable_smart_routing', false );

/** @var SWPM_Provider_Factory $factory */
$factory   = swpm( 'provider_factory' );
$providers = ( $factory instanceof SWPM_Provider_Factory ) ? $factory->get_all() : array();
$default   = get_option( 'swpm_mail_provider', 'phpmail' );

// Build display labels.
$provider_options = array();
foreach ( $providers as $key => $class ) {
	if ( $key === $default ) {
		continue;
	}
	$label = ucfirst( str_replace( array( '_', '-' ), ' ', $key ) );
	if ( class_exists( $class ) ) {
		$tmp   = new $class();
		$label = $tmp->get_label();
	}
	$provider_options[ $key ] = $label;
}
?>

<div class="swpm-wrap">

	<!-- Page Header -->
	<div class="swpm-page-header">
		<div>
			<h1><span class="dashicons dashicons-randomize"></span> <?php esc_html_e( 'Smart Routing', 'swpmail' ); ?></h1>
			<p class="swpm-page-subtitle"><?php esc_html_e( 'Route emails to different providers based on recipient, subject, sender, or source.', 'swpmail' ); ?></p>
		</div>
	</div>

	<!-- Enable Toggle (always visible) -->
	<div class="swpm-card" style="margin-bottom: 20px;">
		<div class="swpm-settings-checks">
			<div class="swpm-settings-check">
				<label class="swpm-settings-check__label">
					<input type="checkbox" id="swpm-routing-toggle" value="1" <?php checked( $enabled ); ?>>
					<div class="swpm-settings-check__content">
						<span class="swpm-settings-check__title"><?php esc_html_e( 'Enable Smart Routing', 'swpmail' ); ?></span>
						<span class="swpm-settings-check__desc">
							<?php
							printf(
								/* translators: %s: default provider name */
								esc_html__( 'Evaluate routing rules for every outgoing email. When no rule matches, the default provider (%s) is used.', 'swpmail' ),
								'<strong>' . esc_html( ucfirst( $default ) ) . '</strong>'
							);
							?>
						</span>
					</div>
				</label>
			</div>
		</div>
	</div>

	<!-- Tabs -->
	<div class="swpm-ms-tabs" role="tablist">
		<button class="swpm-ms-tab active" role="tab" aria-selected="true"  data-tab="routing-rules"  type="button">
			<span class="dashicons dashicons-list-view"></span>
			<?php esc_html_e( 'Routing Rules', 'swpmail' ); ?>
		</button>
		<button class="swpm-ms-tab" role="tab" aria-selected="false" data-tab="routing-test"   type="button">
			<span class="dashicons dashicons-search"></span>
			<?php esc_html_e( 'Test Route', 'swpmail' ); ?>
		</button>
	</div>

	<!-- ═══════════════ TAB 1 — ROUTING RULES ═══════════════ -->
	<div class="swpm-ms-panel" id="swpm-tab-routing-rules" role="tabpanel">
		<div class="swpm-card">

			<div class="swpm-routing-rules-toolbar">
				<div class="swpm-ms-config-notice" style="flex: 1; margin-bottom: 0;">
					<span class="dashicons dashicons-info-outline"></span>
					<?php esc_html_e( 'Rules are evaluated top-to-bottom by priority. The first matching rule wins. All conditions within a rule must match (AND logic).', 'swpmail' ); ?>
				</div>
				<button type="button" id="swpm-add-rule" class="swpm-btn swpm-btn--primary swpm-btn--sm">
					<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Rule', 'swpmail' ); ?>
				</button>
			</div>

			<div id="swpm-rules-list" class="swpm-rules-list"></div>

			<div id="swpm-rules-empty" class="swpm-empty-state" style="display:none;">
				<span class="dashicons dashicons-randomize"></span>
				<p><?php esc_html_e( 'No routing rules defined. Click "Add Rule" to create your first rule.', 'swpmail' ); ?></p>
			</div>

			<div class="swpm-ms-save-bar">
				<button type="button" id="swpm-save-rules" class="swpm-btn swpm-btn--primary">
					<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Rules', 'swpmail' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- ═══════════════ TAB 2 — TEST ROUTE ═══════════════ -->
	<div class="swpm-ms-panel swpm-ms-panel--hidden" id="swpm-tab-routing-test" role="tabpanel">
		<div class="swpm-card">
			<p class="swpm-ms-provider-hint"><?php esc_html_e( 'Enter sample email data to simulate which provider would be selected by the current rule set.', 'swpmail' ); ?></p>

			<div class="swpm-ms-field-grid">
				<div class="swpm-ms-field">
					<label for="swpm-test-to"><?php esc_html_e( 'To (Recipient)', 'swpmail' ); ?></label>
					<input type="email" id="swpm-test-to" class="regular-text" placeholder="user@example.com">
				</div>
				<div class="swpm-ms-field">
					<label for="swpm-test-from"><?php esc_html_e( 'From (Sender)', 'swpmail' ); ?></label>
					<input type="email" id="swpm-test-from" class="regular-text" placeholder="noreply@mysite.com">
				</div>
				<div class="swpm-ms-field">
					<label for="swpm-test-subject"><?php esc_html_e( 'Subject', 'swpmail' ); ?></label>
					<input type="text" id="swpm-test-subject" class="regular-text" placeholder="Order Confirmation #1234">
				</div>
				<div class="swpm-ms-field">
					<label for="swpm-test-source"><?php esc_html_e( 'Source', 'swpmail' ); ?></label>
					<input type="text" id="swpm-test-source" class="regular-text" placeholder="woocommerce, wp-core, swpmail">
				</div>
			</div>

			<div class="swpm-ms-save-bar">
				<button type="button" id="swpm-test-route-btn" class="swpm-btn swpm-btn--secondary">
					<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Run Test', 'swpmail' ); ?>
				</button>
			</div>

			<div id="swpm-route-test-result" style="display:none; margin-top: 16px;"></div>
		</div>
	</div>

</div>

<!-- Rule Template (hidden, cloned by JS) -->
<script type="text/html" id="tmpl-swpm-rule">
<div class="swpm-rule" data-rule-id="{{data.id}}">
	<div class="swpm-rule__header">
		<span class="swpm-rule__drag dashicons dashicons-menu"></span>
		<label class="swpm-rule__toggle">
			<input type="checkbox" class="swpm-rule__enabled" {{data.enabledAttr}}>
		</label>
		<input type="text" class="swpm-rule__name" value="{{data.name}}" placeholder="<?php esc_attr_e( 'Rule name…', 'swpmail' ); ?>">
		<span class="swpm-rule__priority-label"><?php esc_html_e( 'Priority', 'swpmail' ); ?></span>
		<input type="number" class="swpm-rule__priority" value="{{data.priority}}" min="1" max="100">
		<select class="swpm-rule__provider">
			<option value=""><?php esc_html_e( '— Select provider —', 'swpmail' ); ?></option>
			<?php foreach ( $provider_options as $k => $l ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="swpm-rule__collapse dashicons dashicons-arrow-up-alt2" title="<?php esc_attr_e( 'Toggle', 'swpmail' ); ?>"></button>
		<button type="button" class="swpm-rule__delete dashicons dashicons-trash" title="<?php esc_attr_e( 'Delete rule', 'swpmail' ); ?>"></button>
	</div>
	<div class="swpm-rule__conditions">
		<div class="swpm-rule__conditions-list"></div>
		<button type="button" class="swpm-rule__add-condition swpm-btn swpm-btn--sm swpm-btn--secondary">
			<span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add Condition', 'swpmail' ); ?>
		</button>
	</div>
</div>
</script>

<script type="text/html" id="tmpl-swpm-condition">
<div class="swpm-condition">
	<select class="swpm-condition__field">
		<option value="to"><?php esc_html_e( 'Recipient (To)', 'swpmail' ); ?></option>
		<option value="subject"><?php esc_html_e( 'Subject', 'swpmail' ); ?></option>
		<option value="from"><?php esc_html_e( 'Sender (From)', 'swpmail' ); ?></option>
		<option value="header"><?php esc_html_e( 'Headers', 'swpmail' ); ?></option>
		<option value="source"><?php esc_html_e( 'Source', 'swpmail' ); ?></option>
	</select>
	<select class="swpm-condition__operator">
		<option value="contains"><?php esc_html_e( 'contains', 'swpmail' ); ?></option>
		<option value="not_contains"><?php esc_html_e( 'not contains', 'swpmail' ); ?></option>
		<option value="equals"><?php esc_html_e( 'equals', 'swpmail' ); ?></option>
		<option value="not_equals"><?php esc_html_e( 'not equals', 'swpmail' ); ?></option>
		<option value="starts_with"><?php esc_html_e( 'starts with', 'swpmail' ); ?></option>
		<option value="ends_with"><?php esc_html_e( 'ends with', 'swpmail' ); ?></option>
		<option value="matches"><?php esc_html_e( 'matches (regex)', 'swpmail' ); ?></option>
	</select>
	<input type="text" class="swpm-condition__value" value="{{data.value}}" placeholder="<?php esc_attr_e( 'Value…', 'swpmail' ); ?>">
	<button type="button" class="swpm-condition__remove dashicons dashicons-no-alt" title="<?php esc_attr_e( 'Remove', 'swpmail' ); ?>"></button>
</div>
</script>

<script>
	var swpmRoutingRules = <?php echo wp_json_encode( $rules ); ?>;

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
