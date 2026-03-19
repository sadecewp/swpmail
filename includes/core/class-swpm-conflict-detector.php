<?php
/**
 * Plugin conflict detector.
 *
 * Detects conflicts with other WordPress plugins that may interfere
 * With SWPMail's email delivery, SMTP configuration, or cron scheduling.
 *
 * Checks:
 *  - Known conflicting SMTP/mail plugins.
 *  - wp_mail override conflicts.
 *  - Cron interference (alternative cron, disabled cron).
 *  - PHP mail function availability.
 *  - Required PHP extensions.
 *  - WordPress configuration issues.
 *
 * @package SWPMail
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SWPM_Conflict_Detector.
 */
class SWPM_Conflict_Detector {

	/** Known conflicting plugins: slug => label. */
	private const CONFLICTING_PLUGINS = array(
		'wp-mail-smtp/wp_mail_smtp.php'               => 'WP Mail SMTP',
		'easy-wp-smtp/easy-wp-smtp.php'               => 'Easy WP SMTP',
		'post-smtp/postman-smtp.php'                  => 'Post SMTP',
		'smtp-mailer/main.php'                        => 'SMTP Mailer',
		'fluent-smtp/fluent-smtp.php'                 => 'FluentSMTP',
		'gmail-smtp/main.php'                         => 'Gmail SMTP',
		'mailgun/mailgun.php'                         => 'Mailgun (Official)',
		'sendgrid-email-delivery-simplified/wpsendgrid.php' => 'SendGrid',
		'sparkpost/wordpress-sparkpost.php'           => 'SparkPost',
		'mailster/mailster.php'                       => 'Mailster',
		'newsletter/plugin.php'                       => 'Newsletter',
		'email-subscribers/email-subscribers.php'     => 'Email Subscribers & Newsletters',
		'the-starter-templates/starter-templates.php' => false, // Not a conflict.
		'wp-ses/wp-ses.php'                           => 'WP Offload SES',
		'brevo-for-woocommerce/sendinblue.php'        => 'Brevo (Sendinblue)',
		'mailchimp-for-wp/mailchimp-for-wp.php'       => 'MC4WP: Mailchimp for WordPress',
		'wp-offload-ses/wp-offload-ses.php'           => 'WP Offload SES Lite',
		'turbosmtp/turbo-smtp.php'                    => 'turboSMTP',
	);

	/** Required PHP extensions. */
	private const REQUIRED_EXTENSIONS = array(
		'openssl',
		'mbstring',
		'curl',
		'json',
	);

	/** Recommended PHP extensions. */
	private const RECOMMENDED_EXTENSIONS = array(
		'intl',
		'fileinfo',
	);

	// ------------------------------------------------------------------
	// Core detection
	// ----------------------------------------------------------------

	/**
	 * Run all conflict checks.
	 *
	 * @return array{conflicts: array, summary: array}
	 */
	public function detect(): array {
		$conflicts = array();

		$conflicts = array_merge( $conflicts, $this->check_conflicting_plugins() );
		$conflicts = array_merge( $conflicts, $this->check_wp_mail_override() );
		$conflicts = array_merge( $conflicts, $this->check_cron_status() );
		$conflicts = array_merge( $conflicts, $this->check_php_extensions() );
		$conflicts = array_merge( $conflicts, $this->check_php_config() );
		$conflicts = array_merge( $conflicts, $this->check_wp_config() );
		$conflicts = array_merge( $conflicts, $this->check_mu_plugins() );

		$critical = count( array_filter( $conflicts, fn( $c ) => 'critical' === $c['severity'] ) );
		$warning  = count( array_filter( $conflicts, fn( $c ) => 'warning' === $c['severity'] ) );
		$info     = count( array_filter( $conflicts, fn( $c ) => 'info' === $c['severity'] ) );

		return array(
			'conflicts' => $conflicts,
			'summary'   => array(
				'total'    => count( $conflicts ),
				'critical' => $critical,
				'warning'  => $warning,
				'info'     => $info,
				'clean'    => 0 === $critical && 0 === $warning,
			),
		);
	}

	/**
	 * Check for active plugins known to conflict with SWPMail.
	 *
	 * @return array
	 */
	public function check_conflicting_plugins(): array {
		$conflicts = array();
		$active    = get_option( 'active_plugins', array() );
		if ( ! is_array( $active ) ) {
			$active = array();
		}

		// Multisite network-activated plugins.
		if ( is_multisite() ) {
			$network = array_keys( get_site_option( 'active_sitewide_plugins', array() ) );
			$active  = array_merge( $active, $network );
		}

		foreach ( self::CONFLICTING_PLUGINS as $slug => $label ) {
			if ( false === $label ) {
				continue; // Not actually a conflict.
			}
			if ( in_array( $slug, $active, true ) ) {
				$severity    = $this->categorize_plugin_conflict( $slug );
				$conflicts[] = array(
					'code'       => 'conflicting_plugin',
					'severity'   => $severity,
					'message'    => sprintf( '"%s" is active and may conflict with SWPMail email delivery.', $label ),
					'plugin'     => $slug,
					'label'      => $label,
					'resolution' => sprintf( 'Deactivate "%s" or disable its SMTP/mail override features.', $label ),
				);
			}
		}

		return $conflicts;
	}

	/**
	 * Check if wp_mail is already overridden by another plugin.
	 *
	 * @return array
	 */
	public function check_wp_mail_override(): array {
		$conflicts = array();

		if ( ! get_option( 'swpm_override_wp_mail', true ) ) {
			return $conflicts;
		}

		// Check if wp_mail function was defined before us (pluggable function).
		if ( function_exists( 'wp_mail' ) ) {
			try {
				$ref  = new \ReflectionFunction( 'wp_mail' );
				$file = $ref->getFileName();

				// wp_mail should come from pluggable.php or our override.
				$is_pluggable = str_contains( $file, 'pluggable.php' );
				$is_ours      = str_contains( $file, 'swpmail' );

				if ( ! $is_pluggable && ! $is_ours ) {
					$conflicts[] = array(
						'code'       => 'wp_mail_override_conflict',
						'severity'   => 'critical',
						'message'    => sprintf( 'wp_mail() is already overridden by: %s', $file ),
						'file'       => $file,
						// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
						'resolution' => 'Another plugin has replaced wp_mail(). SWPMail\'s mail override will not work. Deactivate the conflicting plugin or disable SWPMail\'s wp_mail override.',
					);
				// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				}
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			} catch ( \ReflectionException $e ) {
				// Cannot determine — skip.
			}
		}

		return $conflicts;
	}

	/**
	 * Check WordPress cron health.
	 *
	 * @return array
	 */
	public function check_cron_status(): array {
		$conflicts = array();

		// DISABLE_WP_CRON.
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$conflicts[] = array(
				'code'       => 'cron_disabled',
				'severity'   => 'warning',
				'message'    => 'WordPress cron is disabled (DISABLE_WP_CRON = true).',
				'resolution' => 'Ensure a system cron job calls wp-cron.php regularly, or enable WP cron.',
			);
		}

		// ALTERNATE_WP_CRON.
		if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
			$conflicts[] = array(
				'code'       => 'cron_alternate',
				'severity'   => 'info',
				'message'    => 'Alternate cron mode is active (ALTERNATE_WP_CRON = true).',
				'resolution' => 'This may cause queue processing delays. Consider using a real system cron.',
			);
		}

		// Check if swpm cron events are scheduled.
		$required_events = array(
			'swpm_process_queue',
			'swpm_cleanup_logs',
			'swpm_cleanup_queue',
		);

		foreach ( $required_events as $hook ) {
			$next = wp_next_scheduled( $hook );
			if ( false === $next ) {
				$conflicts[] = array(
					'code'       => 'cron_event_missing',
					'severity'   => 'warning',
					'message'    => sprintf( 'Cron event "%s" is not scheduled.', $hook ),
					'hook'       => $hook,
					'resolution' => 'Visit Settings → General and save, or deactivate and reactivate SWPMail.',
				);
			}
		}

		return $conflicts;
	}

	/**
	 * Check required/recommended PHP extensions.
	 *
	 * @return array
	 */
	public function check_php_extensions(): array {
		$conflicts = array();

		foreach ( self::REQUIRED_EXTENSIONS as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$conflicts[] = array(
					'code'       => 'missing_php_extension',
					'severity'   => 'critical',
					'message'    => sprintf( 'Required PHP extension "%s" is not loaded.', $ext ),
					'extension'  => $ext,
					'resolution' => sprintf( 'Install and enable the PHP %s extension.', $ext ),
				);
			}
		}

		foreach ( self::RECOMMENDED_EXTENSIONS as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$conflicts[] = array(
					'code'       => 'missing_recommended_extension',
					'severity'   => 'info',
					'message'    => sprintf( 'Recommended PHP extension "%s" is not loaded.', $ext ),
					'extension'  => $ext,
					'resolution' => sprintf( 'Install the PHP %s extension for optimal performance.', $ext ),
				);
			}
		}

		return $conflicts;
	}

	/**
	 * Check PHP configuration relevant to email sending.
	 *
	 * @return array
	 */
	public function check_php_config(): array {
		$conflicts = array();

		// Memory limit below 64M.
		$memory = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		if ( $memory > 0 && $memory < 64 * MB_IN_BYTES ) {
			$conflicts[] = array(
				'code'       => 'low_memory_limit',
				'severity'   => 'warning',
				'message'    => sprintf( 'PHP memory_limit is %s (minimum recommended: 64M).', ini_get( 'memory_limit' ) ),
				'resolution' => 'Increase PHP memory_limit to at least 64M.',
			);
		}

		// max_execution_time below 30s.
		$max_exec = (int) ini_get( 'max_execution_time' );
		if ( $max_exec > 0 && $max_exec < 30 ) {
			$conflicts[] = array(
				'code'       => 'low_execution_time',
				'severity'   => 'warning',
				'message'    => sprintf( 'PHP max_execution_time is %ds (minimum: 30s).', $max_exec ),
				'resolution' => 'Increase max_execution_time to at least 30 seconds.',
			);
		}

		// allow_url_fopen disabled (needed for some API providers).
		if ( ! ini_get( 'allow_url_fopen' ) ) {
			$conflicts[] = array(
				'code'       => 'no_url_fopen',
				'severity'   => 'info',
				'message'    => 'allow_url_fopen is disabled. SWPMail uses wp_remote_* (cURL) so this is usually fine.',
				'resolution' => 'No action needed if cURL is available.',
			);
		}

		return $conflicts;
	}

	/**
	 * Check WordPress configuration issues.
	 *
	 * @return array
	 */
	public function check_wp_config(): array {
		$conflicts = array();

		// WP_DEBUG + WP_DEBUG_DISPLAY in production.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG
			&& defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
			$conflicts[] = array(
				'code'       => 'debug_display_on',
				'severity'   => 'info',
				'message'    => 'WP_DEBUG_DISPLAY is enabled. PHP errors may leak into email output.',
				'resolution' => 'Set WP_DEBUG_DISPLAY to false in production.',
			);
		}

		// SSL verification.
		if ( defined( 'SWPM_SSL_VERIFY' ) && ! SWPM_SSL_VERIFY ) {
			$conflicts[] = array(
				'code'       => 'ssl_verify_disabled',
				'severity'   => 'warning',
				'message'    => 'SSL verification is disabled (SWPM_SSL_VERIFY = false). This is a security risk.',
				'resolution' => 'Remove SWPM_SSL_VERIFY or set it to true.',
			);
		}

		// WordPress version check.
		global $wp_version;
		if ( version_compare( $wp_version, '6.0', '<' ) ) {
			$conflicts[] = array(
				'code'       => 'old_wp_version',
				'severity'   => 'warning',
				'message'    => sprintf( 'WordPress %s is below the minimum required version (6.0).', $wp_version ),
				'resolution' => 'Update WordPress to version 6.0 or later.',
			);
		}

		// PHP version check.
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			$conflicts[] = array(
				'code'       => 'old_php_version',
				'severity'   => 'critical',
				'message'    => sprintf( 'PHP %s is below the minimum required version (7.4).', PHP_VERSION ),
				'resolution' => 'Upgrade PHP to 7.4 or later.',
			);
		}

		return $conflicts;
	}

	/**
	 * Check must-use plugins for potential conflicts.
	 *
	 * @return array
	 */
	public function check_mu_plugins(): array {
		$conflicts = array();
		$mu_dir    = WPMU_PLUGIN_DIR;

		if ( ! is_dir( $mu_dir ) ) {
			return $conflicts;
		}

		$mu_files = glob( $mu_dir . '/*.php' );
		if ( ! is_array( $mu_files ) ) {
			return $conflicts;
		}

		foreach ( $mu_files as $file ) {
			$content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $content ) {
				continue;
			}

			// Check if mu-plugin overrides wp_mail or phpmailer_init.
			if ( preg_match( '/function\s+wp_mail\s*\(/i', $content )
				|| preg_match( '/add_action\s*\(\s*[\'"]phpmailer_init[\'"]/i', $content ) ) {
				$conflicts[] = array(
					'code'       => 'mu_plugin_mail_override',
					'severity'   => 'warning',
					'message'    => sprintf( 'Must-use plugin "%s" may override mail functions.', basename( $file ) ),
					'file'       => basename( $file ),
					'resolution' => 'Review the mu-plugin and ensure it does not conflict with SWPMail.',
				);
			}
		}

		return $conflicts;
	}

	// ------------------------------------------------------------------
	// Helpers
	// ----------------------------------------------------------------

	/**
	 * Categorize plugin conflict severity.
	 *
	 * SMTP/mail override plugins are critical; newsletter plugins are warnings.
	 *
	 * @param string $slug Plugin slug.
	 * @return string Severity: critical, warning, or info.
	 */
	private function categorize_plugin_conflict( string $slug ): string {
		$critical_slugs = array(
			'wp-mail-smtp/wp_mail_smtp.php',
			'easy-wp-smtp/easy-wp-smtp.php',
			'post-smtp/postman-smtp.php',
			'smtp-mailer/main.php',
			'fluent-smtp/fluent-smtp.php',
			'gmail-smtp/main.php',
			'wp-ses/wp-ses.php',
			'wp-offload-ses/wp-offload-ses.php',
		);

		return in_array( $slug, $critical_slugs, true ) ? 'critical' : 'warning';
	}

	// ------------------------------------------------------------------
	// AJAX handlers
	// ----------------------------------------------------------------

	/**
	 * AJAX: Run conflict detection.
	 */
	public function ajax_detect(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}

		wp_send_json_success( $this->detect() );
	}
}
