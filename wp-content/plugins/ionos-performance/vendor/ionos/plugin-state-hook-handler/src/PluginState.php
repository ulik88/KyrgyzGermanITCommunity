<?php

namespace Ionos\PluginStateHookHandler;

use Exception;
use InvalidArgumentException;

/**
 * Providing methods to handle plugin state changes.
 */
class PluginState {
	/**
	 * The path to the main plugin file.
	 *
	 * @var string Path to main plugin file.
	 */
	private $plugin_file_path;

	/**
	 * The entities that shall be removed when a certain event like `uninstall` or `deactivate` occurs
	 *
	 * @var array Multidimensional array with `event` as first and `entities` as second dimension.
	 */
	private $entities_to_remove_on_event = [];

	/**
	 * Whether the cleanup hooks were registered or not.
	 *
	 * @var bool True after the registration of the cleanup hooks.
	 */
	private $did_register_cleanup_hooks = false;

	/**
	 * Basename of plugin (e.g. »plugin-name/plugin-name.php«).
	 *
	 * @var string The plugin basename.
	 */
	private $plugin_basename;

	/**
	 * Sets attributes.
	 *
	 * @param string $plugin_file_path Main plugin file path.
	 */
	public function __construct( $plugin_file_path ) {
		$this->plugin_file_path = $plugin_file_path;
		$this->plugin_basename = plugin_basename( $this->plugin_file_path );
	}

	/**
	 * Registers the deactivation and uninstall cleanup hooks
	 *
	 * @throws Exception If cleanup hooks were already registered.
	 */
	public function register_cleanup_hooks() {
		if ( true === $this->did_register_cleanup_hooks ) {
			throw new Exception( 'You cannot register the cleanup hooks multiple times.' );
		}

		register_deactivation_hook( $this->plugin_file_path, [ $this, 'deactivate' ] );
		register_uninstall_hook( $this->plugin_file_path, [ __CLASS__, 'dummy' ] );
		add_action( "uninstall_{$this->plugin_basename}", [ $this, 'uninstall' ] );

		$this->did_register_cleanup_hooks = true;

		return $this;
	}

	/**
	 * Deletes given items of specified type.
	 *
	 * @param string $event The name of the event. Can be `uninstall` or `deactivate`.
	 */
	private function cleanup( $event ) {
		if ( ! isset( $this->entities_to_remove_on_event[ $event ] ) ) {
			return;
		}

		foreach ( $this->entities_to_remove_on_event[ $event ] as $entity ) {
			if ( 'option' === $entity['type'] ) {
				delete_option( $entity['name'] );
				continue;
			}

			if ( 'transient' === $entity['type'] ) {
				delete_transient( $entity['name'] );
			}
		}
	}

	/**
	 * We use this as dummy function to register an empty uninstall hook.
	 * When the uninstall hook is fired the ùninstall_{$file} action will
	 * fire too.
	 */
	public static function dummy() {}

	/**
	 * Will be registered as uninstall hook
	 */
	public function uninstall() {
		$this->cleanup( 'uninstall' );
	}

	/**
	 * Will be registered as deactivate hook
	 */
	public function deactivate() {
		$this->cleanup( 'deactivate' );
	}

	/**
	 * Registers the given callable as activation hook.
	 *
	 * @param callable $callable The code that runs on activation.
	 *
	 * @throws InvalidArgumentException If $callable is not callable.
	 * @throws Exception If plugins_loaded hook already ran.
	 */
	public function on_activation( $callable ) {
		if ( is_callable( $callable ) === false ) {
			throw new InvalidArgumentException( 'The argument $callable must be callable.' );
		}

		if ( did_action( 'plugins_loaded' ) === true ) {
			throw new Exception( 'You cannot register activation code from within an action.' );
		}

		register_activation_hook( $this->plugin_file_path, $callable );

		return $this;
	}

	/**
	 * Registers an entity like an `option` or a `transient` for removal.
	 *
	 * @param array  $names The entity names that should be removed.
	 * @param string $type The type of the entity. Can be `option` or `transient`.
	 * @param string $event The name of the event. Can be `uninstall` or `deactivate`.
	 */
	private function remove_entities_on_event( $names, $type, $event ) {
		if ( false === $this->did_register_cleanup_hooks ) {
			throw new Exception( 'You have to register the cleanup hooks first before adding entities.' );
		}

		if ( ! isset( $this->entities_to_remove_on_event[ $event ] ) ) {
			$this->entities_to_remove_on_event[ $event ] = [];
		}

		foreach ( $names as $name ) {
			$entity = [
				'name' => $name,
				'type' => $type,
			];
			if ( ! in_array( $name, $this->entities_to_remove_on_event[ $event ], true ) ) {
				array_push( $this->entities_to_remove_on_event[ $event ], $entity );
			}
		}

		return $this;
	}

	/**
	 * Removes given options on uninstall.
	 *
	 * @param array $options Array of option names.
	 */
	public function remove_options_on_uninstall( $options ) {
		return $this->remove_entities_on_event( $options, 'option', 'uninstall' );
	}

	/**
	 * Removes given transients on uninstall.
	 *
	 * @param array $transients Array of transient names.
	 */
	public function remove_transients_on_uninstall( $transients ) {
		return $this->remove_entities_on_event( $transients, 'transient', 'uninstall' );
	}

	/**
	 * Removes given options on deactivation.
	 *
	 * @param array $options Array of option names.
	 */
	public function remove_options_on_deactivation( $options ) {
		return $this->remove_entities_on_event( $options, 'option', 'deactivate' );
	}

	/**
	 * Removes given transients on deactivation.
	 *
	 * @param array $transients Array of transient names.
	 */
	public function remove_transients_on_deactivation( $transients ) {
		return $this->remove_entities_on_event( $transients, 'transient', 'deactivate' );
	}
}
