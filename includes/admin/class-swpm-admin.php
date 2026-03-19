<?php
/**
 * Admin class — menu, assets, hooks.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin menus, assets and hooks.
 */
class SWPM_Admin {

	/**

	 * Variable.
	 *
	 * @var SWPM_Loader
	 */
	private SWPM_Loader $loader;

	/**
	 * Constructor.
	 *
	 * @param SWPM_Loader $loader Loader.
	 */
	public function __construct( SWPM_Loader $loader ) {
		$this->loader = $loader;

		$this->loader->add_action( 'admin_menu', $this, 'add_admin_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_scripts' );

		add_action( 'wp_ajax_swpm_log_tracking_detail', array( 'SWPM_Logs_List_Table', 'ajax_tracking_detail' ) );
	}

	/**
	 * Register admin menu pages.
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'SWPMail', 'swpmail' ),
			__( 'SWPMail', 'swpmail' ),
			'manage_options',
			'swpmail',
			array( $this, 'display_dashboard' ),
			'dashicons-email',
			65
		);

		add_submenu_page(
			'swpmail',
			__( 'Dashboard', 'swpmail' ),
			__( 'Dashboard', 'swpmail' ),
			'manage_options',
			'swpmail',
			array( $this, 'display_dashboard' )
		);

		add_submenu_page(
			'swpmail',
			__( 'Subscribers', 'swpmail' ),
			__( 'Subscribers', 'swpmail' ),
			'manage_options',
			'swpmail-subscribers',
			array( $this, 'display_subscribers' )
		);

		add_submenu_page(
			'swpmail',
			__( 'Email Templates', 'swpmail' ),
			__( 'Email Templates', 'swpmail' ),
			'manage_options',
			'swpmail-templates',
			array( $this, 'display_templates' )
		);

		add_submenu_page(
			'swpmail',
			__( 'Triggers', 'swpmail' ),
			__( 'Triggers', 'swpmail' ),
			'manage_options',
			'swpmail-triggers',
			array( $this, 'display_triggers' )
		);

		add_submenu_page(
			'swpmail',
			__( 'Mail Settings', 'swpmail' ),
			__( 'Mail Settings', 'swpmail' ),
			'manage_options',
			'swpmail-mail-settings',
			array( $this, 'display_mail_settings' )
		);

		add_submenu_page(
			'swpmail',
			__( 'DNS Checker', 'swpmail' ),
			__( 'DNS Checker', 'swpmail' ),
			'manage_options',
			'swpmail-dns-checker',
			array( $this, 'display_dns_checker' )
		);

		add_submenu_page(
			'swpmail',
			__( 'Smart Routing', 'swpmail' ),
			__( 'Smart Routing', 'swpmail' ),
			'manage_options',
			'swpmail-routing',
			array( $this, 'display_routing' )
		);

		add_submenu_page(
			'swpmail',
			__( 'Email Logs', 'swpmail' ),
			__( 'Email Logs', 'swpmail' ),
			'manage_options',
			'swpmail-logs',
			array( $this, 'display_logs' )
		);

		add_submenu_page(
			'swpmail',
			__( 'Alarms', 'swpmail' ),
			__( 'Alarms', 'swpmail' ),
			'manage_options',
			'swpmail-alarms',
			array( $this, 'display_alarms' )
		);

		add_submenu_page(
			'swpmail',
			__( 'Settings', 'swpmail' ),
			__( 'Settings', 'swpmail' ),
			'manage_options',
			'swpmail-settings',
			array( $this, 'display_settings' )
		);

		add_submenu_page(
			'swpmail',
			__( 'Tools', 'swpmail' ),
			__( 'Tools', 'swpmail' ),
			'manage_options',
			'swpmail-tools',
			array( $this, 'display_tools' )
		);
	}

	/**
	 * Enqueue admin CSS.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_styles( string $hook_suffix ): void {
		if ( ! $this->is_swpmail_page( $hook_suffix ) ) {
			return;
		}
		wp_enqueue_style(
			'swpmail-admin',
			SWPM_PLUGIN_URL . 'admin/css/swpmail-admin.css',
			array(),
			SWPM_VERSION
		);
	}

	/**
	 * Enqueue admin JS.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_scripts( string $hook_suffix ): void {
		if ( ! $this->is_swpmail_page( $hook_suffix ) ) {
			return;
		}
		wp_enqueue_script(
			'swpmail-admin',
			SWPM_PLUGIN_URL . 'admin/js/swpmail-admin.js',
			array( 'jquery' ),
			SWPM_VERSION,
			true
		);
		wp_localize_script(
			'swpmail-admin',
			'swpmAdmin',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'swpm_admin_nonce' ),
				'definedConstants' => swpm_get_defined_constants(),
				'i18n'             => array(
					'testing'                => __( 'Testing...', 'swpmail' ),
					'testSuccess'            => __( 'Connection successful! Test email sent.', 'swpmail' ),
					'testFailed'             => __( 'Test failed: ', 'swpmail' ),
					'confirmDelete'          => __( 'Are you sure you want to delete this subscriber?', 'swpmail' ),
					'saved'                  => __( 'Settings saved.', 'swpmail' ),
					'oauthDisconnectConfirm' => __( 'Are you sure you want to disconnect OAuth? You will need to re-authorize.', 'swpmail' ),
					'checking'               => __( 'Checking…', 'swpmail' ),
					'dnsAllPassed'           => __( 'All checks passed', 'swpmail' ),
					'dnsSomeIssues'          => __( 'Some issues found', 'swpmail' ),
					'dnsCritical'            => __( 'Critical issues detected', 'swpmail' ),
					'dnsEnterDomain'         => __( 'Please enter a domain name.', 'swpmail' ),
					'routingSave'            => __( 'Save Rules', 'swpmail' ),
					'saving'                 => __( 'Saving…', 'swpmail' ),
					'routingMatchedRules'    => __( 'Matched rules', 'swpmail' ),
					'alarmSaved'             => __( 'Alarm settings saved.', 'swpmail' ),
					'alarmTestSuccess'       => __( 'Test notification sent!', 'swpmail' ),
					'alarmTestFailed'        => __( 'Test failed.', 'swpmail' ),
					'constDefined'           => __( 'Defined in wp-config.php', 'swpmail' ),
					'constProviderNotice'    => __( 'The mail provider is locked via wp-config.php constant.', 'swpmail' ),
				),
			)
		);
	}

	/**
	 * Display dashboard page.
	 */
	public function display_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SWPM_PLUGIN_DIR . 'admin/partials/display-dashboard.php';
	}

	/**
	 * Display subscribers page.
	 */
	public function display_subscribers(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SWPM_PLUGIN_DIR . 'admin/partials/display-subscribers.php';
	}

	/**
	 * Display templates page.
	 */
	public function display_templates(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SWPM_PLUGIN_DIR . 'admin/partials/display-templates.php';
	}

	/**
	 * Display triggers page.
	 */
	public function display_triggers(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SWPM_PLUGIN_DIR . 'admin/partials/display-triggers.php';
	}

	/**
	 * Display mail settings page.
	 */
	public function display_mail_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SWPM_PLUGIN_DIR . 'admin/partials/display-mail-settings.php';
	}

	/**
	 * Display DNS checker page.
	 */
	public function display_dns_checker(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SWPM_PLUGIN_DIR . 'admin/partials/display-dns-checker.php';
	}

	/**
	 * Display smart routing page.
	 */
	public function display_routing(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SWPM_PLUGIN_DIR . 'admin/partials/display-routing.php';
	}

	/**
	 * Display email logs page.
	 */
	public function display_logs(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SWPM_PLUGIN_DIR . 'admin/partials/display-logs.php';
	}

	/**
	 * Display alarm settings page.
	 */
	public function display_alarms(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SWPM_PLUGIN_DIR . 'admin/partials/display-alarms.php';
	}

	/**
	 * Display general settings page.
	 */
	public function display_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SWPM_PLUGIN_DIR . 'admin/partials/display-settings.php';
	}

	/**
	 * Display tools page (DB repair & conflict detector).
	 */
	public function display_tools(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include SWPM_PLUGIN_DIR . 'admin/partials/display-tools.php';
	}

	/**
	 * Check if current page is a SWPMail admin page.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return bool
	 */
	private function is_swpmail_page( string $hook_suffix ): bool {
		return strpos( $hook_suffix, 'swpmail' ) !== false;
	}
}
