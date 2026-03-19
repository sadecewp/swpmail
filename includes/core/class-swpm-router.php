<?php
/**
 * Smart Router — conditional email routing engine.
 *
 * Evaluates a set of user-defined rules against outgoing e-mails and routes
 * Each message to the provider specified by the first matching rule.
 * When no rule matches, the default provider (primary + failover) is used.
 *
 * Rules are stored as a JSON-encoded option (`swpm_routing_rules`).
 *
 * Rule structure (single rule):
 * {
 *   "id":        "r_abc123",          // Unique ID.
 *   "name":      "Transactional",     // Human-readable label.
 *   "enabled":   true,
 *   "priority":  10,                  // Lower = evaluated first.
 *   "provider":  "sendgrid",          // Target provider key.
 *   "conditions": [                   // ALL conditions must match (AND).
 *     { "field": "to", "operator": "contains", "value": "@example.com" }
 *   ]
 * }
 *
 * Supported condition fields: to, subject, from, header, source.
 * Supported operators: contains, not_contains, equals, not_equals,
 *                      Starts_with, ends_with, matches (regex).
 *
 * @package SWPMail
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes emails to the appropriate provider.
 */
class SWPM_Router {

	/**
	 * Provider factory instance.
	 *
	 * @var SWPM_Provider_Factory
	 */
	private SWPM_Provider_Factory $factory;

	/**
	 * Loaded rules (sorted by priority).
	 *
	 * @var array
	 */
	private array $rules = array();

	/**
	 * Whether rules have been loaded.
	 *
	 * @var bool
	 */
	private bool $loaded = false;

	/**
	 * Constructor.
	 *
	 * @param SWPM_Provider_Factory $factory Factory.
	 */
	public function __construct( SWPM_Provider_Factory $factory ) {
		$this->factory = $factory;

		add_action( 'wp_ajax_swpm_save_routing_rules', array( $this, 'ajax_save_rules' ) );
		add_action( 'wp_ajax_swpm_save_routing_toggle', array( $this, 'ajax_save_toggle' ) );
		add_action( 'wp_ajax_swpm_test_routing', array( $this, 'ajax_test_routing' ) );
	}

	// ------------------------------------------------------------------
	// Rule Loading
	// ----------------------------------------------------------------

	/**
	 * Load and cache rules from the database.
	 *
	 * @return array
	 */
	public function get_rules(): array {
		if ( $this->loaded ) {
			return $this->rules;
		}

		$raw   = get_option( 'swpm_routing_rules', '[]' );
		$rules = json_decode( $raw, true );

		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		// Sort by priority ascending.
		usort(
			$rules,
			static function ( $a, $b ) {
				return ( $a['priority'] ?? 50 ) <=> ( $b['priority'] ?? 50 );
			}
		);

		$this->rules  = $rules;
		$this->loaded = true;

		return $this->rules;
	}

	/**
	 * Persist rules to the database.
	 *
	 * @param array $rules Rules array.
	 */
	public function save_rules( array $rules ): void {
		$sanitized = $this->sanitize_rules( $rules );
		update_option( 'swpm_routing_rules', wp_json_encode( $sanitized ), false );
		$this->rules  = $sanitized;
		$this->loaded = true;
	}

	// phpcs:ignore Squiz.Commenting.BlockComment.NoEmptyLineBefore, Squiz.Commenting.FunctionComment.ParamCommentFullStop
	// ------------------------------------------------------------------
	// Rule Evaluation
	// ----------------------------------------------------------------

	/**
	 * Resolve the provider for a given email context.
	 *
	 * @param array $context Email context with keys: to, subject, from, headers, source.
	 * @return SWPM_Provider_Interface|null Matched provider or null (use default).
	 */
	public function resolve( array $context ): ?SWPM_Provider_Interface {
		$rules = $this->get_rules();

		if ( empty( $rules ) || ! get_option( 'swpm_enable_smart_routing', false ) ) {
			return null;
		}

		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			if ( $this->evaluate_rule( $rule, $context ) ) {
				$provider_key = $rule['provider'] ?? '';

				if ( empty( $provider_key ) ) {
					continue;
				}

				$provider = $this->make_provider( $provider_key );
				if ( $provider ) {
					swpm_log(
						'info',
						sprintf(
							'Smart routing: rule "%s" matched → provider "%s" for %s',
							$rule['name'] ?? $rule['id'],
							$provider_key,
							$context['to'] ?? ''
						)
					);
					return $provider;
				}
			}
		}

		return null; // No match; use default.
	}

	/**
	 * Evaluate a single rule against the email context.
	 *
	 * All conditions must match (logical AND).
	 *
	 * @param array $rule    Rule definition.
	 * @param array $context Email context.
	 * @return bool
	 */
	public function evaluate_rule( array $rule, array $context ): bool {
		$conditions = $rule['conditions'] ?? array();

		if ( empty( $conditions ) ) {
			return false; // No conditions = never match (safety).
		}

		foreach ( $conditions as $condition ) {
			if ( ! $this->evaluate_condition( $condition, $context ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Evaluate a single condition.
	 *
	 * @param array $condition { field, operator, value }.
	 * @param array $context   Email context.
	 * @return bool
	 */
	private function evaluate_condition( array $condition, array $context ): bool {
		$field    = $condition['field'] ?? '';
		$operator = $condition['operator'] ?? 'contains';
		$value    = $condition['value'] ?? '';

		$subject = $this->extract_field( $field, $context );

		return $this->compare( $subject, $operator, $value );
	}

	/**
	 * Extract a field value from the context.
	 *
	 * @param string $field   Field name.
	 * @param array  $context Email context.
	 * @return string
	 */
	private function extract_field( string $field, array $context ): string {
		switch ( $field ) {
			case 'to':
				return strtolower( $context['to'] ?? '' );
			case 'subject':
				return $context['subject'] ?? '';
			case 'from':
				return strtolower( $context['from'] ?? get_option( 'swpm_from_email', '' ) );
			case 'source':
				return $context['source'] ?? '';
			case 'header':
				// Flatten headers to a single string for searching.
				$headers = $context['headers'] ?? array();
				return is_array( $headers ) ? implode( "\n", $headers ) : (string) $headers;
			default:
				return '';
		}
	}

	/**
	 * Compare a subject string against an operator and value.
	 *
	 * @param string $subject  Value extracted from context.
	 * @param string $operator Comparison operator.
	 * @param string $value    Rule value to compare against.
	 * @return bool
	 */
	private function compare( string $subject, string $operator, string $value ): bool {
		switch ( $operator ) {
			case 'equals':
				return strtolower( $subject ) === strtolower( $value );

			case 'not_equals':
				return strtolower( $subject ) !== strtolower( $value );

			case 'contains':
				return stripos( $subject, $value ) !== false;

			case 'not_contains':
				return stripos( $subject, $value ) === false;

			case 'starts_with':
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return stripos( $subject, $value ) === 0;

			case 'ends_with':
				return strlen( $value ) > 0
					&& substr_compare( strtolower( $subject ), strtolower( $value ), -strlen( $value ) ) === 0;

			case 'matches':
				// Validate regex before use to prevent errors.
				if ( @preg_match( $value, '' ) === false ) {
					return false;
				}
				// Limit backtracking to prevent ReDoS.
				$prev_limit = ini_get( 'pcre.backtrack_limit' );
				ini_set( 'pcre.backtrack_limit', '10000' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
				$result = (bool) preg_match( $value, $subject );
				ini_set( 'pcre.backtrack_limit', $prev_limit ); // phpcs:ignore WordPress.PHP.IniSet.Risky
				return $result;

			default:
				return false;
		}
	}

	// ------------------------------------------------------------------
	// Provider Instantiation
	// ----------------------------------------------------------------

	/**
	 * Create a provider by key (with caching).
	 *
	 * @param string $key Provider key.
	 * @return SWPM_Provider_Interface|null
	 */
	private function make_provider( string $key ): ?SWPM_Provider_Interface {
		static $cache = array();

		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		$all = $this->factory->get_all();

		if ( ! isset( $all[ $key ] ) ) {
			return null;
		}

		$class = $all[ $key ];

		if ( ! class_exists( $class ) ) {
			return null;
		}

		$cache[ $key ] = new $class();
		return $cache[ $key ];
	}

	// ------------------------------------------------------------------
	// Sanitization
	// ----------------------------------------------------------------

	/**
	 * Sanitize rules array.
	 *
	 * @param array $rules Raw rules.
	 * @return array Sanitized rules.
	 */
	private function sanitize_rules( array $rules ): array {
		$sanitized       = array();
		$valid_fields    = array( 'to', 'subject', 'from', 'header', 'source' );
		$valid_operators = array( 'contains', 'not_contains', 'equals', 'not_equals', 'starts_with', 'ends_with', 'matches' );

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$clean = array(
				'id'       => sanitize_key( $rule['id'] ?? wp_generate_password( 8, false ) ),
				'name'     => sanitize_text_field( $rule['name'] ?? '' ),
				'enabled'  => ! empty( $rule['enabled'] ),
				'priority' => max( 1, min( 100, (int) ( $rule['priority'] ?? 50 ) ) ),
				'provider' => sanitize_key( $rule['provider'] ?? '' ),
			);

			$conditions = array();
			if ( ! empty( $rule['conditions'] ) && is_array( $rule['conditions'] ) ) {
				foreach ( $rule['conditions'] as $cond ) {
					if ( ! is_array( $cond ) ) {
						continue;
					}

					$field    = sanitize_key( $cond['field'] ?? '' );
					$operator = sanitize_key( $cond['operator'] ?? 'contains' );

					if ( ! in_array( $field, $valid_fields, true ) || ! in_array( $operator, $valid_operators, true ) ) {
						continue;
					}

					$conditions[] = array(
						'field'    => $field,
						'operator' => $operator,
						'value'    => sanitize_text_field( $cond['value'] ?? '' ),
					);
				}
			}

			$clean['conditions'] = $conditions;
			$sanitized[]         = $clean;
		}

		return $sanitized;
	}

	// ------------------------------------------------------------------
	// AJAX Handlers
	// ----------------------------------------------------------------

	/**
	 * AJAX: Save routing rules.
	 */
	public function ajax_save_rules(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swpmail' ) ) );
		}

		$raw   = isset( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : '[]'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rules = json_decode( $raw, true );

		if ( ! is_array( $rules ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid rules data.', 'swpmail' ) ) );
		}

		// Also persist the toggle if provided.
		if ( isset( $_POST['enabled'] ) ) {
			update_option( 'swpm_enable_smart_routing', ! empty( $_POST['enabled'] ), false );
		}

		$this->save_rules( $rules );

		wp_send_json_success(
			array(
				'message' => __( 'Routing rules saved.', 'swpmail' ),
				'rules'   => $this->get_rules(),
			)
		);
	}

	/**
	 * AJAX: Toggle smart routing on/off.
	 */
	public function ajax_save_toggle(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swpmail' ) ) );
		}

		$enabled = ! empty( $_POST['enabled'] );
		update_option( 'swpm_enable_smart_routing', $enabled, false );

		wp_send_json_success( array( 'enabled' => $enabled ) );
	}

	/**
	 * AJAX: Test routing for a given email context.
	 */
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	/**
	 * Ajax test routing.
	 */
	public function ajax_test_routing(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swpmail' ) ) );
		}

		$context = array(
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			'to'      => sanitize_email( $_POST['to'] ?? '' ),
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			'subject' => sanitize_text_field( $_POST['subject'] ?? '' ),
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			'from'    => sanitize_email( $_POST['from'] ?? '' ),
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			'source'  => sanitize_text_field( $_POST['source'] ?? '' ),
			'headers' => array(),
		);

		// Temporarily enable routing for the test.
		$was_enabled = get_option( 'swpm_enable_smart_routing', false );
		update_option( 'swpm_enable_smart_routing', true );

		$provider = $this->resolve( $context );

		if ( ! $was_enabled ) {
			update_option( 'swpm_enable_smart_routing', $was_enabled );
		}

		$rules   = $this->get_rules();
		$matched = array();
		foreach ( $rules as $rule ) {
			if ( ! empty( $rule['enabled'] ) && $this->evaluate_rule( $rule, $context ) ) {
				$matched[] = $rule;
			}
		}

		$default_key   = get_option( 'swpm_mail_provider', 'phpmail' );
		$default_label = ucfirst( $default_key );

		if ( $provider ) {
			wp_send_json_success(
				array(
					'routed'        => true,
					'provider_key'  => $provider->get_key(),
					'provider_name' => $provider->get_label(),
					'matched_rules' => wp_list_pluck( $matched, 'name' ),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'routed'        => false,
					'provider_key'  => $default_key,
					'provider_name' => $default_label . ' (' . __( 'default', 'swpmail' ) . ')',
					'matched_rules' => array(),
				)
			);
		}
	}
}
