<?php

namespace Ionos\Performance;

use Ionos\Performance\Menu;
use Ionos\SettingsPage\SettingsBuilder;

/**
 * Class Settings
 */
class Settings {

	/**
	 * SettingsBuilder instance.
	 *
	 * @var SettingsBuilder
	 */
	private $settings_builder;

	/**
	 * Settings constructor.
	 */
	public function __construct() {
		$this->settings_builder = new SettingsBuilder( 'ionos-performance', esc_html__( 'IONOS Performance', 'ionos-performance' ), 'ionos_performance' );
		$this->settings_builder->register_merge_hook();
	}

	/**
	 * Function for initializing the settings.
	 */
	public function init() {
		add_action(
			'admin_menu',
			function () {
				Menu::add_submenu_page(
					'Performance',
					'Performance',
					'manage_options',
					'ionos_performance',
					[ $this, 'options_page' ],
					null
				);
			}
		);

		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Function for registering the performance settings.
	 */
	public function register_settings() {
		$this->settings_builder->add_sanitize_callback( 'ionos-performance' );
	}

	/**
	 * Function for displaying the settings page.
	 */
	public function options_page() {
		$this->settings_builder->render();
	}
}
