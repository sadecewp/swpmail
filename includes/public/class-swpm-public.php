<?php
/**
 * Public-facing functionality.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public-facing functionality for SWPMail.
 */
class SWPM_Public {

	/**
	 * Loader instance.
	 *
	 * @var SWPM_Loader
	 */
	private SWPM_Loader $loader;

	/**
	 * Constructor.
	 *
	 * @param SWPM_Loader $loader Loader instance.
	 */
	public function __construct( SWPM_Loader $loader ) {
		$this->loader = $loader;
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'register_assets' );
	}

	/**
	 * Register public assets — enqueued later by the shortcode when actually rendered.
	 */
	public function register_assets(): void {
		wp_register_style(
			'swpmail-public',
			SWPM_PLUGIN_URL . 'public/css/swpmail-public.css',
			array(),
			SWPM_VERSION
		);

		wp_register_script(
			'swpmail-public',
			SWPM_PLUGIN_URL . 'public/js/swpmail-public.js',
			array( 'jquery' ),
			SWPM_VERSION,
			true
		);

		wp_localize_script(
			'swpmail-public',
			'swpmPublic',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'subscribing' => __( 'Subscribing...', 'swpmail' ),
					'error'       => __( 'An error occurred. Please try again.', 'swpmail' ),
				),
			)
		);
	}
}
