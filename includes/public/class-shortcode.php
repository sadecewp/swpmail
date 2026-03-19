<?php
/**
 * Subscribe shortcode.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Shortcode {

	/** @var SWPM_Subscriber */
	private SWPM_Subscriber $subscriber;

	public function __construct( SWPM_Subscriber $subscriber ) {
		$this->subscriber = $subscriber;
	}

	/**
	 * Register shortcode.
	 */
	public function register(): void {
		add_shortcode( 'swpmail_subscribe', array( $this, 'render' ) );
	}

	/**
	 * Render subscription form.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ): string {
		wp_enqueue_style( 'swpmail-public' );
		wp_enqueue_script( 'swpmail-public' );

		$atts = shortcode_atts(
			array(
				'title'             => get_option( 'swpm_form_title', __( 'Subscribe to Newsletter', 'swpmail' ) ),
				'show_name'         => 'false',
				'frequency'         => get_option( 'swpm_show_frequency_choice', true ) ? 'true' : 'false',
				'frequency_default' => 'instant',
				'style'             => 'default',
				'button_text'       => __( 'Subscribe', 'swpmail' ),
			),
			$atts,
			'swpmail_subscribe'
		);

		ob_start();

		$template = SWPM_PLUGIN_DIR . 'public/partials/subscribe-form.php';

		/**
		 * Filter subscribe form template path.
		 *
		 * @since 1.0.0
		 * @param string $template Template file path.
		 * @param array  $atts     Shortcode attributes.
		 */
		$template = apply_filters( 'swpm_subscribe_template', $template, $atts );

		if ( file_exists( $template ) ) {
			$show_name         = filter_var( $atts['show_name'], FILTER_VALIDATE_BOOLEAN );
			$show_frequency    = filter_var( $atts['frequency'], FILTER_VALIDATE_BOOLEAN );
			$frequency_default = sanitize_key( $atts['frequency_default'] );
			$style             = sanitize_key( $atts['style'] );
			$title             = sanitize_text_field( $atts['title'] );
			$button_text       = sanitize_text_field( $atts['button_text'] );
			$nonce             = wp_create_nonce( 'swpm_subscribe_nonce' );

			include $template;
		}

		return ob_get_clean();
	}
}
