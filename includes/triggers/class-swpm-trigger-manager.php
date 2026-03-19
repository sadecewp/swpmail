<?php
/**
 * Trigger manager — registers and manages all triggers.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages and dispatches email triggers.
 */
class SWPM_Trigger_Manager {

	/**
	 * Registered triggers.
	 *
	 * @var array<string, SWPM_Trigger_Base>
	 */
	private array $triggers = array();

	/**
	 * Built-in trigger keys.
	 *
	 * @var array<string>
	 */
	private array $builtin_keys = array();

	/**
	 * Initialize built-in triggers, DB-stored custom triggers, and allow code-based ones.
	 */
	public function init(): void {
		// Register built-in triggers.
		$this->register( new SWPM_Trigger_New_Post() );
		$this->register( new SWPM_Trigger_New_User() );
		$this->register( new SWPM_Trigger_User_Login() );
		$this->register( new SWPM_Trigger_New_Comment() );
		$this->register( new SWPM_Trigger_Password_Reset() );

		$this->builtin_keys = array_keys( $this->triggers );

		// Load custom triggers from database.
		$custom_triggers = (array) get_option( 'swpm_custom_triggers', array() );
		foreach ( $custom_triggers as $config ) {
			if ( ! is_array( $config ) || empty( $config['key'] ) || empty( $config['hook'] ) || empty( $config['template_id'] ) ) {
				continue;
			}
			$this->register( new SWPM_Trigger_Custom( $config ) );
		}

		/**
		 * Allow themes/plugins to register custom triggers via code.
		 *
		 * @since 1.0.0
		 * @param SWPM_Trigger_Manager $manager The trigger manager.
		 */
		do_action( 'swpm_register_triggers', $this );

		// Hook all triggers into WordPress.
		foreach ( $this->triggers as $trigger ) {
			$trigger->register();
		}
	}

	/**
	 * Register a trigger.
	 *
	 * @param SWPM_Trigger_Base $trigger Trigger instance.
	 */
	public function register( SWPM_Trigger_Base $trigger ): void {
		$this->triggers[ $trigger->get_key() ] = $trigger;
	}

	/**
	 * Check if a trigger is a built-in (non-deletable) trigger.
	 *
	 * @param string $key Trigger key.
	 * @return bool
	 */
	public function is_builtin( string $key ): bool {
		return in_array( $key, $this->builtin_keys, true );
	}

	/**
	 * Get all registered triggers.
	 *
	 * @return array<string, SWPM_Trigger_Base>
	 */
	public function get_all(): array {
		return $this->triggers;
	}

	/**
	 * Get trigger by key.
	 *
	 * @param string $key Trigger key.
	 * @return SWPM_Trigger_Base|null
	 */
	public function get( string $key ): ?SWPM_Trigger_Base {
		return $this->triggers[ $key ] ?? null;
	}

	/**
	 * Get custom triggers from database.
	 *
	 * @return array
	 */
	public static function get_custom_triggers(): array {
		return (array) get_option( 'swpm_custom_triggers', array() );
	}

	/**
	 * Save a new custom trigger to database.
	 *
	 * @param array $config Trigger configuration.
	 */
	public static function save_custom_trigger( array $config ): void {
		$triggers   = self::get_custom_triggers();
		$triggers[] = $config;
		update_option( 'swpm_custom_triggers', $triggers );
	}

	/**
	 * Delete a custom trigger from database.
	 *
	 * @param string $key Trigger key to remove.
	 */
	public static function delete_custom_trigger( string $key ): void {
		$triggers = self::get_custom_triggers();
		$triggers = array_values(
			array_filter(
				$triggers,
				function ( $t ) use ( $key ) {
					return ( $t['key'] ?? '' ) !== $key;
				}
			)
		);
		update_option( 'swpm_custom_triggers', $triggers );

		// Also remove from active triggers.
		$active = (array) get_option( 'swpm_active_triggers', array() );
		$active = array_values(
			array_filter(
				$active,
				function ( $k ) use ( $key ) {
					return $k !== $key;
				}
			)
		);
		update_option( 'swpm_active_triggers', $active );
	}
}
