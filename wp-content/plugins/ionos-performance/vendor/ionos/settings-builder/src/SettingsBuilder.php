<?php

namespace Ionos\SettingsPage;

use Ionos\Performance\Config;
use Ionos\Performance\Options;
use Exception;
use Ionos\SettingsPage\Interfaces\ISettingsField;
use Ionos\SettingsPage\Interfaces\ISettingsElement;

/**
 * Class SettingsBuilder.
 */
class SettingsBuilder {

	const HOOK_SUFFIX = '_merge_settings_page';

	/**
	 * Array of registered settings tabs.
	 *
	 * @var array $tabs
	 */
	private $tabs = [];

	/**
	 * Array of registered sanitization callbacks.
	 *
	 * @var array $sanitize_callbacks
	 */
	private $sanitize_callbacks = [];

	/**
	 * Name of the option used.
	 *
	 * @var string $option_name
	 */
	private $option_name;

	/**
	 * Title of the settings page.
	 *
	 * @var string $setting_title
	 */
	private $setting_title;

	/**
	 * Slug of the settings page.
	 *
	 * @var string $menu_slug
	 */
	private $menu_slug;

	/**
	 * SettingsBuilder constructor.
	 *
	 * @param string $option_name Name of the option used.
	 * @param string $setting_title Title of the settings page.
	 * @param string $menu_slug     Slug of the settings page.
	 */
	public function __construct( $option_name, $setting_title, $menu_slug ) {
		$this->option_name   = $option_name;
		$this->setting_title = $setting_title;
		$this->menu_slug     = $menu_slug;
	}

	/**
	 * Register needed assets.
	 */
	public function register_assets() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enque_assets' ] );
	}

	/**
	 * Enqueue settings assets.
	 *
	 * @param string $hook_suffix The suffix used to identify the current admin page.
	 */
	public function enque_assets( $hook_suffix ) {
		$tenant = Options::get_tenant_name();
		if ( $hook_suffix !== "{$tenant}_page_{$this->menu_slug}" ) {
			return;
		}

		wp_enqueue_style(
			'ionos-settings',
			plugin_dir_url( __FILE__ ) . '../assets/css/style.css',
			'',
			filemtime( __DIR__ . '/../assets/css/style.css' )
		);
	}

	/**
	 * Registers a hook for merge functionality
	 * Hook needs a settingsBuilder as parameter
	 */
	public function register_merge_hook() {
		$hook_name = str_replace( '-', '_', $this->menu_slug ) . self::HOOK_SUFFIX;
		add_filter( $hook_name, [ $this, 'merge' ], 10, 1 );
	}

	/**
	 * Unregister hook for merge functionality
	 *
	 * @throws Exception If hook is not registered.
	 */
	public function unregister_merge_hook() {
		$hook_name = str_replace( '-', '_', $this->menu_slug ) . self::HOOK_SUFFIX;
		if ( empty( $hook_name ) ) {
			throw new Exception( 'No hook was registered' );
		}
		remove_filter( $hook_name, [ $this, 'merge' ] );
	}

	/**
	 * Clean up the settings page.
	 *
	 * @throws Exception If hook is not registered.
	 */
	public function clean_up() {
		foreach ( $this->tabs as $tab ) {
			foreach ( $tab['elements'] as $field ) {
				if ( $field instanceof ISettingsField ) {
					if ( strpos( $field->get_name(), '[' ) === false ) {
						unregister_setting( $this->option_name, $field->get_name() );
					}
				}
			}
		}
		$this->unregister_merge_hook();
	}

	/**
	 * Merge another settings page into this one.
	 *
	 * @param SettingsBuilder $settings_builder The settings builder to merge with.
	 *
	 * @return void
	 */
	public function merge( $settings_builder ) {
		$this->tabs = array_merge( $this->tabs, $settings_builder->tabs );
	}

	/**
	 * Add a tab to the settings page.
	 *
	 * @param string $tab_id The id of the tab.
	 * @param string $tab_title The title of the tab.
	 *
	 * @return self
	 */
	public function add_tab( $tab_id, $tab_title ) {
		$this->tabs[ $tab_id ] = [
			'title'    => $tab_title,
			'elements' => [],
		];

		return $this;
	}

	/**
	 *  Add a field to the settings page.
	 *
	 * @param string         $tab_id The id of the tab to add the field to.
	 * @param ISettingsField $field The field to add.
	 * @param callable|null  $sanitize_callback The callback to sanitize the field.
	 *
	 * @throws Exception If tab does not exist.
	 *
	 * @return self
	 */
	public function add_field( $tab_id, ISettingsField $field, $sanitize_callback = null ) {
		if ( isset( $this->tabs[ $tab_id ] ) && is_array( $this->tabs[ $tab_id ] ) ) {
			if ( strpos( $field->get_name(), '[' ) === false ) {
				if ( $sanitize_callback === null ) {
					$sanitize_callback = [ $field, 'sanitize_input' ];
				}

				\register_setting( $this->option_name, $field->get_name(), $sanitize_callback );
			}

			$this->tabs[ $tab_id ]['elements'][ $field->get_name() ] = $field;
		} else {
			throw new Exception( 'Tab does not exist.' );
		}

		return $this;
	}

	/**
	 * Add an element to the settings page.
	 *
	 * @param string           $tab_id The id of the tab to add the field to.
	 * @param ISettingsElement $element The element to add.
	 *
	 * @throws Exception If tab does not exist.
	 *
	 * @return self
	 */
	public function add_element( $tab_id, ISettingsElement $element ) {
		if ( isset( $this->tabs[ $tab_id ] ) && is_array( $this->tabs[ $tab_id ] ) ) {
			$this->tabs[ $tab_id ]['elements'][] = $element;
		} else {
			throw new Exception( 'Tab does not exist.' );
		}

		return $this;
	}

	/**
	 * Function for using option arrays.
	 *
	 * @param string $option_name Name of the option.
	 */
	public function add_sanitize_callback( $option_name ) {
		if ( in_array( $option_name, $this->sanitize_callbacks ) ) {
			return;
		}
		$this->sanitize_callbacks[] = $option_name;
		\register_setting(
			$this->option_name,
			$option_name,
			function( $data ) use ( $option_name ) {
				return $this->options_array_sanitize_callback( $data, $option_name );
			}
		);
	}

	/**
	 * Sanitize callback for option arrays.
	 *
	 * @param array  $data The data to sanitize.
	 * @param string $option_name The name of the option.
	 *
	 * @return array
	 */
	private function options_array_sanitize_callback( $data, $option_name ) {
		$current_tab = isset( $_POST['current_tab'] ) ? $_POST['current_tab'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return (array) apply_filters( "{$option_name}_options_array_sanitize_callback", $data, $current_tab );
	}

	/**
	 * Render the settings page.
	 */
	public function render() {
		$current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : array_keys( $this->tabs )[0]; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Fall back to first tab if current tab does not exist.
		if ( ! array_key_exists( $current_tab, $this->tabs ) ) {
			$current_tab = array_keys( $this->tabs )[0];
		}

		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( $this->option_name, $this->option_name, __( 'Settings Saved', 'ionos-settings' ), 'updated' );
		}
		settings_errors( $this->option_name );

		load_template(
			__DIR__ . '/templates/wrapper.html.php',
			true,
			[
				'builder'       => $this,
				'settingsTitle' => $this->setting_title,
				'currentTab'    => $current_tab,
			]
		);
	}

	/**
	 * Load TabList template with current tab
	 *
	 * @param string $current_tab The current tab.
	 *
	 * @return void
	 */
	public function render_tab_list( $current_tab ) {
		load_template(
			__DIR__ . '/templates/tablist.html.php',
			false,
			[
				'url'        => \admin_url( 'admin.php?page=' . $this->menu_slug ),
				'tabs'       => $this->tabs,
				'currentTab' => $current_tab,
			]
		);
	}

	/**
	 * Render elements for current tab
	 *
	 * @param string $current_tab The current tab.
	 *
	 * @return void
	 */
	public function render_tab_elements( $current_tab ) {
		$tab = $this->tabs[ $current_tab ];
		foreach ( $tab['elements'] as $element ) {
			$element->render();
		}
	}

	/**
	 * Get the option name.
	 *
	 * @return string
	 */
	public function get_option_name() {
		return $this->option_name;
	}
}
