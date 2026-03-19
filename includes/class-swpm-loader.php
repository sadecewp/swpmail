<?php
/**
 * Hook loader class.
 *
 * @package SWPMail
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers all actions and filters for the plugin.
 */
class SWPM_Loader {

	/**
	 * Registered actions.
	 *
	 * @var array<int, array>
	 */
	private array $actions = array();

	/**
	 * Registered filters.
	 *
	 * @var array<int, array>
	 */
	private array $filters = array();

	/**
	 * Add an action hook.
	 *
	 * @param string $hook          WordPress hook name.
	 * @param object $component     Object instance.
	 * @param string $callback      Method name.
	 * @param int    $priority      Priority.
	 * @param int    $accepted_args Number of accepted arguments.
	 */
	public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Add a filter hook.
	 *
	 * @param string $hook          WordPress hook name.
	 * @param object $component     Object instance.
	 * @param string $callback      Method name.
	 * @param int    $priority      Priority.
	 * @param int    $accepted_args Number of accepted arguments.
	 */
	public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->filters[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Register all hooks with WordPress.
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
