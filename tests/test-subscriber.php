<?php
/**
 * Tests for SWPM_Subscriber (includes/core/class-subscriber.php).
 *
 * @package SWPMail\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use Brain\Monkey\Functions;

// Stub WP_Error so the class file can be loaded.
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public string $code;
		public string $message;






		/**
		 * Constructor.
		 *
		 * @param string $code Code.
		 * @param string $message Message.
		 */
		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}





		/**
		 * Get error code.
		 *
		 * @return string
		 */
		public function get_error_code(): string {
			return $this->code;
		}





		/**
		 * Get error message.
		 *
		 * @return string
		 */
		public function get_error_message(): string {
			return $this->message;
		}
	}
}

// Stub SWPM_Ajax_Handler for get_client_ip().
if ( ! class_exists( 'SWPM_Ajax_Handler' ) ) {
	class SWPM_Ajax_Handler {





		/**
		 * Get client ip.
		 *
		 * @return string
		 */
		public static function get_client_ip(): string {
			return '127.0.0.1';
		}
	}
}

// Stub SWPMail class.
if ( ! class_exists( 'SWPMail' ) ) {
	class SWPMail {







		/**
		 * Get.
		 *
		 * @param string $key Key.
		 *
		 * @return ?object
		 */
		public static function get( string $key ): ?object {
			return null;
		}
	}
}

// Stub swpm_log to avoid loading full helpers twice if already loaded.
if ( ! function_exists( 'swpm_log' ) ) {







	/**
	 * Swpm log.
	 *
	 * @param string $level Level.
	 * @param string $message Message.
	 * @param array $context Context.
	 */
	function swpm_log( string $level, string $message, array $context = array() ): void {}
}

require_once SWPM_PLUGIN_DIR . 'includes/core/class-swpm-subscriber.php';

class Test_Subscriber extends SWPM_Test_Case {

	private object $wpdb;




	/**
	 * Setup.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create a mock wpdb.
		$this->wpdb = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';

		$GLOBALS['wpdb'] = $this->wpdb;
	}




	/**
	 * Teardown.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}






	/**
	 * Make subscriber.
	 *
	 * @return SWPM_Subscriber
	 */
	private function make_subscriber(): SWPM_Subscriber {
		return new SWPM_Subscriber();
	}

	/* ==================================================================
	 * Create()
	 * ================================================================*/




	/**
	 * Test create returns error for invalid email.
	 */
	public function test_create_returns_error_for_invalid_email(): void {
		Functions\when( 'sanitize_email' )->justReturn( '' );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'is_email' )->justReturn( false );
		Functions\when( '__' )->returnArg();

		$sub    = $this->make_subscriber();
		$result = $sub->create( 'not-an-email' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_email', $result->get_error_code() );
	}




	/**
	 * Test create rejects disposable email.
	 */
	public function test_create_rejects_disposable_email(): void {
		Functions\when( 'sanitize_email' )->justReturn( 'user@mailinator.com' );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'is_email' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( function ( $tag, $value ) {
			return $value;
		} );
		Functions\when( '__' )->returnArg();

		$sub    = $this->make_subscriber();
		$result = $sub->create( 'user@mailinator.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'subscribe_failed', $result->get_error_code() );
	}




	/**
	 * Test create inserts pending when double optin.
	 */
	public function test_create_inserts_pending_when_double_optin(): void {
		$email = 'real@example.com';

		Functions\when( 'sanitize_email' )->justReturn( $email );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'is_email' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( function ( $tag, $value ) {
			return $value;
		} );
		Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
			if ( 'swpm_double_opt_in' === $key ) {
				return true;
			}
			return $default;
		} );
		Functions\when( 'current_time' )->justReturn( '2026-01-01 00:00:00' );
		Functions\when( 'do_action' )->justReturn( null );

		// get_by_email calls prepare() then get_row().
		$this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SELECT * FROM wp_swpm_subscribers WHERE email = ...' );
		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturnNull();

		// insert() should be called with status = 'pending'.
		$this->wpdb->shouldReceive( 'insert' )->once()->with(
			'wp_swpm_subscribers',
			Mockery::on( function ( $data ) {
				return 'pending' === $data['status']
					   && 'real@example.com' === $data['email'];
			} ),
			Mockery::type( 'array' )
		)->andReturn( 1 );

		$this->wpdb->insert_id = 42;

		$sub    = $this->make_subscriber();
		$result = $sub->create( $email );

		$this->assertSame( 42, $result );
	}

	/* ==================================================================
	 * Count()
	 * ================================================================*/




	/**
	 * Test count returns integer.
	 */
	public function test_count_returns_integer(): void {
		$this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SELECT COUNT(*) FROM wp_swpm_subscribers' );
		$this->wpdb->shouldReceive( 'get_var' )->once()->andReturn( '15' );

		$sub = $this->make_subscriber();
		$this->assertSame( 15, $sub->count() );
	}




	/**
	 * Test count filters by status.
	 */
	public function test_count_filters_by_status(): void {
		$this->wpdb->shouldReceive( 'prepare' )->andReturn(
			"SELECT COUNT(*) FROM wp_swpm_subscribers WHERE status = 'confirmed'"
		);
		$this->wpdb->shouldReceive( 'get_var' )->once()->andReturn( '7' );
		Functions\when( 'sanitize_key' )->returnArg();

		$sub = $this->make_subscriber();
		$this->assertSame( 7, $sub->count( 'confirmed' ) );
	}

	/* ==================================================================
	 * Get_by_email()
	 * ================================================================*/




	/**
	 * Test get by email returns null when not found.
	 */
	public function test_get_by_email_returns_null_when_not_found(): void {
		Functions\when( 'sanitize_email' )->justReturn( 'test@example.com' );
		$this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SELECT * ...' );
		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturnNull();

		$sub = $this->make_subscriber();
		$this->assertNull( $sub->get_by_email( 'test@example.com' ) );
	}




	/**
	 * Test get by email returns object when found.
	 */
	public function test_get_by_email_returns_object_when_found(): void {
		Functions\when( 'sanitize_email' )->justReturn( 'found@example.com' );
		$row = (object) array(
			'id'     => 1,
			'email'  => 'found@example.com',
			'status' => 'confirmed',
		);
		$this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SELECT * ...' );
		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturn( $row );

		$sub = $this->make_subscriber();
		$this->assertSame( 'confirmed', $sub->get_by_email( 'found@example.com' )->status );
	}

	/* ==================================================================
	 * Delete()
	 * ================================================================*/




	/**
	 * Test delete returns true when row deleted.
	 */
	public function test_delete_returns_true_when_row_deleted(): void {
		$this->wpdb->shouldReceive( 'delete' )->once()->with(
			'wp_swpm_subscribers',
			array( 'id' => 5 ),
			array( '%d' )
		)->andReturn( 1 );

		$sub = $this->make_subscriber();
		$this->assertTrue( $sub->delete( 5 ) );
	}




	/**
	 * Test delete returns false when no row.
	 */
	public function test_delete_returns_false_when_no_row(): void {
		$this->wpdb->shouldReceive( 'delete' )->once()->andReturn( 0 );

		$sub = $this->make_subscriber();
		$this->assertFalse( $sub->delete( 999 ) );
	}
}
