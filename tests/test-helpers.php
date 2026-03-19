<?php
/**
 * Tests for helper functions (includes/helpers.php).
 *
 * @package SWPMail\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use Brain\Monkey\Functions;

// Load the helpers file under test.
require_once SWPM_PLUGIN_DIR . 'includes/helpers.php';

class Test_Helpers extends SWPM_Test_Case {

	/* ==================================================================
	 * swpm_sanitize_header_value
	 * ================================================================*/

	public function test_sanitize_header_value_strips_crlf(): void {
		$this->assertSame(
			'HelloWorld',
			swpm_sanitize_header_value( "Hello\r\nWorld" )
		);
	}

	public function test_sanitize_header_value_strips_null_bytes(): void {
		$this->assertSame(
			'clean',
			swpm_sanitize_header_value( "cle\x00an" )
		);
	}

	public function test_sanitize_header_value_keeps_normal_text(): void {
		$this->assertSame(
			'Normal Header Value 123',
			swpm_sanitize_header_value( 'Normal Header Value 123' )
		);
	}

	public function test_sanitize_header_value_strips_all_control_characters(): void {
		$this->assertSame(
			'AB',
			swpm_sanitize_header_value( "\x01A\x0fB\x1f" )
		);
	}

	/* ==================================================================
	 * swpm_is_safe_url
	 * ================================================================*/

	public function test_safe_url_rejects_http(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$this->assertFalse( swpm_is_safe_url( 'http://example.com' ) );
	}

	public function test_safe_url_rejects_ftp(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$this->assertFalse( swpm_is_safe_url( 'ftp://example.com' ) );
	}

	public function test_safe_url_rejects_empty_host(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$this->assertFalse( swpm_is_safe_url( 'https://' ) );
	}

	/* ==================================================================
	 * swpm_encrypt / swpm_decrypt round-trip
	 * ================================================================*/

	public function test_encrypt_decrypt_roundtrip(): void {
		Functions\when( 'wp_salt' )->justReturn( 'test-salt-value-for-unit-tests' );

		$plain  = 'my secret API key';
		$cipher = swpm_encrypt( $plain );

		$this->assertNotEmpty( $cipher );
		$this->assertNotSame( $plain, $cipher );

		$decrypted = swpm_decrypt( $cipher );
		$this->assertSame( $plain, $decrypted );
	}

	public function test_encrypt_returns_empty_for_empty_input(): void {
		$this->assertSame( '', swpm_encrypt( '' ) );
	}

	public function test_decrypt_returns_empty_for_empty_input(): void {
		$this->assertSame( '', swpm_decrypt( '' ) );
	}

	public function test_decrypt_returns_empty_for_tampered_ciphertext(): void {
		Functions\when( 'wp_salt' )->justReturn( 'test-salt-value-for-unit-tests' );

		$cipher  = swpm_encrypt( 'secret' );
		// Tamper with the base64 payload.
		$tampered = base64_encode( str_repeat( 'X', 60 ) );

		$this->assertSame( '', swpm_decrypt( $tampered ) );
	}

	public function test_encrypt_produces_unique_ciphertexts(): void {
		Functions\when( 'wp_salt' )->justReturn( 'test-salt' );

		$a = swpm_encrypt( 'same-value' );
		$b = swpm_encrypt( 'same-value' );

		// Different IVs → different ciphertexts.
		$this->assertNotSame( $a, $b );

		// But both decrypt to the same value.
		$this->assertSame( 'same-value', swpm_decrypt( $a ) );
		$this->assertSame( 'same-value', swpm_decrypt( $b ) );
	}

	/* ==================================================================
	 * swpm_get_constant_map
	 * ================================================================*/

	public function test_constant_map_strips_swpm_prefix_and_enc_suffix(): void {
		$map = swpm_get_constant_map();

		// swpm_smtp_password_enc → SWPM_SMTP_PASSWORD (not _ENC).
		$this->assertArrayHasKey( 'swpm_smtp_password_enc', $map );
		$this->assertSame( 'SWPM_SMTP_PASSWORD', $map['swpm_smtp_password_enc'] );
	}

	public function test_constant_map_simple_option(): void {
		$map = swpm_get_constant_map();

		$this->assertArrayHasKey( 'swpm_mail_provider', $map );
		$this->assertSame( 'SWPM_MAIL_PROVIDER', $map['swpm_mail_provider'] );
	}
}
