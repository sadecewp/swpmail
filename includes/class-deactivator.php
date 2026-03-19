<?php
/**
 * Plugin deactivator.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SWPM_Deactivator {

	/**
	 * Run on deactivation.
	 */
	public static function deactivate(): void {
		// Clear scheduled cron hooks.
		wp_clear_scheduled_hook( 'swpm_process_queue' );
		wp_clear_scheduled_hook( 'swpm_send_daily_digest' );
		wp_clear_scheduled_hook( 'swpm_send_weekly_digest' );
		wp_clear_scheduled_hook( 'swpm_cleanup_logs' );
		wp_clear_scheduled_hook( 'swpm_cleanup_queue' );
	}
}
