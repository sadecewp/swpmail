<?php
/**
 * Main plugin class (bootstrap / service container).
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPMail {

	/** @var SWPM_Loader */
	private SWPM_Loader $loader;

	/** @var array<string, object> Service registry. */
	private static array $instances = array();

	public function __construct() {
		$this->loader = new SWPM_Loader();
	}

	/**
	 * Run the plugin.
	 */
	public function run(): void {
		$this->maybe_upgrade();
		$this->load_dependencies();
		$this->set_locale();
		$this->define_hooks();
		$this->loader->run();
	}

	/**
	 * Run database and option migrations on version update.
	 */
	private function maybe_upgrade(): void {
		if ( get_option( 'swpm_db_version' ) === SWPM_VERSION ) {
			return;
		}
		SWPM_Activator::activate();
	}

	/**
	 * Load and instantiate dependencies.
	 */
	private function load_dependencies(): void {
		// i18n.
		require_once SWPM_PLUGIN_DIR . 'includes/class-i18n.php';

		// wp-config.php constants support (must run before any get_option calls).
		swpm_init_constant_overrides();

		// Providers.
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-send-result.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/interface-provider.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-factory.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-phpmail.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-smtp.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-sendlayer.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-smtpcom.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-gmail.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-outlook.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-mailgun.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-sendgrid.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-postmark.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-brevo.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-ses.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-resend.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-elasticemail.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-mailjet.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-mailersend.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-smtp2go.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-sparkpost.php';
		require_once SWPM_PLUGIN_DIR . 'includes/providers/class-provider-zoho.php';

		// Core modules.
		require_once SWPM_PLUGIN_DIR . 'includes/core/class-subscriber.php';
		require_once SWPM_PLUGIN_DIR . 'includes/core/class-template-engine.php';
		require_once SWPM_PLUGIN_DIR . 'includes/core/class-queue.php';
		require_once SWPM_PLUGIN_DIR . 'includes/core/class-mailer.php';
		require_once SWPM_PLUGIN_DIR . 'includes/core/class-cron.php';
		require_once SWPM_PLUGIN_DIR . 'includes/core/class-connections-manager.php';
		require_once SWPM_PLUGIN_DIR . 'includes/core/class-tracker.php';
		require_once SWPM_PLUGIN_DIR . 'includes/core/class-analytics.php';
		require_once SWPM_PLUGIN_DIR . 'includes/core/class-router.php';
		require_once SWPM_PLUGIN_DIR . 'includes/core/class-db-repair.php';
		require_once SWPM_PLUGIN_DIR . 'includes/core/class-conflict-detector.php';

		// Hooks.
		require_once SWPM_PLUGIN_DIR . 'includes/hooks/class-wp-mail-override.php';

		// Alarms.
		require_once SWPM_PLUGIN_DIR . 'includes/alarms/interface-alarm-channel.php';
		require_once SWPM_PLUGIN_DIR . 'includes/alarms/class-swpm-alarm-channel-slack.php';
		require_once SWPM_PLUGIN_DIR . 'includes/alarms/class-swpm-alarm-channel-discord.php';
		require_once SWPM_PLUGIN_DIR . 'includes/alarms/class-alarm-channel-teams.php';
		require_once SWPM_PLUGIN_DIR . 'includes/alarms/class-swpm-alarm-channel-twilio.php';
		require_once SWPM_PLUGIN_DIR . 'includes/alarms/class-swpm-alarm-channel-custom.php';
		require_once SWPM_PLUGIN_DIR . 'includes/alarms/class-swpm-alarm-dispatcher.php';

		// Triggers.
		require_once SWPM_PLUGIN_DIR . 'includes/triggers/class-trigger-base.php';
		require_once SWPM_PLUGIN_DIR . 'includes/triggers/class-trigger-manager.php';
		require_once SWPM_PLUGIN_DIR . 'includes/triggers/class-trigger-new-post.php';
		require_once SWPM_PLUGIN_DIR . 'includes/triggers/class-trigger-new-user.php';
		require_once SWPM_PLUGIN_DIR . 'includes/triggers/class-trigger-user-login.php';
		require_once SWPM_PLUGIN_DIR . 'includes/triggers/class-trigger-new-comment.php';
		require_once SWPM_PLUGIN_DIR . 'includes/triggers/class-trigger-password-reset.php';
		require_once SWPM_PLUGIN_DIR . 'includes/triggers/class-trigger-custom.php';

		// Admin.
		require_once SWPM_PLUGIN_DIR . 'includes/admin/class-admin.php';
		require_once SWPM_PLUGIN_DIR . 'includes/admin/class-settings.php';
		require_once SWPM_PLUGIN_DIR . 'includes/admin/class-subscribers-list-table.php';
		require_once SWPM_PLUGIN_DIR . 'includes/admin/class-template-editor.php';
		require_once SWPM_PLUGIN_DIR . 'includes/admin/class-setup-wizard.php';
		require_once SWPM_PLUGIN_DIR . 'includes/admin/class-oauth-manager.php';
		require_once SWPM_PLUGIN_DIR . 'includes/admin/class-dns-checker.php';
		require_once SWPM_PLUGIN_DIR . 'includes/admin/class-logs-list-table.php';
		require_once SWPM_PLUGIN_DIR . 'includes/admin/class-dashboard-data.php';

		// Public.
		require_once SWPM_PLUGIN_DIR . 'includes/public/class-swpm-public.php';
		require_once SWPM_PLUGIN_DIR . 'includes/public/class-swpm-shortcode.php';
		require_once SWPM_PLUGIN_DIR . 'includes/public/class-swpm-ajax-handler.php';
		require_once SWPM_PLUGIN_DIR . 'includes/public/class-swpm-rest-api.php';

		// Core.
		self::$instances['subscriber']      = new SWPM_Subscriber();
		self::$instances['template_engine'] = new SWPM_Template_Engine();
		self::$instances['queue']           = new SWPM_Queue();
		self::$instances['mailer']          = new SWPM_Mailer( self::$instances['queue'] );

		// Provider factory.
		self::$instances['provider_factory'] = new SWPM_Provider_Factory();
		self::$instances['provider']         = self::$instances['provider_factory']->make();

		// OAuth manager (always loaded — handles AJAX callbacks).
		self::$instances['oauth'] = new SWPM_OAuth_Manager();

		// Connections manager (wraps provider with failover).
		self::$instances['connections'] = new SWPM_Connections_Manager(
			self::$instances['provider'],
			self::$instances['provider_factory']
		);

		// wp_mail override (uses connections manager for failover support).
		self::$instances['mail_override'] = new SWPM_WP_Mail_Override(
			self::$instances['connections'],
			self::$instances['queue']
		);

		// Triggers.
		self::$instances['trigger_manager'] = new SWPM_Trigger_Manager();

		// Email tracking.
		self::$instances['tracker']   = new SWPM_Tracker();
		self::$instances['analytics'] = new SWPM_Analytics();

		// Smart routing.
		self::$instances['router'] = new SWPM_Router( self::$instances['provider_factory'] );

		// Alarm dispatcher.
		self::$instances['alarm_dispatcher'] = new SWPM_Alarm_Dispatcher();

		// Diagnostic tools.
		self::$instances['db_repair']          = new SWPM_DB_Repair();
		self::$instances['conflict_detector']  = new SWPM_Conflict_Detector();

		// WP-CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once SWPM_PLUGIN_DIR . 'includes/cli/class-cli.php';
			\WP_CLI::add_command( 'swpmail', 'SWPM_CLI' );
		}

		// Admin / Public.
		if ( is_admin() ) {
			self::$instances['admin']          = new SWPM_Admin( $this->loader );
			self::$instances['settings']       = new SWPM_Settings();
			self::$instances['template_editor']= new SWPM_Template_Editor( $this->loader );
			self::$instances['setup_wizard']   = new SWPM_Setup_Wizard();
			self::$instances['dns_checker']    = new SWPM_DNS_Checker();
			self::$instances['dashboard_data'] = new SWPM_Dashboard_Data();
		} else {
			self::$instances['public']    = new SWPM_Public( $this->loader );
			self::$instances['shortcode'] = new SWPM_Shortcode( self::$instances['subscriber'] );
		}

		self::$instances['ajax'] = new SWPM_Ajax_Handler( self::$instances['subscriber'], self::$instances['mailer'] );
		self::$instances['rest'] = new SWPM_REST_API( self::$instances['subscriber'], self::$instances['mailer'] );
		self::$instances['cron'] = new SWPM_Cron(
			self::$instances['queue'],
			self::$instances['subscriber']
		);
	}

	/**
	 * Set locale / i18n.
	 */
	private function set_locale(): void {
		$i18n = new SWPM_i18n();
		$this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Define all plugin hooks.
	 */
	private function define_hooks(): void {
		// wp_mail override (early priority).
		$this->loader->add_action( 'plugins_loaded', self::$instances['mail_override'], 'init', 5 );

		// Trigger registration.
		$this->loader->add_action( 'init', self::$instances['trigger_manager'], 'init', 15 );

		// Email tracking endpoints.
		self::$instances['tracker']->init();

		// Alarm dispatcher (listens to swpm_mail_failed & swpm_failover_triggered).
		self::$instances['alarm_dispatcher']->init();

		// Cron.
		$this->loader->add_action( 'init', self::$instances['cron'], 'register' );

		// REST API.
		$this->loader->add_action( 'rest_api_init', self::$instances['rest'], 'register_routes' );

		// Diagnostic tools AJAX.
		add_action( 'wp_ajax_swpm_db_diagnose', array( self::$instances['db_repair'], 'ajax_diagnose' ) );
		add_action( 'wp_ajax_swpm_db_repair', array( self::$instances['db_repair'], 'ajax_repair' ) );
		add_action( 'wp_ajax_swpm_detect_conflicts', array( self::$instances['conflict_detector'], 'ajax_detect' ) );

		// Shortcode (front-end only).
		if ( ! is_admin() ) {
			self::$instances['shortcode']->register();
		}

		// AJAX (always).
		self::$instances['ajax']->register();

		// GDPR hooks.
		$this->loader->add_filter( 'wp_privacy_personal_data_exporters', self::$instances['subscriber'], 'register_exporter' );
		$this->loader->add_filter( 'wp_privacy_personal_data_erasers', self::$instances['subscriber'], 'register_eraser' );

		// wp_mail failure logging.
		add_action( 'wp_mail_failed', array( $this, 'handle_wp_mail_failure' ) );

		// Admin notice: cron not running.
		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'cron_health_notice' ) );
		}

		// Front-end action: confirm / unsubscribe via URL.
		add_action( 'template_redirect', array( $this, 'handle_front_actions' ) );
	}

	/**
	 * Handle wp_mail failures.
	 *
	 * @param \WP_Error $error Error object.
	 */
	public function handle_wp_mail_failure( \WP_Error $error ): void {
		swpm_log( 'error', 'wp_mail failed: ' . $error->get_error_message(), array(
			'data' => $error->get_error_data(),
		) );

		if ( get_transient( 'swpm_mail_failed_notified' ) ) {
			return;
		}

		$admin_notify = get_option( 'swpm_notify_admin_on_failure', true );
		if ( $admin_notify ) {
			set_transient( 'swpm_mail_failed_notified', 1, 10 * MINUTE_IN_SECONDS );
			// Bypass SWPMail override to avoid recursive failure.
			add_filter( 'swpm_skip_override', '__return_true' );
			try {
				wp_mail(
					get_option( 'admin_email' ),
					'[SWPMail] Mail sending failed',
					'Error: ' . $error->get_error_message()
				);
			} finally {
				remove_filter( 'swpm_skip_override', '__return_true' );
			}
		}
	}

	/**
	 * Admin notice if cron hasn't run recently.
	 */
	public function cron_health_notice(): void {
		$last_run = get_option( 'swpm_queue_last_run', false );
		if ( false === $last_run ) {
			return;
		}
		if ( time() - (int) $last_run > HOUR_IN_SECONDS && get_option( 'swpm_mail_provider' ) ) {
			echo '<div class="notice notice-warning"><p>'
				. esc_html__( 'SWPMail: The mail queue has not run in over 1 hour. Check your WordPress cron setup.', 'swpmail' )
				. '</p></div>';
		}
	}

	/**
	 * Handle front-end confirm/unsubscribe actions.
	 *
	 * GET requests show a confirmation form (safe for email link scanners).
	 * POST requests with valid nonce actually perform the action.
	 */
	public function handle_front_actions(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['swpm_action'] ) ? sanitize_key( $_GET['swpm_action'] ) : '';
		if ( empty( $action ) || ! in_array( $action, array( 'confirm', 'unsubscribe' ), true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		if ( empty( $token ) ) {
			return;
		}

		/** @var SWPM_Subscriber $subscriber */
		$subscriber = self::$instances['subscriber'];

		// On POST — execute the action (nonce-verified).
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			// Rate limit confirm/unsubscribe actions by IP.
			$ip        = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
			$rate_key  = 'swpm_action_rate_' . md5( $ip );
			$attempts  = (int) get_transient( $rate_key );
			if ( $attempts >= 10 ) {
				wp_die(
					esc_html__( 'Too many requests. Please try again later.', 'swpmail' ),
					esc_html__( 'Rate Limited', 'swpmail' ),
					array( 'response' => 429 )
				);
			}
			set_transient( $rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );

			// Verify nonce.
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'swpm_' . $action . '_' . $token ) ) {
				wp_die(
					esc_html__( 'Security check failed. Please try the link again.', 'swpmail' ),
					esc_html__( 'Error', 'swpmail' ),
					array( 'response' => 403 )
				);
			}

			if ( 'confirm' === $action ) {
				$confirmed = $subscriber->confirm( $token );
				if ( $confirmed ) {
					$sub = $subscriber->get_by_token( $token );
					// Send welcome email.
					if ( $sub ) {
						/** @var SWPM_Template_Engine $engine */
						$engine = self::$instances['template_engine'];
						$body   = $engine->render( 'welcome', array(
							'subscriber_name' => $sub->name ?: $sub->email,
						) );

						add_filter( 'swpm_skip_override', '__return_true' );
						try {
							wp_mail(
								$sub->email,
								sprintf(
									/* translators: %s: site name */
									__( 'Welcome to %s!', 'swpmail' ),
									get_bloginfo( 'name' )
								),
								$body,
								array( 'Content-Type: text/html; charset=UTF-8' )
							);
						} finally {
							remove_filter( 'swpm_skip_override', '__return_true' );
						}
					}
					wp_die(
						esc_html__( 'Your subscription has been confirmed! Thank you.', 'swpmail' ),
						esc_html__( 'Subscription Confirmed', 'swpmail' ),
						array( 'response' => 200 )
					);
				} else {
					wp_die(
						esc_html__( 'Invalid or expired confirmation link.', 'swpmail' ),
						esc_html__( 'Confirmation Failed', 'swpmail' ),
						array( 'response' => 400 )
					);
				}
			}

			if ( 'unsubscribe' === $action ) {
				// Verify HMAC signature prevents brute-force unsubscribes.
				$sig = isset( $_POST['sig'] ) ? sanitize_text_field( wp_unslash( $_POST['sig'] ) ) : '';
				$expected = hash_hmac( 'sha256', $token, wp_salt( 'nonce' ) );
				if ( ! hash_equals( $expected, $sig ) ) {
					wp_die(
						esc_html__( 'Invalid unsubscribe link.', 'swpmail' ),
						esc_html__( 'Unsubscribe Failed', 'swpmail' ),
						array( 'response' => 403 )
					);
				}

				$unsubscribed = $subscriber->unsubscribe( $token );
				if ( $unsubscribed ) {
					wp_die(
						esc_html__( 'You have been unsubscribed successfully.', 'swpmail' ),
						esc_html__( 'Unsubscribed', 'swpmail' ),
						array( 'response' => 200 )
					);
				} else {
					wp_die(
						esc_html__( 'Invalid unsubscribe link.', 'swpmail' ),
						esc_html__( 'Unsubscribe Failed', 'swpmail' ),
						array( 'response' => 400 )
					);
				}
			}
			return;
		}

		// On GET — show a confirmation form (email link scanners won't POST).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sig = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';
		$this->render_action_form( $action, $token, $sig );
	}

	/**
	 * Render a POST confirmation form for confirm/unsubscribe actions.
	 *
	 * @param string $action Action: confirm or unsubscribe.
	 * @param string $token  Subscriber token.
	 * @param string $sig    HMAC signature for unsubscribe.
	 */
	private function render_action_form( string $action, string $token, string $sig = '' ): void {
		$nonce   = wp_create_nonce( 'swpm_' . $action . '_' . $token );
		$form_url = esc_url( add_query_arg( array(
			'swpm_action' => $action,
			'token'       => rawurlencode( $token ),
		), home_url() ) );

		if ( 'confirm' === $action ) {
			$heading     = esc_html__( 'Confirm Subscription', 'swpmail' );
			$description = esc_html__( 'Click the button below to confirm your email subscription.', 'swpmail' );
			$button_text = esc_html__( 'Confirm My Subscription', 'swpmail' );
		} else {
			$heading     = esc_html__( 'Unsubscribe', 'swpmail' );
			$description = esc_html__( 'Click the button below to unsubscribe from our emails.', 'swpmail' );
			$button_text = esc_html__( 'Unsubscribe', 'swpmail' );
		}

		$html = '<div style="max-width:480px;margin:40px auto;font-family:-apple-system,BlinkMacSystemFont,sans-serif;text-align:center;">'
			. '<h2>' . $heading . '</h2>'
			. '<p>' . $description . '</p>'
			. '<form method="post" action="' . $form_url . '">'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />'
			. ( ! empty( $sig ) ? '<input type="hidden" name="sig" value="' . esc_attr( $sig ) . '" />' : '' )
			. '<button type="submit" style="background:#0073aa;color:#fff;border:none;padding:12px 32px;font-size:16px;border-radius:4px;cursor:pointer;">'
			. $button_text
			. '</button>'
			. '</form>'
			. '</div>';

		wp_die( $html, $heading, array( 'response' => 200 ) );
	}

	/**
	 * Service container access.
	 *
	 * @param string $key Service key.
	 * @return object|null
	 */
	public static function get( string $key ): ?object {
		return self::$instances[ $key ] ?? null;
	}
}
