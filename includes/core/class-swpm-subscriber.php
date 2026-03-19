<?php // phpcs:disable Internal.Exception
/**
 * Subscriber management.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SWPM_Subscriber.
 */
class SWPM_Subscriber {

	/**
	 * Database instance.
	 *
	 * @var \wpdb
	 */
	private $db;

	/**
	 * Subscribers table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'swpm_subscribers';
	}

	/**
	 * Create a new subscriber.
	 *
	 * @param string $email     Email address.
	 * @param string $name      Subscriber name.
	 * @param string $frequency Frequency: instant, daily, weekly.
	 * @return int|\WP_Error Subscriber ID or error.
	 */
	public function create( string $email, string $name = '', string $frequency = 'instant' ) {
		$email     = sanitize_email( $email );
		$name      = sanitize_text_field( $name );
		$frequency = in_array( $frequency, array( 'instant', 'daily', 'weekly' ), true )
					? $frequency : 'instant';

		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Invalid email address.', 'swpmail' ) );
		}

		// Disposable email check.
		$blocked = apply_filters(
			'swpm_blocked_email_domains',
			array(
				'mailinator.com',
				'tempmail.com',
				'guerrillamail.com',
				'10minutemail.com',
				'throwaway.email',
				'yopmail.com',
				'trashmail.com',
				'sharklasers.com',
				'guerrillamailblock.com',
				'grr.la',
				'dispostable.com',
				'mailnesia.com',
				'maildrop.cc',
				'temp-mail.org',
				'fakeinbox.com',
				'getnada.com',
				'tmpmail.net',
				'burnermail.io',
			)
		);
		$domain  = strtolower( substr( $email, strrpos( $email, '@' ) + 1 ) );
		// Check both the exact domain and parent domain to prevent subdomain bypass.
		$parts         = explode( '.', $domain );
		$parent_domain = count( $parts ) > 2 ? implode( '.', array_slice( $parts, -2 ) ) : $domain;
		if ( in_array( $domain, $blocked, true ) || in_array( $parent_domain, $blocked, true ) ) {
			// Generic message to avoid revealing which domains are blocked.
			return new \WP_Error( 'subscribe_failed', __( 'Subscription could not be completed. Please try a different email address.', 'swpmail' ) );
		}

		$existing = $this->get_by_email( $email );

		if ( $existing ) {
			if ( 'unsubscribed' === $existing->status ) {
				return $this->resubscribe( (int) $existing->id, $frequency );
			}
			// Generic message to prevent email enumeration.
			return new \WP_Error( 'subscribe_failed', __( 'Subscription could not be completed. Please try a different email address.', 'swpmail' ) );
		}

		$now    = current_time( 'mysql' );
		$status = get_option( 'swpm_double_opt_in', true ) ? 'pending' : 'confirmed';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $this->db->insert(
			$this->table,
			array(
				'email'      => $email,
				'name'       => $name,
				'status'     => $status,
				'frequency'  => $frequency,
				'token'      => $this->generate_token(),
				'ip_address' => $this->get_client_ip(),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Could not save subscriber.', 'swpmail' ) );
		}

		$id = $this->db->insert_id;

		/**
		 * Fires after a subscriber is created.
		 *
		 * @since 1.0.0
		 * @param int    $id        Subscriber ID.
		 * @param string $email     Email.
		 * @param string $frequency Frequency.
		 */
		do_action( 'swpm_subscriber_created', $id, $email, $frequency );

		return $id;
	}

	/**
	 * Confirm a pending subscriber.
	 *
	 * @param string $token Confirmation token.
	 * @return bool
	 */
	public function confirm( string $token ): bool {
		$token  = sanitize_text_field( $token );
		$expiry = (int) apply_filters( 'swpm_confirm_token_expiry', 48 * HOUR_IN_SECONDS );
		$now    = current_time( 'mysql' );

		// Atomic update: prevents TOCTOU race by combining SELECT conditions into UPDATE WHERE.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table}
				 SET status = 'confirmed', confirmed_at = %s, updated_at = %s
				 WHERE token = %s
				   AND status = 'pending'
				   AND updated_at > %s",
				$now,
				$now,
				$token,
				gmdate( 'Y-m-d H:i:s', time() - $expiry )
			)
		);

		if ( $updated ) {
			$subscriber = $this->get_by_token( $token );
			if ( $subscriber ) {
				do_action( 'swpm_subscriber_confirmed', (int) $subscriber->id, $subscriber->email );
			}
		}
		return (bool) $updated;
	}

	/**
	 * Unsubscribe by token.
	 *
	 * @param string $token Subscriber token.
	 * @return bool
	 */
	public function unsubscribe( string $token ): bool {
		$token      = sanitize_text_field( $token );
		$subscriber = $this->get_by_token( $token );
		if ( ! $subscriber ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $this->db->update(
			$this->table,
			array(
				'status'     => 'unsubscribed',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $subscriber->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		if ( $updated ) {
			do_action( 'swpm_subscriber_unsubscribed', (int) $subscriber->id, $subscriber->email );
		}
		return (bool) $updated;
	}

	/**
	 * Resubscribe a previously unsubscribed user.
	 *
	 * @param int    $id        Subscriber ID.
	 * @param string $frequency New frequency.
	 * @return int|\WP_Error
	 */
	private function resubscribe( int $id, string $frequency ) {
		$now    = current_time( 'mysql' );
		$status = get_option( 'swpm_double_opt_in', true ) ? 'pending' : 'confirmed';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $this->db->update(
			$this->table,
			array(
				'status'     => $status,
				'frequency'  => $frequency,
				'token'      => $this->generate_token(),
				'updated_at' => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return $updated ? $id : new \WP_Error( 'db_error', __( 'Could not resubscribe.', 'swpmail' ) );
	}

	/**
	 * Get subscriber by email.
	 *
	 * @param string $email Email address.
	 * @return object|null
	 */
	public function get_by_email( string $email ): ?object {
		$email = sanitize_email( $email );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE email = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$email
			)
		);
	}

	/**
	 * Get subscriber by token.
	 *
	 * @param string $token Token string.
	 * @return object|null
	 */
	public function get_by_token( string $token ): ?object {
		$token = sanitize_text_field( $token );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE token = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$token
			)
		);
	}

	/**
	 * Get confirmed subscribers by frequency.
	 *
	 * @param string $frequency Frequency type.
	 * @param int    $limit     Max results.
	 * @param int    $offset    Offset.
	 * @return array
	 */
	public function get_confirmed_by_frequency( string $frequency, int $limit = 100, int $offset = 0 ): array {
		$frequency = sanitize_key( $frequency );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE status = 'confirmed' AND frequency = %s ORDER BY id ASC LIMIT %d OFFSET %d",
				$frequency,
				$limit,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $results ? $results : array();
	}

	/**
	 * Get subscriber by ID.
	 *
	 * @param int $id Subscriber ID.
	 * @return object|null
	 */
	public function get_by_id( int $id ): ?object {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);
	}

	/**
	 * Delete a subscriber.
	 *
	 * @param int $id Subscriber ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $this->db->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Count subscribers by status.
	 *
	 * @param string $status Status filter (empty for all).
	 * @return int
	 */
	public function count( string $status = '' ): int {
		if ( ! empty( $status ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $this->db->get_var(
				$this->db->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					sanitize_key( $status )
				)
			);
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/**
	 * Register GDPR data exporter.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['swpmail'] = array(
			'exporter_friendly_name' => __( 'SWPMail Subscriber', 'swpmail' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Register GDPR data eraser.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['swpmail'] = array(
			'eraser_friendly_name' => __( 'SWPMail Subscriber', 'swpmail' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * GDPR: Export personal data.
	 *
	 * @param string $email_address Email.
	 * @param int    $page          Page number.
	 * @return array
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$subscriber = $this->get_by_email( $email_address );
		$data       = array();

		if ( $subscriber ) {
			$data[] = array(
				'group_id'    => 'swpmail',
				'group_label' => __( 'SWPMail Subscriber Data', 'swpmail' ),
				'item_id'     => "swpmail-{$subscriber->id}",
				'data'        => array(
					array(
						'name'  => __( 'Email', 'swpmail' ),
						'value' => $subscriber->email,
					),
					array(
						'name'  => __( 'Name', 'swpmail' ),
						'value' => $subscriber->name,
					),
					array(
						'name'  => __( 'Status', 'swpmail' ),
						'value' => $subscriber->status,
					),
					array(
						'name'  => __( 'Frequency', 'swpmail' ),
						'value' => $subscriber->frequency,
					),
					array(
						'name'  => __( 'IP Address', 'swpmail' ),
						'value' => $subscriber->ip_address,
					),
					array(
						'name'  => __( 'Subscribed On', 'swpmail' ),
						'value' => $subscriber->created_at,
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * GDPR: Erase personal data.
	 *
	 * @param string $email_address Email.
	 * @param int    $page          Page number.
	 * @return array
	 */
	public function erase_personal_data( string $email_address, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$subscriber = $this->get_by_email( $email_address );
		$removed    = false;

		if ( $subscriber ) {
			$removed = $this->delete( (int) $subscriber->id );
		}

		return array(
			'items_removed'  => $removed ? 1 : 0,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Generate a secure random token.
	 *
	 * @return string
	 */
	private function generate_token(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Regenerate and save a new token for a subscriber.
	 *
	 * @param int $id Subscriber ID.
	 */
	private function regenerate_token( int $id ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->db->update(
			$this->table,
			array(
				'token'      => $this->generate_token(),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		return SWPM_Ajax_Handler::get_client_ip();
	}
}
