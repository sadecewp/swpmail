<?php
/**
 * OAuth 2.0 Manager for Gmail and Outlook providers.
 *
 * Handles authorization URL generation, callback processing,
 * Token storage (encrypted), and automatic token refresh.
 *
 * Google OAuth 2.0: https://developers.google.com/identity/protocols/oauth2/web-server
 * Microsoft Identity v2.0: https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow
 *
 * @package SWPMail
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles OAuth authentication flows.
 */
class SWPM_OAuth_Manager {

	/**
	 * WordPress admin-ajax.php callback action.
	 *
	 * @var string
	 */
	private string $callback_action = 'swpm_oauth_callback';

	/** Provider configuration map. */
	private const PROVIDERS = array(
		'gmail'   => array(
			'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
			'token_url'     => 'https://oauth2.googleapis.com/token',
			'scopes'        => 'https://mail.google.com/ openid email',
			'userinfo_url'  => 'https://openidconnect.googleapis.com/v1/userinfo',
		),
		'outlook' => array(
			'authorize_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
			'token_url'     => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
			'scopes'        => 'https://outlook.office365.com/SMTP.Send offline_access openid email',
			'userinfo_url'  => 'https://graph.microsoft.com/v1.0/me',
		),
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_swpm_oauth_callback', array( $this, 'handle_callback' ) );
		add_action( 'wp_ajax_swpm_oauth_start', array( $this, 'ajax_start_oauth' ) );
		add_action( 'wp_ajax_swpm_oauth_disconnect', array( $this, 'ajax_disconnect' ) );
	}

	/**
	 * Get the redirect URI for the OAuth callback.
	 *
	 * @return string
	 */
	public function get_redirect_uri(): string {
		return admin_url( 'admin-ajax.php?action=' . $this->callback_action );
	}

	/**
	 * Check if a provider has valid OAuth credentials configured.
	 *
	 * @param string $provider 'gmail' or 'outlook'.
	 * @return bool
	 */
	public function has_credentials( string $provider ): bool {
		$client_id     = get_option( "swpm_{$provider}_oauth_client_id", '' );
		$client_secret = swpm_decrypt( get_option( "swpm_{$provider}_oauth_client_secret_enc", '' ) );
		return ! empty( $client_id ) && ! empty( $client_secret );
	}

	/**
	 * Check if a provider is currently authenticated via OAuth.
	 *
	 * @param string $provider 'gmail' or 'outlook'.
	 * @return bool
	 */
	public function is_connected( string $provider ): bool {
		$tokens = $this->get_tokens( $provider );
		return ! empty( $tokens['access_token'] );
	}

	/**
	 * Get stored token data for a provider.
	 *
	 * @param string $provider 'gmail' or 'outlook'.
	 * @return array{access_token: string, refresh_token: string, expires_at: int, email: string}
	 */
	public function get_tokens( string $provider ): array {
		$encrypted = get_option( "swpm_{$provider}_oauth_tokens_enc", '' );
		if ( empty( $encrypted ) ) {
			return array(
				'access_token'  => '',
				'refresh_token' => '',
				'expires_at'    => 0,
				'email'         => '',
			);
		}

		$json = swpm_decrypt( $encrypted );
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			return array(
				'access_token'  => '',
				'refresh_token' => '',
				'expires_at'    => 0,
				'email'         => '',
			);
		}

		return wp_parse_args(
			$data,
			array(
				'access_token'  => '',
				'refresh_token' => '',
				'expires_at'    => 0,
				'email'         => '',
			)
		);
	}

	/**
	 * Get a valid access token, refreshing if expired.
	 *
	 * @param string $provider 'gmail' or 'outlook'.
	 * @return string Access token or empty string on failure.
	 */
	public function get_access_token( string $provider ): string {
		$tokens = $this->get_tokens( $provider );

		if ( empty( $tokens['access_token'] ) ) {
			return '';
		}

		// Refresh if token expires within 5 minutes.
		if ( $tokens['expires_at'] <= ( time() + 300 ) ) {
			$refreshed = $this->refresh_token( $provider );
			if ( ! $refreshed ) {
				swpm_log( 'error', "OAuth token refresh failed for {$provider}." );
				return '';
			}
			$tokens = $this->get_tokens( $provider );
		}

		return $tokens['access_token'];
	}

	/**
	 * Get the authenticated email address for a provider.
	 *
	 * @param string $provider 'gmail' or 'outlook'.
	 * @return string
	 */
	public function get_authenticated_email( string $provider ): string {
		$tokens = $this->get_tokens( $provider );
		return $tokens['email'] ?? '';
	}

	// ------------------------------------------------------------------
	// OAuth Flow: Start
	// ----------------------------------------------------------------

	/**
	 * AJAX: Initiate OAuth flow — redirect user to provider's authorization page.
	 */
	public function ajax_start_oauth(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		// Rate limit OAuth initiation attempts.
		$rate_key = 'swpm_oauth_rate_' . get_current_user_id();
		$attempts = (int) get_transient( $rate_key );
		if ( $attempts >= 5 ) {
			wp_send_json_error( array( 'message' => __( 'Too many OAuth attempts. Please try again later.', 'swpmail' ) ), 429 );
		}
		set_transient( $rate_key, $attempts + 1, 5 * MINUTE_IN_SECONDS );

		$provider = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : '';

		if ( ! isset( self::PROVIDERS[ $provider ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid OAuth provider.', 'swpmail' ) ) );
		}

		if ( ! $this->has_credentials( $provider ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please save your Client ID and Client Secret first.', 'swpmail' ),
				)
			);
		}

		$auth_url = $this->build_authorization_url( $provider );

		wp_send_json_success( array( 'redirect_url' => $auth_url ) );
	}

	/**
	 * Build the authorization URL for the OAuth provider.
	 *
	 * @param string $provider 'gmail' or 'outlook'.
	 * @return string Authorization URL.
	 */
	private function build_authorization_url( string $provider ): string {
		$config    = self::PROVIDERS[ $provider ];
		$client_id = get_option( "swpm_{$provider}_oauth_client_id", '' );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		// Generate and store a CSRF state token.
		$state = wp_generate_password( 40, false );
		set_transient( "swpm_oauth_state_{$state}", $provider, 10 * MINUTE_IN_SECONDS );

		// PKCE: Generate code verifier and challenge.
		$code_verifier  = bin2hex( random_bytes( 32 ) );
		$code_challenge = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );
		set_transient( "swpm_oauth_verifier_{$state}", $code_verifier, 10 * MINUTE_IN_SECONDS );

		$params = array(
			'client_id'             => $client_id,
			'redirect_uri'          => $this->get_redirect_uri(),
			'response_type'         => 'code',
			'scope'                 => $config['scopes'],
			'state'                 => $state,
			'access_type'           => 'offline', // Google: request refresh_token.
			'prompt'                => 'consent', // Force consent to always get refresh_token.
			'code_challenge'        => $code_challenge,
			'code_challenge_method' => 'S256',
		);

		return $config['authorize_url'] . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	// ------------------------------------------------------------------
	// OAuth Flow: Callback
	// ----------------------------------------------------------------

	/**
	 * Handle the OAuth callback from the provider.
	 * Validates state, exchanges code for tokens, stores them encrypted.
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	/**
	 * Handle callback.
	 */
	public function handle_callback(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! current_user_can( 'manage_options' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_die( esc_html__( 'Insufficient permissions.', 'swpmail' ), 403 );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// Check for errors from the provider.
		if ( ! empty( $_GET['error'] ) ) {
			$error_desc = isset( $_GET['error_description'] )
				? sanitize_text_field( wp_unslash( $_GET['error_description'] ) )
				: sanitize_text_field( wp_unslash( $_GET['error'] ) );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			swpm_log( 'error', 'OAuth authorization error: ' . $error_desc );
			$this->redirect_with_notice( 'error', $error_desc );
			return;
		}

		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		if ( empty( $code ) || empty( $state ) ) {
			$this->redirect_with_notice( 'error', __( 'Invalid OAuth callback parameters.', 'swpmail' ) );
			return;
		}

		// Validate CSRF state.
		$provider = get_transient( "swpm_oauth_state_{$state}" );
		delete_transient( "swpm_oauth_state_{$state}" );

		// Retrieve PKCE code verifier.
		$code_verifier = get_transient( "swpm_oauth_verifier_{$state}" );
		delete_transient( "swpm_oauth_verifier_{$state}" );

		if ( empty( $provider ) || ! isset( self::PROVIDERS[ $provider ] ) ) {
			$this->redirect_with_notice( 'error', __( 'Invalid or expired state. Please try again.', 'swpmail' ) );
			return;
		}

		// Exchange authorization code for tokens.
		$result = $this->exchange_code( $provider, $code, $code_verifier );

		if ( is_wp_error( $result ) ) {
			swpm_log( 'error', 'OAuth token exchange failed: ' . $result->get_error_message() );
			$this->redirect_with_notice( 'error', $result->get_error_message() );
			return;
		}

		// Fetch user email from the provider.
		$email = $this->fetch_user_email( $provider, $result['access_token'] );

		// Store tokens.
		$token_data = array(
			'access_token'  => $result['access_token'],
			'refresh_token' => $result['refresh_token'] ?? '',
			'expires_at'    => time() + (int) ( $result['expires_in'] ?? 3600 ),
			'email'         => $email,
		);

		$this->save_tokens( $provider, $token_data );

		// Auto-set the username to the OAuth email.
		if ( ! empty( $email ) ) {
			update_option( "swpm_{$provider}_username", $email );
		}

		swpm_log( 'info', "OAuth connected successfully for {$provider}.", array( 'email' => $email ) );
		$this->redirect_with_notice(
			'success',
			sprintf(
			/* translators: %s: authenticated email */
				__( 'Successfully connected as %s.', 'swpmail' ),
				$email ? $email : $provider
			)
		);
	}

	/**
	 * Exchange an authorization code for access/refresh tokens.
	 *
	 * @param string $provider      'gmail' or 'outlook'.
	 * @param string $code          Authorization code.
	 * @param string $code_verifier PKCE code verifier (optional for backwards compat).
	 * @return array|\WP_Error Token response or error.
	 */
	private function exchange_code( string $provider, string $code, string $code_verifier = '' ) {
		$config        = self::PROVIDERS[ $provider ];
		$client_id     = get_option( "swpm_{$provider}_oauth_client_id", '' );
		$client_secret = swpm_decrypt( get_option( "swpm_{$provider}_oauth_client_secret_enc", '' ) );

		$body = array(
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => $this->get_redirect_uri(),
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
		);

		if ( ! empty( $code_verifier ) ) {
			$body['code_verifier'] = $code_verifier;
		}

		$response = wp_remote_post(
			$config['token_url'],
			array(
				'timeout' => 30,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || empty( $data['access_token'] ) ) {
			$error_msg = $data['error_description'] ?? $data['error'] ?? __( 'Unknown error during token exchange.', 'swpmail' );
			return new \WP_Error( 'oauth_token_error', $error_msg );
		}

		return $data;
	}

	/**
	 * Fetch the authenticated user's email from the provider.
	 *
	 * @param string $provider     'gmail' or 'outlook'.
	 * @param string $access_token Access token.
	 * @return string Email or empty string.
	 */
	private function fetch_user_email( string $provider, string $access_token ): string {
		$config = self::PROVIDERS[ $provider ];

		$response = wp_remote_get(
			$config['userinfo_url'],
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 'outlook' === $provider ) {
			$email = $data['mail'] ?? $data['userPrincipalName'] ?? '';
		} else {
			$email = $data['email'] ?? '';
		}

		return is_email( $email ) ? $email : '';
	}

	/*
	------------------------------------------------------------------
	 * Token Refresh
	 * ----------------------------------------------------------------
	 */

	/**
	 * Refresh an expired access token using the refresh token.
	 *
	 * @param string $provider 'gmail' or 'outlook'.
	 * @return bool True on success.
	 */
	private function refresh_token( string $provider ): bool {
		$tokens = $this->get_tokens( $provider );

		if ( empty( $tokens['refresh_token'] ) ) {
			swpm_log( 'warning', "No refresh token available for {$provider}." );
			return false;
		}

		$config        = self::PROVIDERS[ $provider ];
		$client_id     = get_option( "swpm_{$provider}_oauth_client_id", '' );
		$client_secret = swpm_decrypt( get_option( "swpm_{$provider}_oauth_client_secret_enc", '' ) );

		$body = array(
			'grant_type'    => 'refresh_token',
			'refresh_token' => $tokens['refresh_token'],
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
		);

		$response = wp_remote_post(
			$config['token_url'],
			array(
				'timeout' => 30,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			swpm_log( 'error', "OAuth refresh HTTP error for {$provider}: " . $response->get_error_message() );
			return false;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || empty( $data['access_token'] ) ) {
			$error_msg = $data['error_description'] ?? $data['error'] ?? 'Unknown refresh error';
			swpm_log( 'error', "OAuth refresh failed for {$provider}: {$error_msg}" );
			return false;
		}

		// Update stored tokens (refresh_token may or may not be returned).
		$token_data = array(
			'access_token'  => $data['access_token'],
			'refresh_token' => $data['refresh_token'] ?? $tokens['refresh_token'],
			'expires_at'    => time() + (int) ( $data['expires_in'] ?? 3600 ),
			'email'         => $tokens['email'],
		);

		$this->save_tokens( $provider, $token_data );

		swpm_log( 'debug', "OAuth token refreshed for {$provider}." );
		return true;
	}

	/*
	------------------------------------------------------------------
	 * Disconnect
	 * ----------------------------------------------------------------
	 */

	/**
	 * AJAX: Disconnect OAuth for a provider.
	 */
	public function ajax_disconnect(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : '';

		if ( ! isset( self::PROVIDERS[ $provider ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid provider.', 'swpmail' ) ) );
		}

		delete_option( "swpm_{$provider}_oauth_tokens_enc" );

		swpm_log( 'info', "OAuth disconnected for {$provider}." );

		wp_send_json_success(
			array(
				'message' => __( 'OAuth disconnected successfully.', 'swpmail' ),
			)
		);
	}

	/*
	------------------------------------------------------------------
	 * Storage Helpers
	 * ----------------------------------------------------------------
	 */

	/**
	 * Save tokens encrypted.
	 *
	 * @param string $provider   'gmail' or 'outlook'.
	 * @param array  $token_data Token data array.
	 */
	private function save_tokens( string $provider, array $token_data ): void {
		$json      = wp_json_encode( $token_data );
		$encrypted = swpm_encrypt( $json );
		update_option( "swpm_{$provider}_oauth_tokens_enc", $encrypted, false );
	}

	/**
	 * Redirect back to Mail Settings page with a notice.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Notice message.
	 */
	private function redirect_with_notice( string $type, string $message ): void {
		set_transient(
			'swpm_oauth_notice',
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);

		wp_safe_redirect( admin_url( 'admin.php?page=swpmail-mail-settings' ) );
		exit;
	}
}
