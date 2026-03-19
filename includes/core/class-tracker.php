<?php
/**
 * Email Tracker — open pixel + click redirect proxy.
 *
 * Injects an invisible 1×1 tracking pixel into outgoing HTML emails and
 * rewrites links through a redirect proxy so that opens and clicks
 * are recorded in the swpm_tracking table.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Tracker {

	/** @var string Endpoint query var for open pixel. */
	public const OPEN_ENDPOINT = 'swpm_open';

	/** @var string Endpoint query var for click redirect. */
	public const CLICK_ENDPOINT = 'swpm_click';

	/** @var \wpdb */
	private $db;

	/** @var string */
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'swpm_tracking';
	}

	/**
	 * Register rewrite rules and front-end handlers.
	 * Called from define_hooks() via the loader.
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ), 5 );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_request' ), 1 );
	}

	/* ------------------------------------------------------------------
	 * Rewrite / Routing
	 * ----------------------------------------------------------------*/

	/**
	 * Add rewrite rules for tracking endpoints.
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^swpm/open/([a-f0-9]{64})/?$',
			'index.php?' . self::OPEN_ENDPOINT . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^swpm/click/([a-f0-9]{64})/?$',
			'index.php?' . self::CLICK_ENDPOINT . '=$matches[1]',
			'top'
		);

		// Flush once when rules are missing.
		if ( ! get_option( 'swpm_tracking_rules_flushed' ) ) {
			flush_rewrite_rules( false );
			update_option( 'swpm_tracking_rules_flushed', SWPM_VERSION, false );
		}
	}

	/**
	 * Register custom query vars.
	 *
	 * @param array $vars Existing vars.
	 * @return array
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = self::OPEN_ENDPOINT;
		$vars[] = self::CLICK_ENDPOINT;
		return $vars;
	}

	/**
	 * Handle incoming tracking requests.
	 */
	public function handle_request(): void {
		$open_hash  = get_query_var( self::OPEN_ENDPOINT, '' );
		$click_hash = get_query_var( self::CLICK_ENDPOINT, '' );

		if ( ! empty( $open_hash ) ) {
			if ( ! $this->check_tracking_rate_limit() ) {
				$this->serve_pixel();
				return;
			}
			$this->handle_open( $open_hash );
		}

		if ( ! empty( $click_hash ) ) {
			if ( ! $this->check_tracking_rate_limit() ) {
				wp_safe_redirect( home_url() );
				exit;
			}
			$this->handle_click( $click_hash );
		}
	}

	/* ------------------------------------------------------------------
	 * Open Tracking
	 * ----------------------------------------------------------------*/

	/**
	 * Record an open event and serve a 1×1 transparent GIF.
	 *
	 * @param string $hash Tracking hash.
	 */
	private function handle_open( string $hash ): void {
		if ( ! $this->is_valid_hash( $hash ) ) {
			$this->serve_pixel();
			return;
		}

		$meta = $this->get_hash_meta( $hash );
		if ( ! $meta ) {
			$this->serve_pixel();
			return;
		}

		// Skip known prefetch/proxy user agents to avoid false opens.
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';
		$prefetch_patterns = array(
			'GoogleImageProxy',
			'YahooMailProxy',
			'Outlook-iOS',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
			'Googlebot',
			'bingbot',
			'Barracuda',
			'ZmImgProxy',
			'CloudFlare-AlwaysOnline',
			'Fastly-Image-Proxy',
			'AppleWebKit/605.1',
		);
		foreach ( $prefetch_patterns as $pattern ) {
			if ( ! empty( $ua ) && stripos( $ua, $pattern ) !== false ) {
				$this->serve_pixel();
				return;
			}
		}

		// Record only the first open per hash to avoid inflating counts.
		$cache_key = 'swpm_opened_' . $hash;
		if ( get_transient( $cache_key ) ) {
			$this->serve_pixel();
			return;
		}
		set_transient( $cache_key, 1, 30 * DAY_IN_SECONDS );

		$this->record_event( $hash, $meta, 'open' );

		$this->serve_pixel();
	}

	/**
	 * Output a 1×1 transparent GIF and exit.
	 */
	private function serve_pixel(): void {
		// @codeCoverageIgnoreStart
		nocache_headers();
		header( 'Content-Type: image/gif' );
		header( 'Content-Length: 43' );
		// 1×1 transparent GIF.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
		exit;
		// @codeCoverageIgnoreEnd
	}

	/* ------------------------------------------------------------------
	 * Click Tracking
	 * ----------------------------------------------------------------*/

	/**
	 * Record a click event and redirect to the original URL.
	 *
	 * @param string $hash Tracking hash.
	 */
	private function handle_click( string $hash ): void {
		if ( ! $this->is_valid_hash( $hash ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}

		$meta = $this->get_hash_meta( $hash );
		if ( ! $meta || empty( $meta['url'] ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}

		$this->record_event( $hash, $meta, 'click' );

		$url = $meta['url'];

		// Only redirect to http(s) URLs — prevent open redirect attacks.
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = home_url();
		}

		// Use wp_redirect for external URLs.
		wp_redirect( esc_url_raw( $url ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/* ------------------------------------------------------------------
	 * Email Body Transformation
	 * ----------------------------------------------------------------*/

	/**
	 * Inject tracking pixel and rewrite links in an HTML email body.
	 *
	 * @param string   $body     Email HTML body.
	 * @param string   $to_email Recipient email.
	 * @param string   $subject  Email subject.
	 * @param int|null $queue_id Queue item ID (if sent via queue).
	 * @return string Modified HTML body.
	 */
	public function inject_tracking( string $body, string $to_email, string $subject = '', ?int $queue_id = null ): string {
		// Only process HTML content.
		if ( stripos( $body, '<' ) === false ) {
			return $body;
		}

		$email_hash = $this->generate_email_hash( $to_email, $queue_id );

		// Store hash metadata in a short-lived transient (30 days).
		$this->store_hash_meta( $email_hash, array(
			'to_email' => $to_email,
			'subject'  => $subject,
			'queue_id' => $queue_id,
		) );

		// Inject click tracking (rewrite links).
		if ( get_option( 'swpm_enable_click_tracking', true ) ) {
			$body = $this->rewrite_links( $body, $to_email, $subject, $queue_id );
		}

		// Inject open tracking pixel.
		if ( get_option( 'swpm_enable_open_tracking', true ) ) {
			$pixel_url = $this->get_open_pixel_url( $email_hash );
			$pixel_tag = '<img src="' . esc_url( $pixel_url ) . '" width="1" height="1" alt="" style="display:none;width:1px;height:1px;border:0;" />';

			// Insert before </body> if it exists, otherwise append.
			if ( stripos( $body, '</body>' ) !== false ) {
				$body = str_ireplace( '</body>', $pixel_tag . '</body>', $body );
			} else {
				$body .= $pixel_tag;
			}
		}

		return $body;
	}

	/**
	 * Rewrite <a href="..."> links for click tracking.
	 *
	 * @param string   $body     HTML body.
	 * @param string   $to_email Recipient email.
	 * @param string   $subject  Subject line.
	 * @param int|null $queue_id Queue ID.
	 * @return string Modified HTML.
	 */
	private function rewrite_links( string $body, string $to_email, string $subject, ?int $queue_id ): string {
		// Match <a href="..."> tags, but skip anchors (#), mailto:, tel:, and unsubscribe links.
		return preg_replace_callback(
			'/<a\s([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>/i',
			function ( $matches ) use ( $to_email, $subject, $queue_id ) {
				$before = $matches[1];
				$url    = $matches[2];
				$after  = $matches[3];

				// Skip non-trackable links.
				if (
					strpos( $url, '#' ) === 0 ||
					strpos( $url, 'mailto:' ) === 0 ||
					strpos( $url, 'tel:' ) === 0 ||
					strpos( $url, 'swpm_action' ) !== false // Unsubscribe links.
				) {
					return $matches[0];
				}

				// Skip links with data-swpm-no-track attribute.
				if ( stripos( $matches[0], 'data-swpm-no-track' ) !== false ) {
					return $matches[0];
				}

				$link_hash = $this->generate_link_hash( $to_email, $url, $queue_id );

				$this->store_hash_meta( $link_hash, array(
					'to_email' => $to_email,
					'subject'  => $subject,
					'queue_id' => $queue_id,
					'url'      => $url,
				) );

				$tracked_url = $this->get_click_url( $link_hash );

				return '<a ' . $before . 'href="' . esc_url( $tracked_url ) . '"' . $after . '>';
			},
			$body
		);
	}

	/* ------------------------------------------------------------------
	 * Hash Management
	 * ----------------------------------------------------------------*/

	/**
	 * Generate a unique hash for an email (open tracking).
	 *
	 * @param string   $to_email Recipient.
	 * @param int|null $queue_id Queue ID.
	 * @return string 64-char hex hash.
	 */
	private function generate_email_hash( string $to_email, ?int $queue_id ): string {
		$data = $to_email . '|' . ( $queue_id ?? 0 ) . '|' . wp_generate_password( 16, false );
		return hash( 'sha256', $data . wp_salt( 'auth' ) );
	}

	/**
	 * Generate a unique hash for a link (click tracking).
	 *
	 * @param string   $to_email Recipient.
	 * @param string   $url      Original URL.
	 * @param int|null $queue_id Queue ID.
	 * @return string 64-char hex hash.
	 */
	private function generate_link_hash( string $to_email, string $url, ?int $queue_id ): string {
		$data = $to_email . '|' . $url . '|' . ( $queue_id ?? 0 ) . '|' . wp_generate_password( 16, false );
		return hash( 'sha256', $data . wp_salt( 'auth' ) );
	}

	/**
	 * Store hash metadata in a transient (30-day TTL).
	 *
	 * @param string $hash Hash key.
	 * @param array  $meta Metadata.
	 */
	private function store_hash_meta( string $hash, array $meta ): void {
		set_transient( 'swpm_th_' . $hash, $meta, 30 * DAY_IN_SECONDS );
	}

	/**
	 * Retrieve hash metadata.
	 *
	 * @param string $hash Hash key.
	 * @return array|false
	 */
	private function get_hash_meta( string $hash ) {
		return get_transient( 'swpm_th_' . $hash );
	}

	/**
	 * Validate hash format (64 hex chars).
	 *
	 * @param string $hash Hash to validate.
	 * @return bool
	 */
	private function is_valid_hash( string $hash ): bool {
		return (bool) preg_match( '/^[a-f0-9]{64}$/', $hash );
	}

	/**
	 * IP-based rate limit for tracking endpoints.
	 *
	 * @return bool True if request is within limit, false if throttled.
	 */
	private function check_tracking_rate_limit(): bool {
		$ip    = $this->get_client_ip();
		$key   = 'swpm_track_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		$limit = (int) apply_filters( 'swpm_tracking_rate_limit', 100 );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, 5 * MINUTE_IN_SECONDS );
		return true;
	}

	/* ------------------------------------------------------------------
	 * URL Builders
	 * ----------------------------------------------------------------*/

	/**
	 * Build the open tracking pixel URL.
	 *
	 * @param string $hash Tracking hash.
	 * @return string
	 */
	private function get_open_pixel_url( string $hash ): string {
		return home_url( 'swpm/open/' . $hash );
	}

	/**
	 * Build the click redirect URL.
	 *
	 * @param string $hash Tracking hash.
	 * @return string
	 */
	private function get_click_url( string $hash ): string {
		return home_url( 'swpm/click/' . $hash );
	}

	/* ------------------------------------------------------------------
	 * Event Recording
	 * ----------------------------------------------------------------*/

	/**
	 * Record a tracking event.
	 *
	 * @param string $hash       Tracking hash.
	 * @param array  $meta       Hash metadata.
	 * @param string $event_type 'open' or 'click'.
	 */
	private function record_event( string $hash, array $meta, string $event_type ): void {
		$ip = $this->get_client_ip();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->db->insert(
			$this->table,
			array(
				'hash'       => $hash,
				'queue_id'   => $meta['queue_id'] ?? null,
				'to_email'   => $meta['to_email'] ?? '',
				'subject'    => $meta['subject'] ?? '',
				'event_type' => $event_type,
				'url'        => $meta['url'] ?? null,
				'ip_address' => $ip,
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] )
					? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 )
					: null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get client IP address, respecting privacy.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
