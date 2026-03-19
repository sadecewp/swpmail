<?php
/**
 * Connections Manager — backup provider, automatic failover, health checks.
 *
 * Wraps the primary provider with failover logic:
 *  - If the primary provider fails, automatically retries with the backup.
 *  - Tracks provider health via lightweight test sends.
 *  - Exposes connection status for admin dashboard.
 *
 * @package SWPMail
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Connections_Manager implements SWPM_Provider_Interface {

	/** @var SWPM_Provider_Interface Primary provider. */
	private SWPM_Provider_Interface $primary;

	/** @var SWPM_Provider_Interface|null Backup provider (null if not configured). */
	private ?SWPM_Provider_Interface $backup;

	/** @var SWPM_Provider_Factory */
	private SWPM_Provider_Factory $factory;

	/** Maximum consecutive failures before a provider is considered unhealthy. */
	private const FAILURE_THRESHOLD = 3;

	/** How long (seconds) to keep a provider marked as unhealthy before re-checking. */
	private const HEALTH_COOLDOWN = 300; // 5 minutes.

	/**
	 * @param SWPM_Provider_Interface $primary Primary provider instance.
	 * @param SWPM_Provider_Factory   $factory Factory for creating backup provider.
	 */
	public function __construct( SWPM_Provider_Interface $primary, SWPM_Provider_Factory $factory ) {
		$this->primary = $primary;
		$this->factory = $factory;
		$this->backup  = $this->create_backup_provider();

		add_action( 'wp_ajax_swpm_health_check', array( $this, 'ajax_health_check' ) );
		add_action( 'wp_ajax_swpm_get_connection_status', array( $this, 'ajax_get_status' ) );
	}

	/* ------------------------------------------------------------------
	 * Provider Interface Implementation (delegation with failover)
	 * ----------------------------------------------------------------*/

	public function get_key(): string {
		return $this->primary->get_key();
	}

	public function get_label(): string {
		return $this->primary->get_label();
	}

	/**
	 * Send with automatic failover.
	 *
	 * Flow:
	 * 1. If primary is healthy → send via primary.
	 * 2. On failure → increment primary failure count → try backup.
	 * 3. If primary is unhealthy (cooldown) → go directly to backup.
	 * 4. If both fail → return the most recent error.
	 */
	public function send(
		string $to,
		string $subject,
		string $body,
		array $headers = array(),
		array $attachments = array()
	): SWPM_Send_Result {
		$primary_healthy = $this->is_provider_healthy( 'primary' );

		// Try primary if healthy.
		if ( $primary_healthy ) {
			$result = $this->primary->send( $to, $subject, $body, $headers, $attachments );

			if ( $result->is_success() ) {
				$this->reset_failures( 'primary' );
				return $result;
			}

			// Primary failed — record it.
			$this->record_failure( 'primary' );
			swpm_log( 'warning', sprintf(
				'Primary provider (%s) failed: %s — attempting failover.',
				$this->primary->get_label(),
				$result->get_error_message()
			), array(
				'provider'   => $this->primary->get_key(),
				'error_code' => $result->get_error_code(),
			) );

			do_action( 'swpm_failover_triggered', $this->primary->get_key(), $result );
		} else {
			swpm_log( 'info', sprintf(
				'Primary provider (%s) is unhealthy, routing directly to backup.',
				$this->primary->get_label()
			) );
		}

		// Try backup.
		if ( $this->backup ) {
			$backup_result = $this->backup->send( $to, $subject, $body, $headers, $attachments );

			if ( $backup_result->is_success() ) {
				$this->reset_failures( 'backup' );
				swpm_log( 'info', sprintf(
					'Failover successful — sent via backup provider (%s).',
					$this->backup->get_label()
				) );
				return $backup_result;
			}

			// Backup also failed.
			$this->record_failure( 'backup' );
			swpm_log( 'error', sprintf(
				'Backup provider (%s) also failed: %s',
				$this->backup->get_label(),
				$backup_result->get_error_message()
			), array(
				'provider'   => $this->backup->get_key(),
				'error_code' => $backup_result->get_error_code(),
			) );

			return $backup_result;
		}

		// No backup configured; return original failure or generic error.
		if ( isset( $result ) ) {
			return $result;
		}

		return SWPM_Send_Result::failure(
			__( 'Primary provider is unhealthy and no backup is configured.', 'swpmail' ),
			'NO_HEALTHY_PROVIDER'
		);
	}

	/**
	 * Test connection for both primary and backup.
	 */
	public function test_connection(): SWPM_Send_Result {
		return $this->primary->test_connection();
	}

	/* ------------------------------------------------------------------
	 * Provider Health Tracking
	 * ----------------------------------------------------------------*/

	/**
	 * Check if a provider is considered healthy.
	 *
	 * @param string $slot 'primary' or 'backup'.
	 * @return bool
	 */
	public function is_provider_healthy( string $slot ): bool {
		$health = $this->get_health_data( $slot );

		// Never unhealthy yet.
		if ( $health['consecutive_failures'] < self::FAILURE_THRESHOLD ) {
			return true;
		}

		// In cooldown — check if it's expired.
		if ( $health['unhealthy_since'] > 0 && ( time() - $health['unhealthy_since'] ) >= self::HEALTH_COOLDOWN ) {
			// Cooldown expired → give it another chance.
			$this->reset_failures( $slot );
			return true;
		}

		return false;
	}

	/**
	 * Record a send failure for a provider slot.
	 *
	 * Uses a database-level lock to prevent race conditions when
	 * concurrent requests attempt to update the same health data.
	 *
	 * @param string $slot 'primary' or 'backup'.
	 */
	private function record_failure( string $slot ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "SELECT GET_LOCK('swpm_health_{$slot}', 2)" );

		$health = $this->get_health_data( $slot );
		$health['consecutive_failures']++;
		$health['total_failures']++;
		$health['last_failure_at'] = time();

		if ( $health['consecutive_failures'] >= self::FAILURE_THRESHOLD && 0 === $health['unhealthy_since'] ) {
			$health['unhealthy_since'] = time();
		}

		$this->save_health_data( $slot, $health );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "SELECT RELEASE_LOCK('swpm_health_{$slot}')" );
	}

	/**
	 * Reset failure count for a provider slot (on successful send).
	 *
	 * @param string $slot 'primary' or 'backup'.
	 */
	private function reset_failures( string $slot ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "SELECT GET_LOCK('swpm_health_{$slot}', 2)" );

		$health = $this->get_health_data( $slot );
		$health['consecutive_failures'] = 0;
		$health['unhealthy_since']      = 0;
		$health['last_success_at']      = time();
		$this->save_health_data( $slot, $health );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "SELECT RELEASE_LOCK('swpm_health_{$slot}')" );
	}

	/**
	 * Get health data for a provider slot.
	 *
	 * @param string $slot 'primary' or 'backup'.
	 * @return array
	 */
	public function get_health_data( string $slot ): array {
		$data = get_option( "swpm_connection_health_{$slot}", array() );
		return wp_parse_args( $data, array(
			'consecutive_failures' => 0,
			'total_failures'       => 0,
			'last_failure_at'      => 0,
			'last_success_at'      => 0,
			'unhealthy_since'      => 0,
			'last_check_at'        => 0,
			'last_check_ok'        => null,
		) );
	}

	/**
	 * Save health data for a provider slot.
	 *
	 * @param string $slot 'primary' or 'backup'.
	 * @param array  $data Health data.
	 */
	private function save_health_data( string $slot, array $data ): void {
		update_option( "swpm_connection_health_{$slot}", $data, false );
	}

	/* ------------------------------------------------------------------
	 * Health Check (manual ping via test email)
	 * ----------------------------------------------------------------*/

	/**
	 * AJAX: Run health check on primary and/or backup providers.
	 */
	public function ajax_health_check(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		$slot = isset( $_POST['slot'] ) ? sanitize_key( $_POST['slot'] ) : 'primary';

		if ( ! in_array( $slot, array( 'primary', 'backup' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid slot.', 'swpmail' ) ) );
		}

		$provider = 'primary' === $slot ? $this->primary : $this->backup;

		if ( ! $provider ) {
			wp_send_json_error( array( 'message' => __( 'No provider configured for this slot.', 'swpmail' ) ) );
		}

		$result = $provider->test_connection();

		// Update health data.
		$health = $this->get_health_data( $slot );
		$health['last_check_at'] = time();
		$health['last_check_ok'] = $result->is_success();

		if ( $result->is_success() ) {
			$health['consecutive_failures'] = 0;
			$health['unhealthy_since']      = 0;
			$health['last_success_at']      = time();
		} else {
			$health['consecutive_failures']++;
			$health['last_failure_at'] = time();
		}

		$this->save_health_data( $slot, $health );

		if ( $result->is_success() ) {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %s: provider name */
					__( '%s is working correctly.', 'swpmail' ),
					$provider->get_label()
				),
				'slot'   => $slot,
				'status' => 'healthy',
			) );
		} else {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
				'slot'    => $slot,
				'status'  => 'error',
			) );
		}
	}

	/**
	 * AJAX: Get current connection status for both slots.
	 */
	public function ajax_get_status(): void {
		check_ajax_referer( 'swpm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'swpmail' ) ), 403 );
		}

		wp_send_json_success( $this->get_status_summary() );
	}

	/**
	 * Get full connection status summary for dashboard/UI.
	 *
	 * @return array
	 */
	public function get_status_summary(): array {
		$primary_health = $this->get_health_data( 'primary' );
		$backup_key     = get_option( 'swpm_backup_provider', '' );

		$summary = array(
			'primary' => array(
				'key'          => $this->primary->get_key(),
				'label'        => $this->primary->get_label(),
				'healthy'      => $this->is_provider_healthy( 'primary' ),
				'failures'     => $primary_health['consecutive_failures'],
				'last_success' => $primary_health['last_success_at'],
				'last_failure' => $primary_health['last_failure_at'],
				'last_check'   => $primary_health['last_check_at'],
				'last_check_ok' => $primary_health['last_check_ok'],
			),
			'backup'  => null,
			'failover_enabled' => $this->is_failover_enabled(),
		);

		if ( $this->backup ) {
			$backup_health = $this->get_health_data( 'backup' );
			$summary['backup'] = array(
				'key'          => $this->backup->get_key(),
				'label'        => $this->backup->get_label(),
				'healthy'      => $this->is_provider_healthy( 'backup' ),
				'failures'     => $backup_health['consecutive_failures'],
				'last_success' => $backup_health['last_success_at'],
				'last_failure' => $backup_health['last_failure_at'],
				'last_check'   => $backup_health['last_check_at'],
				'last_check_ok' => $backup_health['last_check_ok'],
			);
		}

		return $summary;
	}

	/* ------------------------------------------------------------------
	 * Backup Provider Management
	 * ----------------------------------------------------------------*/

	/**
	 * Whether failover is enabled and a backup provider is configured.
	 *
	 * @return bool
	 */
	public function is_failover_enabled(): bool {
		$backup_key = get_option( 'swpm_backup_provider', '' );
		return ! empty( $backup_key ) && 'none' !== $backup_key;
	}

	/**
	 * Get the primary provider instance.
	 *
	 * @return SWPM_Provider_Interface
	 */
	public function get_primary(): SWPM_Provider_Interface {
		return $this->primary;
	}

	/**
	 * Get the backup provider instance.
	 *
	 * @return SWPM_Provider_Interface|null
	 */
	public function get_backup(): ?SWPM_Provider_Interface {
		return $this->backup;
	}

	/**
	 * Create the backup provider from stored option.
	 *
	 * @return SWPM_Provider_Interface|null
	 */
	private function create_backup_provider(): ?SWPM_Provider_Interface {
		$backup_key  = sanitize_key( get_option( 'swpm_backup_provider', '' ) );
		$primary_key = $this->primary->get_key();

		if ( empty( $backup_key ) || 'none' === $backup_key || $backup_key === $primary_key ) {
			return null;
		}

		$registry = apply_filters( 'swpm_provider_registry', $this->factory->get_all() );

		if ( ! isset( $registry[ $backup_key ] ) ) {
			swpm_log( 'warning', "Unknown backup provider '{$backup_key}'." );
			return null;
		}

		$class = $registry[ $backup_key ];

		if ( ! class_exists( $class ) ) {
			swpm_log( 'error', "Backup provider class '{$class}' not found." );
			return null;
		}

		return new $class();
	}
}
