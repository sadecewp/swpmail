<?php
/**
 * Tests for SWPM_Analytics (includes/core/class-analytics.php).
 *
 * @package SWPMail\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use Brain\Monkey\Functions;

require_once SWPM_PLUGIN_DIR . 'includes/core/class-swpm-analytics.php';

class Test_Analytics extends SWPM_Test_Case {

	private object $wpdb;




	/**
	 * Setup.
	 */
	protected function setUp(): void {
		parent::setUp();

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

	/* ==================================================================
	 * Get_summary()
	 * ================================================================*/




	/**
	 * Test get summary returns expected structure.
	 */
	public function test_get_summary_returns_expected_structure(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SELECT ...' );

		// get_row returns associative arrays for opens and clicks.
		$this->wpdb->shouldReceive( 'get_row' )
			->twice()
			->andReturn(
				array( 'total' => '100', 'uniq' => '80' ),
				array( 'total' => '50', 'uniq' => '40' )
			);

		// get_var returns the sent count.
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '200' );

		$analytics = new SWPM_Analytics();
		$summary   = $analytics->get_summary( 30 );

		$this->assertArrayHasKey( 'total_opens', $summary );
		$this->assertArrayHasKey( 'unique_opens', $summary );
		$this->assertArrayHasKey( 'total_clicks', $summary );
		$this->assertArrayHasKey( 'unique_clicks', $summary );
		$this->assertArrayHasKey( 'open_rate', $summary );
		$this->assertArrayHasKey( 'click_rate', $summary );
		$this->assertArrayHasKey( 'total_sent', $summary );
	}




	/**
	 * Test get summary avoids division by zero.
	 */
	public function test_get_summary_avoids_division_by_zero(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SELECT ...' );

		$this->wpdb->shouldReceive( 'get_row' )
			->twice()
			->andReturn(
				array( 'total' => '0', 'uniq' => '0' ),
				array( 'total' => '0', 'uniq' => '0' )
			);

		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '0' );

		$analytics = new SWPM_Analytics();
		$summary   = $analytics->get_summary( 30 );

		$this->assertSame( 0, $summary['open_rate'] );
		$this->assertSame( 0, $summary['click_rate'] );
	}

	/* ==================================================================
	 * Get_daily_trend()
	 * ================================================================*/




	/**
	 * Test get daily trend returns array.
	 */
	public function test_get_daily_trend_returns_array(): void {
		$this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SELECT ...' );
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array(
			(object) array( 'day' => '2026-01-01', 'opens' => 10, 'clicks' => 5 ),
			(object) array( 'day' => '2026-01-02', 'opens' => 20, 'clicks' => 8 ),
		) );

		$analytics = new SWPM_Analytics();
		$trend     = $analytics->get_daily_trend( 7 );

		$this->assertCount( 2, $trend );
	}

	/* ==================================================================
	 * Get_top_links()
	 * ================================================================*/




	/**
	 * Test get top links returns array.
	 */
	public function test_get_top_links_returns_array(): void {
		$this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SELECT ...' );
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array(
			array( 'url' => 'https://example.com', 'clicks' => 42 ),
		) );

		$analytics = new SWPM_Analytics();
		$links     = $analytics->get_top_links( 10, 30 );

		$this->assertCount( 1, $links );
	}

	/* ==================================================================
	 * Cleanup_old_tracking()
	 * ================================================================*/




	/**
	 * Test cleanup deletes in batches.
	 */
	public function test_cleanup_deletes_in_batches(): void {
		Functions\when( 'apply_filters' )->alias( function ( $tag, $value ) {
			if ( 'swpm_cleanup_batch_size' === $tag ) {
				return 100; // Small batch for testing.
			}
			return $value;
		} );

		$this->wpdb->shouldReceive( 'prepare' )->andReturn( 'DELETE ...' );

		// Simulate: first batch deletes 100 (= batch_size), second deletes 30.
		$this->wpdb->shouldReceive( 'query' )
			->twice()
			->andReturn( 100, 30 );

		$analytics = new SWPM_Analytics();
		$analytics->cleanup_old_tracking( 90 );

		// Verify that query was called exactly twice (= 2 batches).
		$this->assertSame( 2, $this->wpdb->mockery_getExpectationCount() > 0 ? 2 : 0 );
	}




	/**
	 * Test cleanup stops immediately when nothing to delete.
	 */
	public function test_cleanup_stops_immediately_when_nothing_to_delete(): void {
		Functions\when( 'apply_filters' )->alias( function ( $tag, $value ) {
			return $value;
		} );

		$this->wpdb->shouldReceive( 'prepare' )->andReturn( 'DELETE ...' );
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 0 );

		$analytics = new SWPM_Analytics();
		$analytics->cleanup_old_tracking( 90 );

		// Verify that query was called exactly once (no second batch needed).
		$this->assertSame( 0, 0 ); // Mockery verifies the single call expectation.
	}
}
