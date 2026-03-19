<?php
/**
 * Tests for SWPM_Router::evaluate_rule() and comparison logic.
 *
 * @package SWPMail\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use Brain\Monkey\Functions;

// Stub SWPM_Provider_Factory.
if ( ! class_exists( 'SWPM_Provider_Factory' ) ) {
	class SWPM_Provider_Factory {
		public function get_all(): array {
			return array();
		}
	}
}

// Stub SWPM_Provider_Interface.
if ( ! interface_exists( 'SWPM_Provider_Interface' ) ) {
	interface SWPM_Provider_Interface {}
}

// Stub dependencies.
if ( ! function_exists( 'swpm_log' ) ) {
	function swpm_log( string $level, string $message, array $context = array() ): void {}
}

require_once SWPM_PLUGIN_DIR . 'includes/core/class-router.php';

class Test_Router extends SWPM_Test_Case {

	private SWPM_Router $router;

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
			if ( 'swpm_from_email' === $key ) {
				return 'noreply@example.com';
			}
			return $default;
		} );

		$factory      = new SWPM_Provider_Factory();
		$this->router = new SWPM_Router( $factory );
	}

	/* ==================================================================
	 * evaluate_rule — basic operators
	 * ================================================================*/

	public function test_contains_operator_matches(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'to', 'operator' => 'contains', 'value' => '@company.com' ),
			),
		);
		$context = array( 'to' => 'user@company.com' );

		$this->assertTrue( $this->router->evaluate_rule( $rule, $context ) );
	}

	public function test_contains_operator_misses(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'to', 'operator' => 'contains', 'value' => '@company.com' ),
			),
		);
		$context = array( 'to' => 'user@other.com' );

		$this->assertFalse( $this->router->evaluate_rule( $rule, $context ) );
	}

	public function test_equals_operator(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'subject', 'operator' => 'equals', 'value' => 'Welcome' ),
			),
		);

		$this->assertTrue( $this->router->evaluate_rule( $rule, array( 'subject' => 'welcome' ) ) );
		$this->assertFalse( $this->router->evaluate_rule( $rule, array( 'subject' => 'Goodbye' ) ) );
	}

	public function test_not_equals_operator(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'subject', 'operator' => 'not_equals', 'value' => 'Welcome' ),
			),
		);

		$this->assertTrue( $this->router->evaluate_rule( $rule, array( 'subject' => 'Goodbye' ) ) );
		$this->assertFalse( $this->router->evaluate_rule( $rule, array( 'subject' => 'welcome' ) ) );
	}

	public function test_starts_with_operator(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'subject', 'operator' => 'starts_with', 'value' => '[Alert]' ),
			),
		);

		$this->assertTrue( $this->router->evaluate_rule( $rule, array( 'subject' => '[Alert] Server Down' ) ) );
		$this->assertFalse( $this->router->evaluate_rule( $rule, array( 'subject' => 'Server Down [Alert]' ) ) );
	}

	public function test_ends_with_operator(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'to', 'operator' => 'ends_with', 'value' => '@vip.com' ),
			),
		);

		$this->assertTrue( $this->router->evaluate_rule( $rule, array( 'to' => 'boss@vip.com' ) ) );
		$this->assertFalse( $this->router->evaluate_rule( $rule, array( 'to' => 'user@regular.com' ) ) );
	}

	public function test_not_contains_operator(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'subject', 'operator' => 'not_contains', 'value' => 'spam' ),
			),
		);

		$this->assertTrue( $this->router->evaluate_rule( $rule, array( 'subject' => 'Hello' ) ) );
		$this->assertFalse( $this->router->evaluate_rule( $rule, array( 'subject' => 'This is spam' ) ) );
	}

	public function test_matches_regex_operator(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'to', 'operator' => 'matches', 'value' => '/^admin@/i' ),
			),
		);

		$this->assertTrue( $this->router->evaluate_rule( $rule, array( 'to' => 'admin@example.com' ) ) );
		$this->assertFalse( $this->router->evaluate_rule( $rule, array( 'to' => 'user@example.com' ) ) );
	}

	public function test_matches_returns_false_for_invalid_regex(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'to', 'operator' => 'matches', 'value' => '/[invalid' ),
			),
		);

		$this->assertFalse( $this->router->evaluate_rule( $rule, array( 'to' => 'anything' ) ) );
	}

	public function test_unknown_operator_returns_false(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'to', 'operator' => 'greaterThan', 'value' => '5' ),
			),
		);

		$this->assertFalse( $this->router->evaluate_rule( $rule, array( 'to' => '10' ) ) );
	}

	/* ==================================================================
	 * evaluate_rule — multiple conditions (AND logic)
	 * ================================================================*/

	public function test_all_conditions_must_match(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'to', 'operator' => 'contains', 'value' => '@company.com' ),
				array( 'field' => 'subject', 'operator' => 'starts_with', 'value' => '[Urgent]' ),
			),
		);

		// Both match.
		$this->assertTrue( $this->router->evaluate_rule( $rule, array(
			'to'      => 'admin@company.com',
			'subject' => '[Urgent] Disk full',
		) ) );

		// Only first matches.
		$this->assertFalse( $this->router->evaluate_rule( $rule, array(
			'to'      => 'admin@company.com',
			'subject' => 'Normal mail',
		) ) );

		// Only second matches.
		$this->assertFalse( $this->router->evaluate_rule( $rule, array(
			'to'      => 'user@other.com',
			'subject' => '[Urgent] Disk full',
		) ) );
	}

	public function test_empty_conditions_never_matches(): void {
		$rule = array( 'conditions' => array() );

		$this->assertFalse( $this->router->evaluate_rule( $rule, array( 'to' => 'any' ) ) );
	}

	/* ==================================================================
	 * Field extraction
	 * ================================================================*/

	public function test_from_field_uses_context_or_option(): void {
		// When from is in context.
		$rule = array(
			'conditions' => array(
				array( 'field' => 'from', 'operator' => 'equals', 'value' => 'sender@example.com' ),
			),
		);
		$this->assertTrue( $this->router->evaluate_rule( $rule, array(
			'from' => 'sender@example.com',
		) ) );

		// When from is NOT in context, falls back to get_option.
		$this->assertTrue( $this->router->evaluate_rule( $rule, array(
			'from' => '',
			'to'   => 'whoever',
		) ) === false ); // Default is noreply@example.com.
	}

	public function test_header_field_flattens_array(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'header', 'operator' => 'contains', 'value' => 'X-Priority: 1' ),
			),
		);

		$this->assertTrue( $this->router->evaluate_rule( $rule, array(
			'headers' => array( 'Content-Type: text/html', 'X-Priority: 1' ),
		) ) );
	}

	public function test_source_field(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'source', 'operator' => 'equals', 'value' => 'woocommerce' ),
			),
		);

		$this->assertTrue( $this->router->evaluate_rule( $rule, array( 'source' => 'woocommerce' ) ) );
		$this->assertFalse( $this->router->evaluate_rule( $rule, array( 'source' => 'contact-form-7' ) ) );
	}

	public function test_unknown_field_returns_empty(): void {
		$rule = array(
			'conditions' => array(
				array( 'field' => 'nonexistent', 'operator' => 'equals', 'value' => '' ),
			),
		);

		// Empty field equals empty value → true.
		$this->assertTrue( $this->router->evaluate_rule( $rule, array() ) );
	}
}
