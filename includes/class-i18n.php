<?php
/**
 * Internationalization handler.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_i18n {

	/**
	 * Load plugin text domain.
	 */
	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			'swpmail',
			false,
			dirname( SWPM_PLUGIN_BASE ) . '/languages/'
		);
	}
}
