<?php
/**
 * Mail queue management.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the email queue and batch processing.
 */
class SWPM_Queue {

	/**

	 * Variable.
	 *
	 * @var \wpdb
	 */
	private $db;

	/**

	 * Variable.
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
		$this->table = $wpdb->prefix . 'swpm_queue';
	}

	/**
	 * Add a single item to the queue.
	 *
	 * @param string      $to_email      Recipient email.
	 * @param string      $subject       Subject line.
	 * @param string      $body          Email body (HTML).
	 * @param string|null $template_id   Template identifier.
	 * @param int|null    $subscriber_id Subscriber ID.
	 * @param array       $headers       Additional headers.
	 * @param array       $attachments   File paths.
	 * @param string      $scheduled_at  When to send (MySQL datetime).
	 * @return int|false Inserted ID or false.
	 */
	public function enqueue(
		string $to_email,
		string $subject,
		string $body,
		?string $template_id = null,
		?int $subscriber_id = null,
		array $headers = array(),
		array $attachments = array(),
		string $scheduled_at = ''
	) {
		if ( empty( $scheduled_at ) ) {
			$scheduled_at = current_time( 'mysql' );
		}

		$data = array(
			'to_email'     => sanitize_email( $to_email ),
			'subject'      => sanitize_text_field( $subject ),
			'body'         => $body,
			'status'       => 'pending',
			'scheduled_at' => $scheduled_at,
			'created_at'   => current_time( 'mysql' ),
		);

		$format = array( '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( null !== $subscriber_id ) {
			$data['subscriber_id'] = $subscriber_id;
			$format[]              = '%d';
		}
		if ( null !== $template_id ) {
			$data['template_id'] = sanitize_key( $template_id );
			$format[]            = '%s';
		}
		if ( ! empty( $headers ) ) {
			$data['headers'] = wp_json_encode( $headers );
			$format[]        = '%s';
		}
		if ( ! empty( $attachments ) ) {
			$data['attachments'] = wp_json_encode( $attachments );
			$format[]            = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $this->db->insert( $this->table, $data, $format );

		return $result ? $this->db->insert_id : false;
	}

	/**
	 * Enqueue bulk emails for multiple recipients.
	 *
	 * Uses batch INSERT for better performance with large recipient lists.
	 *
	 * @param array  $recipients  Array of subscriber objects with email, name, token.
	 * @param string $template_id Template ID.
	 * @param string $subject     Subject line.
	 * @param array  $data        Template variables.
	 */
	public function enqueue_bulk( array $recipients, string $template_id, string $subject, array $data ): void {
		/**
		 * Engine.
		 *
		 * @var SWPM_Template_Engine
		 */
		$engine     = swpm( 'template_engine' );
		$now        = current_time( 'mysql' );
		$safe_tpl   = sanitize_key( $template_id );
		$safe_subj  = sanitize_text_field( $subject );
		$batch_size = (int) apply_filters( 'swpm_bulk_enqueue_batch_size', 50 );
		$rows       = array();

		foreach ( $recipients as $recipient ) {
			$email  = is_object( $recipient ) ? $recipient->email : $recipient;
			$name   = is_object( $recipient ) ? ( $recipient->name ?? '' ) : '';
			$token  = is_object( $recipient ) ? ( $recipient->token ?? '' ) : '';
			$sub_id = is_object( $recipient ) && isset( $recipient->id ) ? (int) $recipient->id : null;

			$vars = array_merge(
				$data,
				array(
					'subscriber_name'  => $name ? $name : $email,
					'subscriber_email' => $email,
					'unsubscribe_url'  => ! empty( $token )
						? add_query_arg(
							array(
								'swpm_action' => 'unsubscribe',
								'token'       => rawurlencode( $token ),
								'sig'         => rawurlencode( hash_hmac( 'sha256', $token, wp_salt( 'nonce' ) ) ),
							),
							home_url()
						)
						: '',
				)
			);

			$body = $engine->render( $template_id, $vars );

			$rows[] = $this->db->prepare(
				'(%s, %s, %s, %s, %d, %s, %s, %s)',
				sanitize_email( $email ),
				$safe_subj,
				$body,
				$safe_tpl,
				$sub_id ?? 0,
				'pending',
				$now,
				$now
			);

			if ( count( $rows ) >= $batch_size ) {
				$this->insert_bulk_rows( $rows );
				$rows = array();
			}
		}

		if ( ! empty( $rows ) ) {
			$this->insert_bulk_rows( $rows );
		}
	}

	/**
	 * Execute a batch INSERT for pre-prepared row values.
	 *
	 * @param array $rows Array of prepared value strings.
	 */
	private function insert_bulk_rows( array $rows ): void {
		$values = implode( ', ', $rows );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->db->query(
			"INSERT INTO {$this->table} (to_email, subject, body, template_id, subscriber_id, status, scheduled_at, created_at) VALUES {$values}"
		);
	}

	/**
	 * Process the queue: send pending emails.
	 *
	 * @param int $batch_size Number of emails per batch.
	 */
	public function process( int $batch_size = 50 ): void {
		// Prevent concurrent execution with database-level atomic lock.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$locked = $this->db->get_var( "SELECT GET_LOCK('swpm_queue_lock', 0)" );
		if ( '1' !== $locked ) {
			return;
		}

		// Reset stuck items.
		$this->reset_stuck_items();

		// Mark exceeded as failed.
		$this->mark_exceeded_as_failed();

		$now = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table}
				 WHERE status = 'pending' AND scheduled_at <= %s
				 ORDER BY scheduled_at ASC LIMIT %d",
				$now,
				$batch_size
			)
		);

		if ( empty( $items ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->db->query( "SELECT RELEASE_LOCK('swpm_queue_lock')" );
			return;
		}

		/**

		 * Provider.
		 *
		 * @var SWPM_Provider_Interface
		 */
		$provider = swpm( 'provider' );
		if ( ! $provider ) {
			swpm_log( 'error', 'No provider available for queue processing.' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->db->query( "SELECT RELEASE_LOCK('swpm_queue_lock')" );
			return;
		}

		foreach ( $items as $item ) {
			// Validate email before attempting send.
			if ( ! is_email( $item->to_email ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->db->update(
					$this->table,
					array(
						'status'        => 'failed',
						'error_message' => 'Invalid email address.',
						'body'          => '',
					),
					array( 'id' => $item->id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
				continue;
			}

			// Mark as sending.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->db->update(
				$this->table,
				array(
					'status'   => 'sending',
					'attempts' => $item->attempts + 1,
				),
				array( 'id' => $item->id ),
				array( '%s', '%d' ),
				array( '%d' )
			);

			$headers     = ! empty( $item->headers ) ? json_decode( $item->headers, true ) : array();
			$attachments = ! empty( $item->attachments ) ? json_decode( $item->attachments, true ) : array();

			$headers = (array) apply_filters(
				'swpm_mail_headers',
				array_merge(
					array( 'Content-Type: text/html; charset=UTF-8' ),
					$headers ? $headers : array()
				),
				$item
			);

			// Inject tracking pixel and link rewrites.
			$tracked_body = $item->body;
			$tracker      = swpm( 'tracker' );
			if ( $tracker instanceof SWPM_Tracker ) {
				$tracked_body = $tracker->inject_tracking( $tracked_body, $item->to_email, $item->subject, (int) $item->id );
			}

			// Smart routing: resolve per-email provider.
			$send_provider = $provider;
			$router        = swpm( 'router' );
			if ( $router instanceof SWPM_Router ) {
				$routed = $router->resolve(
					array(
						'to'      => $item->to_email,
						'subject' => $item->subject,
						'from'    => get_option( 'swpm_from_email', '' ),
						'headers' => $headers,
						'source'  => 'queue',
					)
				);
				if ( $routed ) {
					$send_provider = $routed;
				}
			}

			// Skip override to avoid recursion.
			add_filter( 'swpm_skip_override', '__return_true' );
			try {
				$result = $send_provider->send(
					$item->to_email,
					$item->subject,
					$tracked_body,
					$headers,
					$attachments ? $attachments : array()
				);
			} finally {
				remove_filter( 'swpm_skip_override', '__return_true' );
			}

			if ( $result->is_success() ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->db->update(
					$this->table,
					array(
						'status'          => 'sent',
						'sent_at'         => current_time( 'mysql' ),
						'body'            => '', // Clear body after send to avoid leaking sensitive data.
						'provider_used'   => get_option( 'swpm_mail_provider' ),
						'provider_msg_id' => $result->get_message_id(),
					),
					array( 'id' => $item->id ),
					array( '%s', '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
			} else {
				$new_status = ( $item->attempts + 1 >= $item->max_attempts ) ? 'failed' : 'pending';

				$update_data   = array(
					'status'        => $new_status,
					'error_message' => $result->get_error_message(),
					'error_code'    => $result->get_error_code(),
					'provider_used' => get_option( 'swpm_mail_provider' ),
				);
				$update_format = array( '%s', '%s', '%s', '%s' );

				// Clear body on final failure to prevent sensitive data retention.
				if ( 'failed' === $new_status ) {
					$update_data['body'] = '';
					$update_format[]     = '%s';
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->db->update(
					$this->table,
					$update_data,
					array( 'id' => $item->id ),
					$update_format,
					array( '%d' )
				);
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->db->query( "SELECT RELEASE_LOCK('swpm_queue_lock')" );
	}

	/**
	 * Reschedule an item with delay.
	 *
	 * @param int $queue_id Queue item ID.
	 * @param int $delay    Delay in seconds.
	 */
	public function reschedule_with_delay( int $queue_id, int $delay ): void {
		$new_time = gmdate( 'Y-m-d H:i:s', time() + $delay );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->db->update(
			$this->table,
			array(
				'scheduled_at' => $new_time,
				'status'       => 'pending',
			),
			array( 'id' => $queue_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Reset stuck 'sending' items older than 10 minutes.
	 */
	private function reset_stuck_items(): void {
		$threshold = gmdate( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET status = 'pending'
				 WHERE status = 'sending' AND scheduled_at < %s",
				$threshold
			)
		);
	}

	/**
	 * Mark items that exceeded max_attempts as failed.
	 */
	private function mark_exceeded_as_failed(): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET status = 'failed', error_message = %s
				 WHERE status = 'pending' AND attempts >= max_attempts",
				'Maximum retry attempts exceeded.'
			)
		);
	}

	/**
	 * Get queue stats.
	 *
	 * @return array
	 */
	public function get_stats(): array {
		// Table name is safe (wpdb prefix + constant). No user input, so prepare() is not needed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $this->db->get_results(
			"SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status"
		);
		$stats   = array(
			'pending' => 0,
			'sending' => 0,
			'sent'    => 0,
			'failed'  => 0,
		);
		foreach ( $results as $row ) {
			$stats[ $row->status ] = (int) $row->count;
		}
		return $stats;
	}
}
