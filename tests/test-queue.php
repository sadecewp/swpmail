<?php
/**
 * Tests for SWPM_Queue (includes/core/class-queue.php).
 *
 * @package SWPMail\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use Brain\Monkey\Functions;

// Stub SWPMail class if not already loaded.
if ( ! class_exists( 'SWPMail' ) ) {
	class SWPMail {
		private static array $instances = array();
		public static function get( string $key ): ?object {
			return self::$instances[ $key ] ?? null;
		}
		public static function set( string $key, object $obj ): void {
			self::$instances[ $key ] = $obj;
		}
	}
}

if ( ! function_exists( 'swpm' ) ) {
	function swpm( string $key ): ?object {
		return SWPMail::get( $key );
	}
}

if ( ! function_exists( 'swpm_log' ) ) {
	function swpm_log( string $level, string $message, array $context = array() ): void {}
}

// Stub classes.
if ( ! class_exists( 'SWPM_Tracker' ) ) {
	class SWPM_Tracker {
		public function inject_tracking( string $body, string $to, string $subject, int $id ): string {
			return $body;
		}
	}
}

if ( ! class_exists( 'SWPM_Router' ) ) {
	// Already loaded by another test, skip.
}

if ( ! interface_exists( 'SWPM_Provider_Interface' ) ) {
	interface SWPM_Provider_Interface {}
}

require_once SWPM_PLUGIN_DIR . 'includes/core/class-queue.php';

class Test_Queue extends SWPM_Test_Case {

	private object $wpdb;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';
		$this->wpdb->insert_id = 0;

		$GLOBALS['wpdb'] = $this->wpdb;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/* ==================================================================
	 * enqueue()
	 * ================================================================*/

	public function test_enqueue_inserts_and_returns_id(): void {
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'current_time' )->justReturn( '2026-01-01 12:00:00' );

		$this->wpdb->shouldReceive( 'insert' )->once()->with(
			'wp_swpm_queue',
			Mockery::on( function ( $data ) {
				return 'test@example.com' === $data['to_email']
					   && 'Hello' === $data['subject']
					   && 'pending' === $data['status'];
			} ),
			Mockery::type( 'array' )
		)->andReturn( 1 );

		$this->wpdb->insert_id = 99;

		$queue  = new SWPM_Queue();
		$result = $queue->enqueue( 'test@example.com', 'Hello', '<p>Body</p>' );

		$this->assertSame( 99, $result );
	}

	public function test_enqueue_returns_false_on_failure(): void {
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'current_time' )->justReturn( '2026-01-01 12:00:00' );

		$this->wpdb->shouldReceive( 'insert' )->once()->andReturn( false );

		$queue  = new SWPM_Queue();
		$result = $queue->enqueue( 'test@example.com', 'Hello', '<p>Body</p>' );

		$this->assertFalse( $result );
	}

	public function test_enqueue_includes_optional_fields(): void {
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'current_time' )->justReturn( '2026-01-01 12:00:00' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$this->wpdb->shouldReceive( 'insert' )->once()->with(
			'wp_swpm_queue',
			Mockery::on( function ( $data ) {
				return isset( $data['subscriber_id'] )
					   && 5 === $data['subscriber_id']
					   && isset( $data['template_id'] )
					   && 'welcome' === $data['template_id'];
			} ),
			Mockery::type( 'array' )
		)->andReturn( 1 );

		$this->wpdb->insert_id = 10;

		$queue  = new SWPM_Queue();
		$result = $queue->enqueue(
			'user@example.com',
			'Welcome',
			'<p>Welcome!</p>',
			'welcome',
			5,
			array( 'X-Custom: 1' )
		);

		$this->assertSame( 10, $result );
	}

	/* ==================================================================
	 * get_stats()
	 * ================================================================*/

	public function test_get_stats_returns_all_statuses(): void {
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array(
			(object) array( 'status' => 'pending', 'count' => 10 ),
			(object) array( 'status' => 'sent', 'count' => 200 ),
			(object) array( 'status' => 'failed', 'count' => 3 ),
		) );

		$queue = new SWPM_Queue();
		$stats = $queue->get_stats();

		$this->assertSame( 10, $stats['pending'] );
		$this->assertSame( 200, $stats['sent'] );
		$this->assertSame( 3, $stats['failed'] );
		$this->assertSame( 0, $stats['sending'] );
	}

	/* ==================================================================
	 * reschedule_with_delay()
	 * ================================================================*/

	public function test_reschedule_updates_scheduled_at(): void {
		$this->wpdb->shouldReceive( 'update' )->once()->with(
			'wp_swpm_queue',
			Mockery::on( function ( $data ) {
				return 'pending' === $data['status']
					   && ! empty( $data['scheduled_at'] );
			} ),
			array( 'id' => 42 ),
			array( '%s', '%s' ),
			array( '%d' )
		)->andReturn( 1 );

		$queue = new SWPM_Queue();
		$queue->reschedule_with_delay( 42, 300 );

		// If we reach here, Mockery confirmed the update call.
		$this->assertTrue( true );
	}
}
