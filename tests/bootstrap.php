<?php
/**
 * PHPUnit bootstrap for SWPMail unit tests.
 *
 * Uses Brain\Monkey to mock WordPress functions so tests run
 * without a real WordPress installation.
 *
 * @package SWPMail\Tests
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

use Brain\Monkey;

// WordPress constants the plugin expects.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'MB_IN_BYTES' ) ) {
	define( 'MB_IN_BYTES', 1048576 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'SWPM_PLUGIN_DIR' ) ) {
	define( 'SWPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'SWPM_PLUGIN_URL' ) ) {
	define( 'SWPM_PLUGIN_URL', 'https://example.com/wp-content/plugins/swpmail/' );
}
if ( ! defined( 'SWPM_VERSION' ) ) {
	define( 'SWPM_VERSION', '1.1.0' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

/**
 * Base test case with Brain\Monkey setup/teardown.
 */
abstract class SWPM_Test_Case extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
