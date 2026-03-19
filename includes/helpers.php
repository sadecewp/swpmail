<?php
/**
 * Helper functions for SWPMail.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validate that a URL is safe for outbound requests (SSRF protection).
 *
 * Blocks private/reserved IP ranges and non-HTTPS schemes.
 *
 * @since 1.0.1
 * @param string $url URL to validate.
 * @return bool True if the URL is safe for outbound requests.
 */
function swpm_is_safe_url( string $url ): bool {
	$parsed = wp_parse_url( $url );

	if ( ! isset( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], array( 'https' ), true ) ) {
		return false;
	}

	$host = $parsed['host'] ?? '';
	if ( empty( $host ) ) {
		return false;
	}

	$ip = gethostbyname( $host );

	// gethostbyname returns the hostname on failure.
	if ( $ip === $host && ! filter_var( $host, FILTER_VALIDATE_IP ) ) {
		return false;
	}

	// Block private and reserved IP ranges.
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
		return false;
	}

	return true;
}

/**
 * Sanitize an email header value by stripping control characters.
 *
 * Prevents header injection attacks via \r\n sequences.
 *
 * @since 1.0.1
 * @param string $value Raw header value.
 * @return string Sanitized header value.
 */
function swpm_sanitize_header_value( string $value ): string {
	return preg_replace( '/[\r\n\x00-\x1f]/', '', $value );
}

/**
 * Validate and secure an attachment file for email sending.
 *
 * Checks file existence, readability, symlink safety, path traversal,
 * and enforces a maximum file size.
 *
 * @since 1.0.1
 * @param string $file     Absolute file path.
 * @param int    $max_size Maximum file size in bytes (default 25 MB).
 * @return string|false Real path on success, false if unsafe.
 */
function swpm_validate_attachment( string $file, int $max_size = 25 * MB_IN_BYTES ) {
	if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
		return false;
	}

	$real = realpath( $file );
	if ( ! $real || strpos( $real, realpath( ABSPATH ) ) !== 0 ) {
		return false;
	}

	if ( filesize( $real ) > $max_size ) {
		return false;
	}

	return $real;
}

/**
 * Encrypt a value using AES-256-CBC with HMAC-SHA256 authentication.
 *
 * Format: base64( HMAC[32] . IV[16] . ciphertext )
 *
 * @param string $value Plain text value.
 * @return string Base64-encoded authenticated encrypted string.
 */
function swpm_encrypt( string $value ): string {
	if ( empty( $value ) ) {
		return '';
	}
	$key    = hash( 'sha256', wp_salt( 'auth' ), true );
	$iv     = random_bytes( 16 );
	$cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
	if ( false === $cipher ) {
		return '';
	}
	$hmac = hash_hmac( 'sha256', $iv . $cipher, $key, true );
	return base64_encode( $hmac . $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * Decrypt a value encrypted with swpm_encrypt().
 *
 * Verifies HMAC before decrypting to prevent padding-oracle and
 * ciphertext-manipulation attacks.
 *
 * @param string $encrypted Base64-encoded authenticated encrypted string.
 * @return string Decrypted plain text.
 */
function swpm_decrypt( string $encrypted ): string {
	if ( empty( $encrypted ) ) {
		return '';
	}
	try {
		$key  = hash( 'sha256', wp_salt( 'auth' ), true );
		$data = base64_decode( $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		// Minimum length: 32 (HMAC) + 16 (IV) + 1 (ciphertext).
		if ( strlen( $data ) < 49 ) {
			// Attempt legacy (pre-HMAC) decryption for backward compatibility.
			return swpm_decrypt_legacy( $data, $key );
		}

		$hmac   = substr( $data, 0, 32 );
		$iv     = substr( $data, 32, 16 );
		$cipher = substr( $data, 48 );

		// Verify HMAC before decrypting (constant-time comparison).
		$calc_hmac = hash_hmac( 'sha256', $iv . $cipher, $key, true );
		if ( ! hash_equals( $hmac, $calc_hmac ) ) {
			// Try legacy format (IV + ciphertext, no HMAC) for migration.
			return swpm_decrypt_legacy( $data, $key );
		}

		$result = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return false !== $result ? $result : '';
	} catch ( \Exception $e ) {
		swpm_log( 'error', 'Decryption failed.' );
		return '';
	}
}

/**
 * Decrypt a legacy (pre-1.1) value that has no HMAC prefix.
 *
 * @param string $data Raw decoded bytes (IV + ciphertext).
 * @param string $key  Derived encryption key.
 * @return string
 */
function swpm_decrypt_legacy( string $data, string $key ): string {
	if ( strlen( $data ) < 17 ) {
		return '';
	}
	$iv     = substr( $data, 0, 16 );
	$cipher = substr( $data, 16 );
	$result = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
	return false !== $result ? $result : '';
}

/**
 * Central logging function.
 *
 * @param string $level   Log level: debug, info, warning, error.
 * @param string $message Log message.
 * @param array  $context Additional context (JSON encoded).
 */
function swpm_log( string $level, string $message, array $context = array() ): void {
	global $wpdb;

	$allowed = array( 'debug', 'info', 'warning', 'error' );
	$level   = in_array( $level, $allowed, true ) ? $level : 'info';

	if ( 'debug' === $level && ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) ) {
		return;
	}

	$table = $wpdb->prefix . 'swpm_logs';

	// Check if table exists (cached per request to avoid repeated SHOW TABLES queries).
	static $table_verified = false;
	if ( ! $table_verified ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);
		if ( ! $exists ) {
			return;
		}
		$table_verified = true;
	}

	$max_logs = (int) apply_filters( 'swpm_max_log_entries', 10000 );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$count = (int) $wpdb->get_var(
		$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
	);

	if ( $count >= $max_logs ) {
		$delete_count = (int) ( $max_logs * 0.1 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE id IN (
					SELECT id FROM (
						SELECT id FROM %i ORDER BY id ASC LIMIT %d
					) tmp
				)',
				$table,
				$table,
				$delete_count
			)
		);
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert(
		$table,
		array(
			'level'      => $level,
			'message'    => mb_substr( $message, 0, 65535 ),
			'context'    => ! empty( $context ) ? wp_json_encode( $context ) : null,
			'created_at' => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s' )
	);
}

/**
 * Global helper: swpm('mailer') returns the SWPM_Mailer instance.
 *
 * @param string $key Service key.
 * @return object|null
 */
function swpm( string $key ): ?object {
	return SWPMail::get( $key );
}

/**
 * Get the site logo URL from the active theme.
 *
 * @since 1.0.0
 * @return string
 */
function swpm_get_site_logo_url(): string {
	$id = get_theme_mod( 'custom_logo' );
	return $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : '';
}

/* =========================================================================
 * wp-config.php Constants Support
 * ========================================================================= */

/**
 * Return the constant-name map: option_key => CONSTANT_NAME.
 *
 * Convention:
 *  - Strip `swpm_` prefix.
 *  - Strip `_enc` suffix (constants hold plain text).
 *  - Uppercase and prefix with `SWPM_`.
 *
 * Usage in wp-config.php:
 *   define( 'SWPM_MAIL_PROVIDER', 'mailgun' );
 *   define( 'SWPM_SMTP_PASSWORD', 'plain-text-secret' );
 *
 * @return array<string, string>
 */
function swpm_get_constant_map(): array {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}

	$options = array(
		// Core identity.
		'swpm_mail_provider',
		'swpm_from_name',
		'swpm_from_email',
		'swpm_override_wp_mail',
		'swpm_backup_provider',
		// SMTP.
		'swpm_smtp_host',
		'swpm_smtp_port',
		'swpm_smtp_encryption',
		'swpm_smtp_username',
		'swpm_smtp_password_enc',
		// Mailgun.
		'swpm_mailgun_api_key_enc',
		'swpm_mailgun_domain',
		'swpm_mailgun_region',
		// SendGrid.
		'swpm_sendgrid_api_key_enc',
		// Postmark.
		'swpm_postmark_server_token_enc',
		'swpm_postmark_message_stream',
		// Brevo.
		'swpm_brevo_api_key_enc',
		// Amazon SES.
		'swpm_ses_access_key_enc',
		'swpm_ses_secret_key_enc',
		'swpm_ses_region',
		// Resend.
		'swpm_resend_api_key_enc',
		// SendLayer.
		'swpm_sendlayer_api_key_enc',
		// SMTP.com.
		'swpm_smtpcom_api_key_enc',
		'swpm_smtpcom_channel',
		// Gmail.
		'swpm_gmail_username',
		'swpm_gmail_app_password_enc',
		'swpm_gmail_oauth_client_id',
		'swpm_gmail_oauth_client_secret_enc',
		// Outlook.
		'swpm_outlook_username',
		'swpm_outlook_password_enc',
		'swpm_outlook_oauth_client_id',
		'swpm_outlook_oauth_client_secret_enc',
		// Elastic Email.
		'swpm_elasticemail_api_key_enc',
		// Mailjet.
		'swpm_mailjet_api_key_enc',
		'swpm_mailjet_secret_key_enc',
		// MailerSend.
		'swpm_mailersend_api_token_enc',
		// SMTP2GO.
		'swpm_smtp2go_api_key_enc',
		// SparkPost.
		'swpm_sparkpost_api_key_enc',
		'swpm_sparkpost_region',
		// Zoho.
		'swpm_zoho_username',
		'swpm_zoho_password_enc',
		'swpm_zoho_region',
		// General.
		'swpm_notify_admin_on_failure',
		'swpm_double_opt_in',
		'swpm_gdpr_checkbox',
		'swpm_show_frequency_choice',
		'swpm_form_title',
		'swpm_daily_send_hour',
		'swpm_weekly_send_day',
		'swpm_enable_open_tracking',
		'swpm_enable_click_tracking',
		'swpm_enable_smart_routing',
	);

	$map = array();
	foreach ( $options as $option_key ) {
		$name = preg_replace( '/^swpm_/', '', $option_key );
		$name = preg_replace( '/_enc$/', '', $name );
		$map[ $option_key ] = 'SWPM_' . strtoupper( $name );
	}

	return $map;
}

/**
 * Check if an option is defined as a constant in wp-config.php.
 *
 * @param string $option_key WordPress option key (e.g. 'swpm_mail_provider').
 * @return bool
 */
function swpm_is_defined( string $option_key ): bool {
	$map = swpm_get_constant_map();
	return isset( $map[ $option_key ] ) && defined( $map[ $option_key ] );
}

/**
 * Get a list of option keys that are currently overridden by constants.
 *
 * @return string[]
 */
function swpm_get_defined_constants(): array {
	$defined = array();
	foreach ( swpm_get_constant_map() as $option_key => $const_name ) {
		if ( defined( $const_name ) ) {
			$defined[] = $option_key;
		}
	}
	return $defined;
}

/**
 * Register pre_option filters for all constant-defined options.
 *
 * This uses WordPress's `pre_option_{$option}` filter so that
 * existing `get_option()` calls automatically pick up constants
 * with zero code changes across the plugin codebase.
 *
 * Also registers `pre_update_option_{$option}` to block database
 * writes for constant-defined options (the constant is authoritative).
 */
function swpm_init_constant_overrides(): void {
	foreach ( swpm_get_constant_map() as $option_key => $const_name ) {
		if ( ! defined( $const_name ) ) {
			continue;
		}

		// Override option reads.
		add_filter( "pre_option_{$option_key}", static function () use ( $option_key, $const_name ) {
			$value = constant( $const_name );

			// Boolean false would be mistaken for "no override" by WP core.
			if ( true === $value ) {
				return '1';
			}
			if ( false === $value ) {
				return '0';
			}

			// Encrypted fields: constant holds plain text, callers expect encrypted.
			if ( str_ends_with( $option_key, '_enc' ) && is_string( $value ) && '' !== $value ) {
				return swpm_encrypt( $value );
			}

			return $value;
		} );

		// Block database writes — keep existing DB value unchanged.
		add_filter( "pre_update_option_{$option_key}", static function ( $new_value, $old_value ) {
			return $old_value;
		}, 10, 2 );
	}
}
