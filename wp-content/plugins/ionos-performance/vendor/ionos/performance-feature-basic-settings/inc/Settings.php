<?php

namespace Ionos\Performance\BasicSettings;

use Ionos\Performance\Menu;
use Exception;
use Ionos\SettingsPage\Elements\CheckboxField;
use Ionos\SettingsPage\Elements\SectionHeader;
use Ionos\SettingsPage\SettingsBuilder;

/**
 * Class for handling the basic-settings tab.
 */
class Settings {

	const TAB_NAME  = 'basic-settings';
	const MENU_SLUG = 'ionos_performance';

	/**
	 * SettingsBuilder instance for the basic-settings tab.
	 *
	 * @var SettingsBuilder $settings_builder
	 */
	private $settings_builder;

	/**
	 * Settings constructor.
	 *
	 * @throws Exception If something goes wrong while adding elements.
	 */
	public function __construct() {
		$this->settings_builder = new SettingsBuilder( 'ionos-performance', esc_html__( 'IONOS Performance', 'ionos-performance' ), self::MENU_SLUG );

		$this->settings_builder->register_assets();

		$this->settings_builder->add_tab( self::TAB_NAME, esc_html__( 'Basic Settings', 'ionos-performance' ) );

		$this->settings_builder->add_element(
			self::TAB_NAME,
			new SectionHeader(
				[
					'heading' => esc_html__( 'Basic Settings', 'ionos-performance' ),
				]
			)
		);

		$this->settings_builder->add_field(
			self::TAB_NAME,
			new CheckboxField(
				'ionos-performance[basic_directory_index]',
				[
					'label'                => esc_html__( 'Directory Index', 'ionos-performance' ),
					'description'          => __( 'Adds the <code>Directory Index</code> rule to the <code>.htaccess</code> so that the webserver does not search for other default files inside a directory.', 'ionos-performance' ),
					'kses_for_description' => [
						'code' => [],
					],
				]
			)
		);

		$this->settings_builder->add_field(
			self::TAB_NAME,
			new CheckboxField(
				'ionos-performance[basic_cache_expiration]',
				[
					'label'       => esc_html__( 'Cache expiration', 'ionos-performance' ),
					'description' => esc_html__( 'Defines a cache expiration for static assets like images and font files, so users that visit your site repeatedly get the files from their browser cache.', 'ionos-performance' ),
				]
			)
		);

		$this->settings_builder->add_field(
			self::TAB_NAME,
			new CheckboxField(
				'ionos-performance[basic_deflate]',
				[
					'label'       => esc_html__( 'Mod Deflate', 'ionos-performance' ),
					'description' => esc_html__( 'Activates gzip compression for static files to reduce file size and make your site faster.', 'ionos-performance' ),
				]
			)
		);

		$this->merge_with_base();
	}

	/**
	 * Function for using the settings in standalone mode.
	 */
	public function init_for_standalone() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		add_action(
			'admin_menu',
			function () {
				Menu::add_submenu_page(
					'Performance',
					'Performance',
					'manage_options',
					self::MENU_SLUG,
					[ $this, 'options_page' ],
					null
				);
			}
		);
	}

	/**
	 * Calls merge_settings_page filter to merge the settings builder.
	 */
	private function merge_with_base() {
		apply_filters( self::MENU_SLUG . '_merge_settings_page', $this->settings_builder );
		add_filter( 'ionos-performance_options_array_sanitize_callback', [ $this, 'validate_options' ], 10, 2 );
	}

	/**
	 * Registers the settings when used in standalone mode.
	 */
	public function register_settings() {
		$this->settings_builder->add_sanitize_callback( 'ionos-performance' );
	}

	/**
	 * Renders the settings page when used in standalone mode.
	 *
	 * @throws Exception If current selected tab does not exists.
	 */
	public function options_page() {
		$this->settings_builder->render();
	}

	/**
	 * Validates the options.
	 *
	 * @param array  $data Array of raw form values.
	 * @param string $tab The current tab.
	 *
	 * @return array Array of validated values.
	 */
	public function validate_options( $data, $tab ) {
		if ( $tab !== self::TAB_NAME ) {
			return array_merge( Manager::get_feature_options(), $data );
		}

		if ( empty( $data ) ) {
			return [];
		}

		return array_merge(
			$data,
			[
				'basic_directory_index'  => (int) ( ! empty( $data['basic_directory_index'] ) ),
				'basic_cache_expiration' => (int) ( ! empty( $data['basic_cache_expiration'] ) ),
				'basic_deflate'          => (int) ( ! empty( $data['basic_deflate'] ) ),
			]
		);
	}
}
