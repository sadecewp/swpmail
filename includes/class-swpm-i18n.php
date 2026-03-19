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

// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid
/**
 * Internationalization handler.
 */
class SWPM_i18n {
// phpcs:enable PEAR.NamingConventions.ValidClassName.Invalid

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
