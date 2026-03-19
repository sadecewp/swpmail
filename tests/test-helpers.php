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
	 * Swpm_sanitize_header_value
	 * ================================================================*/




	/**
	 * Test sanitize header value strips crlf.
	 */
	public function test_sanitize_header_value_strips_crlf(): void {
		$this->assertSame(
			'HelloWorld',
			swpm_sanitize_header_value( "Hello\r\nWorld" )
		);
	}




	/**
	 * Test sanitize header value strips null bytes.
	 */
	public function test_sanitize_header_value_strips_null_bytes(): void {
		$this->assertSame(
			'clean',
			swpm_sanitize_header_value( "cle\x00an" )
		);
	}




	/**
	 * Test sanitize header value keeps normal text.
	 */
	public function test_sanitize_header_value_keeps_normal_text(): void {
		$this->assertSame(
			'Normal Header Value 123',
			swpm_sanitize_header_value( 'Normal Header Value 123' )
		);
	}




	/**
	 * Test sanitize header value strips all control characters.
	 */
	public function test_sanitize_header_value_strips_all_control_characters(): void {
		$this->assertSame(
			'AB',
			swpm_sanitize_header_value( "\x01A\x0fB\x1f" )
		);
	}

	/* ==================================================================
	 * Swpm_is_safe_url
	 * ================================================================*/




	/**
	 * Test safe url rejects http.
	 */
	public function test_safe_url_rejects_http(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$this->assertFalse( swpm_is_safe_url( 'http://example.com' ) );
	}




	/**
	 * Test safe url rejects ftp.
	 */
	public function test_safe_url_rejects_ftp(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$this->assertFalse( swpm_is_safe_url( 'ftp://example.com' ) );
	}




	/**
	 * Test safe url rejects empty host.
	 */
	public function test_safe_url_rejects_empty_host(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$this->assertFalse( swpm_is_safe_url( 'https://' ) );
	}

	/* ==================================================================
	 * Swpm_encrypt / swpm_decrypt round-trip
	 * ================================================================*/




	/**
	 * Test encrypt decrypt roundtrip.
	 */
	public function test_encrypt_decrypt_roundtrip(): void {
		Functions\when( 'wp_salt' )->justReturn( 'test-salt-value-for-unit-tests' );

		$plain  = 'my secret API key';
		$cipher = swpm_encrypt( $plain );

		$this->assertNotEmpty( $cipher );
		$this->assertNotSame( $plain, $cipher );

		$decrypted = swpm_decrypt( $cipher );
		$this->assertSame( $plain, $decrypted );
	}




	/**
	 * Test encrypt returns empty for empty input.
	 */
	public function test_encrypt_returns_empty_for_empty_input(): void {
		$this->assertSame( '', swpm_encrypt( '' ) );
	}




	/**
	 * Test decrypt returns empty for empty input.
	 */
	public function test_decrypt_returns_empty_for_empty_input(): void {
		$this->assertSame( '', swpm_decrypt( '' ) );
	}




	/**
	 * Test decrypt returns empty for tampered ciphertext.
	 */
	public function test_decrypt_returns_empty_for_tampered_ciphertext(): void {
		Functions\when( 'wp_salt' )->justReturn( 'test-salt-value-for-unit-tests' );

		$cipher  = swpm_encrypt( 'secret' );
		// Tamper with the base64 payload.
		$tampered = base64_encode( str_repeat( 'X', 60 ) );

		$this->assertSame( '', swpm_decrypt( $tampered ) );
	}




	/**
	 * Test encrypt produces unique ciphertexts.
	 */
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
	 * Swpm_get_constant_map
	 * ================================================================*/




	/**
	 * Test constant map strips swpm prefix and enc suffix.
	 */
	public function test_constant_map_strips_swpm_prefix_and_enc_suffix(): void {
		$map = swpm_get_constant_map();

		// swpm_smtp_password_enc → SWPM_SMTP_PASSWORD (not _ENC).
		$this->assertArrayHasKey( 'swpm_smtp_password_enc', $map );
		$this->assertSame( 'SWPM_SMTP_PASSWORD', $map['swpm_smtp_password_enc'] );
	}




	/**
	 * Test constant map simple option.
	 */
	public function test_constant_map_simple_option(): void {
		$map = swpm_get_constant_map();

		$this->assertArrayHasKey( 'swpm_mail_provider', $map );
		$this->assertSame( 'SWPM_MAIL_PROVIDER', $map['swpm_mail_provider'] );
	}
}
