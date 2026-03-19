<?php
/**
 * REST API endpoints.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_REST_API {

	/** @var SWPM_Subscriber */
	private SWPM_Subscriber $subscriber;

	/** @var SWPM_Mailer */
	private SWPM_Mailer $mailer;

	public function __construct( SWPM_Subscriber $subscriber, SWPM_Mailer $mailer ) {
		$this->subscriber = $subscriber;
		$this->mailer     = $mailer;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		$ns = 'swpmail/v1';

		register_rest_route( $ns, '/subscribe', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'subscribe' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'email'     => array(
					'required'          => true,
					'type'              => 'string',
					'format'            => 'email',
					'sanitize_callback' => 'sanitize_email',
				),
				'name'      => array(
					'required'          => false,
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'frequency' => array(
					'required' => false,
					'type'     => 'string',
					'default'  => 'instant',
					'enum'     => array( 'instant', 'daily', 'weekly' ),
				),
				'swpm_website' => array(
					'required' => false,
					'type'     => 'string',
					'default'  => '',
				),
				'gdpr'      => array(
					'required' => false,
					'type'     => 'boolean',
					'default'  => false,
				),
			),
		) );

		register_rest_route( $ns, '/subscribers', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_subscribers' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'status'   => array(
					'required' => false,
					'type'     => 'string',
					'enum'     => array( 'pending', 'confirmed', 'unsubscribed', 'bounced' ),
				),
				'per_page' => array(
					'required' => false,
					'type'     => 'integer',
					'default'  => 20,
					'minimum'  => 1,
					'maximum'  => 100,
				),
				'page'     => array(
					'required' => false,
					'type'     => 'integer',
					'default'  => 1,
					'minimum'  => 1,
				),
			),
		) );

		register_rest_route( $ns, '/subscribers/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_subscriber' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );
	}

	/**
	 * POST /subscribe
	 */
	public function subscribe( WP_REST_Request $request ): WP_REST_Response {
		// 0. Origin / Referer validation for CSRF protection.
		$origin  = $request->get_header( 'origin' );
		$referer = $request->get_header( 'referer' );
		$home    = wp_parse_url( home_url(), PHP_URL_HOST );

		$origin_host  = $origin ? wp_parse_url( $origin, PHP_URL_HOST ) : null;
		$referer_host = $referer ? wp_parse_url( $referer, PHP_URL_HOST ) : null;

		if ( ! $origin_host && ! $referer_host ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Missing origin header.', 'swpmail' ) ),
				403
			);
		}

		if ( ( $origin_host && $origin_host !== $home ) || ( ! $origin_host && $referer_host !== $home ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Cross-origin requests are not allowed.', 'swpmail' ) ),
				403
			);
		}

		// 1. Rate limit.
		$ip       = SWPM_Ajax_Handler::get_client_ip();
		$rate_key = 'swpm_rate_' . md5( $ip );
		$attempts = (int) get_transient( $rate_key );

		if ( $attempts >= 5 ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Too many requests. Please try again later.', 'swpmail' ) ),
				429
			);
		}
		set_transient( $rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );

		// 2. Honeypot.
		if ( ! empty( $request->get_param( 'swpm_website' ) ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Please check your email to confirm your subscription.', 'swpmail' ) ),
				201
			);
		}

		// 3. GDPR consent.
		if ( get_option( 'swpm_gdpr_checkbox' ) && empty( $request->get_param( 'gdpr' ) ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Please accept the privacy policy.', 'swpmail' ) ),
				400
			);
		}

		$email     = $request->get_param( 'email' );
		$name      = $request->get_param( 'name' );
		$frequency = $request->get_param( 'frequency' );

		$result = $this->subscriber->create( $email, $name, $frequency );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'message' => $result->get_error_message() ),
				422
			);
		}

		// 4. Send confirmation email (double opt-in).
		if ( get_option( 'swpm_double_opt_in', true ) ) {
			$sub = $this->subscriber->get_by_email( $email );

			/** @var SWPM_Template_Engine $engine */
			$engine = swpm( 'template_engine' );
			$body   = $engine->render( 'confirm-subscription', array(
				'subscriber_name' => $name ?: $email,
				'confirm_url'     => add_query_arg(
					array(
						'swpm_action' => 'confirm',
						'token'       => rawurlencode( $sub->token ),
					),
					home_url()
				),
			) );

			add_filter( 'swpm_skip_override', '__return_true' );
			try {
				wp_mail(
					$email,
					sprintf(
						/* translators: %s: site name */
						__( 'Confirm your subscription to %s', 'swpmail' ),
						get_bloginfo( 'name' )
					),
					$body,
					array( 'Content-Type: text/html; charset=UTF-8' )
				);
			} finally {
				remove_filter( 'swpm_skip_override', '__return_true' );
			}
		}

		return new WP_REST_Response(
			array(
				'message' => get_option( 'swpm_double_opt_in', true )
					? __( 'Please check your email to confirm your subscription.', 'swpmail' )
					: __( 'You have successfully subscribed!', 'swpmail' ),
				'id'      => $result,
			),
			201
		);
	}

	/**
	 * GET /subscribers
	 */
	public function get_subscribers( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$table    = $wpdb->prefix . 'swpm_subscribers';
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );
		$offset   = ( $page - 1 ) * $per_page;
		$status   = $request->get_param( 'status' );

		$where  = '';
		$params = array();

		if ( $status ) {
			$where    = 'WHERE status = %s';
			$params[] = $status;
		}

		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, email, name, status, frequency, created_at FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
				$params
			)
		);

		// Total count.
		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		$response = new WP_REST_Response( $results, 200 );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * DELETE /subscribers/{id}
	 */
	public function delete_subscriber( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$id    = absint( $request->get_param( 'id' ) );
		$table = $wpdb->prefix . 'swpm_subscribers';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		if ( ! $deleted ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Subscriber not found.', 'swpmail' ) ),
				404
			);
		}

		// Audit log for compliance (GDPR/KVKK).
		swpm_log( 'info', sprintf( 'Subscriber #%d deleted via REST API by user #%d', $id, get_current_user_id() ) );

		return new WP_REST_Response(
			array( 'message' => __( 'Subscriber deleted.', 'swpmail' ) ),
			200
		);
	}
}
