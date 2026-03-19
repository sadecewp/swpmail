<?php
/**
 * DNS Domain Checker — SPF, DKIM, DMARC validation.
 *
 * Uses PHP's native dns_get_record() for lookups and
 * Parses each record type against deliverability best practices.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates DNS records for email authentication.
 */
class SWPM_DNS_Checker {

	/** Transient key prefix for cached results. */
	private const CACHE_PREFIX = 'swpm_dns_';

	/** Cache TTL in seconds (15 minutes). */
	private const CACHE_TTL = 900;

	/**
	 * Domain to check.
	 *
	 * @var string
	 */
	private string $domain;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_swpm_dns_check', array( $this, 'ajax_dns_check' ) );
		add_action( 'wp_ajax_swpm_dns_auto_check', array( $this, 'ajax_auto_check' ) );

		// Auto-check when mail settings are saved.
		add_action( 'update_option_swpm_from_email', array( $this, 'on_settings_saved' ), 10, 0 );
		add_action( 'update_option_swpm_mail_provider', array( $this, 'on_settings_saved' ), 10, 0 );
	}

	/**
	 * Callback: re-run DNS check when mail settings change.
	 */
	public function on_settings_saved(): void {
		$domain = $this->get_from_domain();
		if ( empty( $domain ) ) {
			return;
		}
		// Clear cached result so it re-checks.
		delete_transient( self::CACHE_PREFIX . md5( $this->sanitize_domain( $domain ) ) );
		$result = $this->check( $domain );

		// Store a transient notice so the admin can see the result.
		$notice_type = 'pass' === $result['overall'] ? 'success' : ( 'warning' === $result['overall'] ? 'warning' : 'error' );
		$messages    = array(
			'success' => __( 'DNS check passed — SPF, DKIM, and DMARC are properly configured.', 'swpmail' ),
			'warning' => __( 'DNS check found some issues. Visit the DNS Checker page for details.', 'swpmail' ),
			'error'   => __( 'DNS check detected critical issues. Visit the DNS Checker page to review.', 'swpmail' ),
		);
		set_transient(
			'swpm_dns_notice',
			array(
				'type'    => $notice_type,
				'message' => $messages[ $notice_type ],
			),
			60
		);
	}

	// ------------------------------------------------------------------
	// Public API
	// ----------------------------------------------------------------

	/**
	 * Run a full DNS check for the given domain.
	 *
	 * @param string $domain Domain name (without protocol).
	 * @return array{domain: string, spf: array, dkim: array, dmarc: array, overall: string, timestamp: int}
	 */
	public function check( string $domain ): array {
		$this->domain = $this->sanitize_domain( $domain );

		if ( empty( $this->domain ) ) {
			return $this->empty_result( $domain );
		}

		$cached = get_transient( self::CACHE_PREFIX . md5( $this->domain ) );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$spf   = $this->check_spf();
		$dkim  = $this->check_dkim();
		$dmarc = $this->check_dmarc();

		$result = array(
			'domain'    => $this->domain,
			'spf'       => $spf,
			'dkim'      => $dkim,
			'dmarc'     => $dmarc,
			'overall'   => $this->compute_overall( $spf, $dkim, $dmarc ),
			'timestamp' => time(),
		);

		set_transient( self::CACHE_PREFIX . md5( $this->domain ), $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Get the from-email domain for auto-checks.
	 *
	 * @return string
	 */
	public function get_from_domain(): string {
		$email = get_option( 'swpm_from_email', get_option( 'admin_email' ) );
		$parts = explode( '@', $email );
		return isset( $parts[1] ) ? $parts[1] : '';
	}

	// ------------------------------------------------------------------
	// SPF Check
	// ----------------------------------------------------------------

	/**
	 * Check SPF record for the domain.
	 *
	 * @return array{status: string, record: string, details: string[], warnings: string[]}
	 */
	private function check_spf(): array {
		$result = array(
			'status'   => 'missing',
			'record'   => '',
			'details'  => array(),
			'warnings' => array(),
		);

		$txt_records = $this->dns_lookup( $this->domain, DNS_TXT );

		if ( false === $txt_records ) {
			$result['details'][] = __( 'DNS lookup failed. Please verify the domain name is correct.', 'swpmail' );
			return $result;
		}

		$spf_records = array();
		foreach ( $txt_records as $record ) {
			$txt = isset( $record['txt'] ) ? $record['txt'] : '';
			if ( stripos( $txt, 'v=spf1' ) === 0 ) {
				$spf_records[] = $txt;
			}
		}

		if ( empty( $spf_records ) ) {
			$result['details'][] = __( 'No SPF record found. Email receivers cannot verify your sending authorization.', 'swpmail' );
			return $result;
		}

		if ( count( $spf_records ) > 1 ) {
			$result['status']     = 'warning';
			$result['warnings'][] = __( 'Multiple SPF records found. Only one SPF record is allowed per domain. This can cause delivery failures.', 'swpmail' );
		}

		$spf_text         = $spf_records[0];
		$result['record'] = $spf_text;

		// Parse mechanisms.
		$mechanisms    = preg_split( '/\s+/', $spf_text );
		$has_all       = false;
		$all_value     = '';
		$include_count = 0;
		$lookup_count  = 0;

		foreach ( $mechanisms as $mech ) {
			$mech = strtolower( trim( $mech ) );
			if ( empty( $mech ) || 'v=spf1' === $mech ) {
				continue;
			}

			// Count DNS lookups (includes, a, mx, ptr, exists, redirect).
			if ( preg_match( '/^[+~?-]?(include|a|mx|ptr|exists|redirect)([:\/\s]|$)/', $mech ) ) {
				++$lookup_count;
			}

			if ( strpos( $mech, 'include:' ) !== false ) {
				++$include_count;
			}

			if ( preg_match( '/^[+~?-]?all$/', $mech ) ) {
				$has_all   = true;
				$all_value = $mech;
			}
		}

		if ( ! $has_all ) {
			$result['warnings'][] = __( 'SPF record has no "all" mechanism. Add "-all" or "~all" to complete the policy.', 'swpmail' );
		} elseif ( '+all' === $all_value || 'all' === $all_value ) {
			$result['warnings'][] = __( 'SPF uses "+all" which allows any server to send email for your domain. Use "-all" (hard fail) or "~all" (soft fail) instead.', 'swpmail' );
		}

		if ( $lookup_count > 10 ) {
			$result['warnings'][] = sprintf(
				/* translators: %d: number of DNS lookups */
				__( 'SPF record requires %d DNS lookups — exceeds the RFC 7208 limit of 10. This may cause SPF failures.', 'swpmail' ),
				$lookup_count
			);
		}

		$result['details'][] = sprintf(
			/* translators: %d: number of include mechanisms */
			__( 'Contains %d include mechanism(s).', 'swpmail' ),
			$include_count
		);

		$result['details'][] = sprintf(
			/* translators: %d: number of DNS lookups */
			__( '%d of 10 DNS lookups used.', 'swpmail' ),
			$lookup_count
		);

		if ( '-all' === $all_value ) {
			$result['details'][] = __( 'Uses hard fail (-all) — recommended.', 'swpmail' );
		} elseif ( '~all' === $all_value ) {
			$result['details'][] = __( 'Uses soft fail (~all) — acceptable.', 'swpmail' );
		}

		$result['status'] = empty( $result['warnings'] ) ? 'pass' : 'warning';

		return $result;
	}

	// ------------------------------------------------------------------
	// DKIM Check
	// ----------------------------------------------------------------

	/**
	 * Check DKIM records for common selectors.
	 *
	 * @return array{status: string, records: array, details: string[], warnings: string[]}
	 */
	private function check_dkim(): array {
		$result = array(
			'status'   => 'missing',
			'records'  => array(),
			'details'  => array(),
			'warnings' => array(),
		);

		// Common DKIM selectors used by popular email providers.
		$selectors = array(
			'default',
			'google',
			'selector1',  // Microsoft.
			'selector2',  // Microsoft.
			'k1',         // Mailchimp.
			'smtp',
			'mail',
			'dkim',
			's1',
			's2',
			'brevo',      // Brevo (Sendinblue).
			'mxvault',
			'pm',         // Postmark.
			'sendgrid',   // SendGrid.
			'sig1',
		);

		$provider_key       = get_option( 'swpm_mail_provider', 'phpmail' );
		$provider_selectors = $this->get_provider_selectors( $provider_key );
		$selectors          = array_unique( array_merge( $provider_selectors, $selectors ) );

		$found = array();

		foreach ( $selectors as $selector ) {
			$dkim_domain = $selector . '._domainkey.' . $this->domain;
			$records     = $this->dns_lookup( $dkim_domain, DNS_TXT );

			if ( false === $records || empty( $records ) ) {
				// Also try CNAME — many providers use CNAME delegation.
				$cname = $this->dns_lookup( $dkim_domain, DNS_CNAME );
				if ( ! empty( $cname ) ) {
					$found[] = array(
						'selector' => $selector,
						'type'     => 'CNAME',
						'value'    => $cname[0]['target'] ?? '',
					);
					continue;
				}
				continue;
			}

			foreach ( $records as $record ) {
				$txt = isset( $record['txt'] ) ? $record['txt'] : '';
				if ( stripos( $txt, 'v=dkim1' ) !== false || stripos( $txt, 'p=' ) !== false ) {
					$found[] = array(
						'selector' => $selector,
						'type'     => 'TXT',
						'value'    => $txt,
					);

					// Validate the key.
					if ( preg_match( '/p=([^;\s]*)/', $txt, $matches ) ) {
						if ( empty( $matches[1] ) ) {
							$result['warnings'][] = sprintf(
								/* translators: %s: DKIM selector */
								__( 'Selector "%s" has an empty public key — the key may be revoked.', 'swpmail' ),
								$selector
							);
						}
					}
				}
			}
		}

		$result['records'] = $found;

		if ( empty( $found ) ) {
			$result['details'][] = __( 'No DKIM records found for common selectors. DKIM signing may not be configured.', 'swpmail' );
			return $result;
		}

		$result['details'][] = sprintf(
			/* translators: %d: number of DKIM records */
			__( '%d DKIM record(s) found.', 'swpmail' ),
			count( $found )
		);

		$selector_list       = wp_list_pluck( $found, 'selector' );
		$result['details'][] = sprintf(
			/* translators: %s: comma-separated selector list */
			__( 'Active selectors: %s', 'swpmail' ),
			implode( ', ', $selector_list )
		);

		$result['status'] = empty( $result['warnings'] ) ? 'pass' : 'warning';

		return $result;
	}

	/**
	 * Get provider-specific DKIM selectors to check first.
	 *
	 * @param string $provider_key Current provider key.
	 * @return string[]
	 */
	private function get_provider_selectors( string $provider_key ): array {
		$map = array(
			'gmail'        => array( 'google' ),
			'outlook'      => array( 'selector1', 'selector2' ),
			'sendgrid'     => array( 's1', 's2', 'sendgrid' ),
			'mailgun'      => array( 'smtp', 'k1', 'mailo' ),
			'postmark'     => array( 'pm', '20161025' ),
			'brevo'        => array( 'brevo', 'mail' ),
			'ses'          => array( 'amazonses' ),
			'sparkpost'    => array( 'sparkpostmail', 'scph' ),
			'mailjet'      => array( 'mailjet' ),
			'resend'       => array( 'resend' ),
			'elasticemail' => array( 'api' ),
			'smtp2go'      => array( 'smtp2go', 'cm' ),
			'mailersend'   => array( 'mlsend' ),
			'zoho'         => array( 'zoho', 'zmail' ),
		);

		return $map[ $provider_key ] ?? array();
	}

	// ------------------------------------------------------------------
	// DMARC Check
	// ----------------------------------------------------------------

	/**
	 * Check DMARC record for the domain.
	 *
	 * @return array{status: string, record: string, policy: string, details: string[], warnings: string[]}
	 */
	private function check_dmarc(): array {
		$result = array(
			'status'   => 'missing',
			'record'   => '',
			'policy'   => '',
			'details'  => array(),
			'warnings' => array(),
		);

		$dmarc_domain = '_dmarc.' . $this->domain;
		$records      = $this->dns_lookup( $dmarc_domain, DNS_TXT );

		if ( false === $records || empty( $records ) ) {
			$result['details'][] = __( 'No DMARC record found. Without DMARC, receivers have no policy for handling SPF/DKIM failures.', 'swpmail' );
			return $result;
		}

		$dmarc_txt = '';
		foreach ( $records as $record ) {
			$txt = isset( $record['txt'] ) ? $record['txt'] : '';
			if ( stripos( $txt, 'v=dmarc1' ) === 0 ) {
				$dmarc_txt = $txt;
				break;
			}
		}

		if ( empty( $dmarc_txt ) ) {
			$result['details'][] = __( 'TXT records exist at _dmarc but none contain a valid DMARC record (must start with "v=DMARC1").', 'swpmail' );
			return $result;
		}

		$result['record'] = $dmarc_txt;

		// Parse tags.
		$tags = $this->parse_dmarc_tags( $dmarc_txt );

		// Policy (p=).
		$policy           = $tags['p'] ?? '';
		$result['policy'] = $policy;

		if ( empty( $policy ) ) {
			$result['warnings'][] = __( 'DMARC record is missing the required "p=" policy tag.', 'swpmail' );
		} elseif ( 'none' === $policy ) {
			$result['details'][]  = __( 'Policy: none (monitoring only) — emails are delivered even on failure.', 'swpmail' );
			$result['warnings'][] = __( 'Consider upgrading to "p=quarantine" or "p=reject" for better protection.', 'swpmail' );
		} elseif ( 'quarantine' === $policy ) {
			$result['details'][] = __( 'Policy: quarantine — failing emails are sent to spam.', 'swpmail' );
		} elseif ( 'reject' === $policy ) {
			$result['details'][] = __( 'Policy: reject — failing emails are blocked entirely. Strongest protection.', 'swpmail' );
		}

		// Sub‐domain policy (sp=).
		if ( isset( $tags['sp'] ) ) {
			$result['details'][] = sprintf(
				/* translators: %s: subdomain policy */
				__( 'Subdomain policy: %s', 'swpmail' ),
				$tags['sp']
			);
		}

		// Percentage (pct=).
		if ( isset( $tags['pct'] ) ) {
			$pct                 = (int) $tags['pct'];
			$result['details'][] = sprintf(
				/* translators: %d: percentage */
				__( 'Applied to %d%% of emails.', 'swpmail' ),
				$pct
			);
			if ( $pct < 100 ) {
				$result['warnings'][] = sprintf(
					/* translators: %d: percentage */
					__( 'Only %d%% of emails are subject to the DMARC policy. Consider increasing to 100%%.', 'swpmail' ),
					$pct
				);
			}
		}

		// Reporting (rua=, ruf=).
		if ( isset( $tags['rua'] ) ) {
			$rua_valid           = $this->validate_dmarc_uris( $tags['rua'] );
			$result['details'][] = sprintf(
				/* translators: %s: reporting URI */
				__( 'Aggregate reports sent to: %s', 'swpmail' ),
				$tags['rua']
			);
			if ( ! $rua_valid ) {
				$result['warnings'][] = __( 'One or more rua= email addresses appear invalid.', 'swpmail' );
			}
		} else {
			$result['warnings'][] = __( 'No aggregate reporting (rua=) configured. You won\'t receive DMARC reports.', 'swpmail' );
		}

		if ( isset( $tags['ruf'] ) ) {
			$ruf_valid           = $this->validate_dmarc_uris( $tags['ruf'] );
			$result['details'][] = sprintf(
				/* translators: %s: forensic URI */
				__( 'Forensic reports sent to: %s', 'swpmail' ),
				$tags['ruf']
			);
			if ( ! $ruf_valid ) {
				$result['warnings'][] = __( 'One or more ruf= email addresses appear invalid.', 'swpmail' );
			}
		}

		// Alignment (adkim=, aspf=).
		if ( isset( $tags['adkim'] ) ) {
			$alignment           = 'r' === $tags['adkim'] ? __( 'relaxed', 'swpmail' ) : __( 'strict', 'swpmail' );
			$result['details'][] = sprintf(
				/* translators: %s: alignment mode */
				__( 'DKIM alignment: %s', 'swpmail' ),
				$alignment
			);
		}
		if ( isset( $tags['aspf'] ) ) {
			$alignment           = 'r' === $tags['aspf'] ? __( 'relaxed', 'swpmail' ) : __( 'strict', 'swpmail' );
			$result['details'][] = sprintf(
				/* translators: %s: alignment mode */
				__( 'SPF alignment: %s', 'swpmail' ),
				$alignment
			);
		}

		$result['status'] = empty( $result['warnings'] ) ? 'pass' : 'warning';

		return $result;
	}

	/**
	 * Parse DMARC tag-value pairs.
	 *
	 * @param string $record Raw DMARC TXT record.
	 * @return array<string, string>
	 */
	private function parse_dmarc_tags( string $record ): array {
		$tags  = array();
		$parts = explode( ';', $record );
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( strpos( $part, '=' ) === false ) {
				continue;
			}
			list( $key, $value )                = explode( '=', $part, 2 );
			$tags[ strtolower( trim( $key ) ) ] = trim( $value );
		}
		return $tags;
	}

	/**
	 * Validate DMARC mailto: URIs in rua/ruf values.
	 *
	 * @param string $uris Comma-separated list of mailto: URIs.
	 * @return bool True if all URIs contain valid email addresses.
	 */
	private function validate_dmarc_uris( string $uris ): bool {
		$parts     = explode( ',', $uris );
		$found_uri = false;
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}
			if ( strpos( $part, 'mailto:' ) === 0 ) {
				$email = substr( $part, 7 );
				// Strip optional !<size> suffix (e.g. mailto:admin@example.com!10m).
				$email = preg_replace( '/![^@]*$/', '', $email );
				if ( ! is_email( $email ) ) {
					return false;
				}
				$found_uri = true;
			}
		}
		return $found_uri;
	}

	// ------------------------------------------------------------------
	// Helpers
	// ----------------------------------------------------------------

	/**
	 * Safe DNS lookup wrapper.
	 *
	 * @param string $host  Hostname to query.
	 * @param int    $type  DNS record type constant.
	 * @return array|false
	 */
	private function dns_lookup( string $host, int $type ) {
		if ( ! function_exists( 'dns_get_record' ) ) {
			return false;
		}
		// Suppress warnings from invalid/unreachable domains.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- dns_get_record warns on unreachable domains.
		$records = @dns_get_record( $host, $type );
		return is_array( $records ) ? $records : false;
	}

	/**
	 * Extract and sanitize domain from input.
	 *
	 * @param string $input Raw domain input.
	 * @return string Clean domain (lowercase, no protocol/path).
	 */
	private function sanitize_domain( string $input ): string {
		$input = strtolower( trim( $input ) );

		// Strip protocol.
		$input = preg_replace( '#^https?://#', '', $input );

		// Strip path/query.
		$input = preg_replace( '#[/?#].*$#', '', $input );

		// Strip port.
		$input = preg_replace( '#:\d+$#', '', $input );

		// Validate.
		if ( ! preg_match( '/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $input ) ) {
			return '';
		}

		return $input;
	}

	/**
	 * Compute overall score from all three checks.
	 *
	 * @param array $spf   SPF result.
	 * @param array $dkim  DKIM result.
	 * @param array $dmarc DMARC result.
	 * @return string 'pass'|'warning'|'fail'
	 */
	private function compute_overall( array $spf, array $dkim, array $dmarc ): string {
		$statuses = array( $spf['status'], $dkim['status'], $dmarc['status'] );

		$missing = array_count_values( $statuses )['missing'] ?? 0;

		if ( $missing >= 2 ) {
			return 'fail';
		}

		if ( in_array( 'missing', $statuses, true ) ) {
			return 'warning';
		}

		if ( in_array( 'warning', $statuses, true ) ) {
			return 'warning';
		}

		return 'pass';
	}

	/**
	 * Return empty result structure.
	 *
	 * @param string $domain Original domain input.
	 * @return array
	 */
	private function empty_result( string $domain ): array {
		return array(
			'domain'    => $domain,
			'spf'       => array(
				'status'   => 'missing',
				'record'   => '',
				'details'  => array( __( 'Invalid domain.', 'swpmail' ) ),
				'warnings' => array(),
			),
			'dkim'      => array(
				'status'   => 'missing',
				'records'  => array(),
				'details'  => array( __( 'Invalid domain.', 'swpmail' ) ),
				'warnings' => array(),
			),
			'dmarc'     => array(
				'status'   => 'missing',
				'record'   => '',
				'policy'   => '',
				'details'  => array( __( 'Invalid domain.', 'swpmail' ) ),
				'warnings' => array(),
			),
			'overall'   => 'fail',
			'timestamp' => time(),
		);
	}

	// ------------------------------------------------------------------
	// AJAX Endpoints
	// ----------------------------------------------------------------

	/**
	 * AJAX: Run DNS check for a user-provided domain.
	 */
	public function ajax_dns_check(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		$domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';

		if ( empty( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a domain name.', 'swpmail' ) ) );
		}

		$result = $this->check( $domain );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Auto-check the configured from-email domain.
	 */
	public function ajax_auto_check(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		$domain = $this->get_from_domain();

		if ( empty( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'No from-email domain configured.', 'swpmail' ) ) );
		}

		$result = $this->check( $domain );

		wp_send_json_success( $result );
	}
}
