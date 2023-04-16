<?php

namespace Ionos\SettingsPage\Elements;

use Ionos\SettingsPage\Interfaces\ISettingsField;

/**
 * Abstract class for settings fields.
 */
abstract class AbstractSettingsField implements ISettingsField {

	/**
	 * The name of the field.
	 *
	 * @var string $name
	 */
	protected $name;

	/**
	 * The options of the field.
	 *
	 * @var array $options
	 */
	protected $options = [];

	/**
	 * Name of the template.
	 *
	 * @var string $template_name
	 */
	protected $template_name;

	/**
	 * AbstractSettingsField constructor.
	 *
	 * @param string $name   The name of the field.
	 * @param array  $options The options of the field.
	 */
	public function __construct( $name, array $options = [] ) {
		$this->name    = $name;
		$this->options = $options;
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function render() {
		$this->options['name'] = $this->get_name();

		if ( ! isset( $this->options['value'] ) ) {
			$this->options['value'] = $this->get_value();
		}

		\load_template( $this->get_template(), false, $this->options );
	}

	/**
	 * Returns the current value of the field.
	 *
	 * @return false|mixed|string|null
	 */
	private function get_value() {
		if ( strpos( $this->get_name(), '[' ) !== false ) {
			$option_name = explode( '[', $this->get_name() )[0];
			$option      = \get_option( $option_name );

			$option_key = str_replace( $option_name . '[', '', $this->get_name() );
			$option_key = str_replace( ']', '', $option_key );

			return isset( $option[ $option_key ] ) ? $option[ $option_key ] : '';
		}
		return \get_option( $this->get_name() );
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function get_name() {
		return $this->name;
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function get_template() {
		return __DIR__ . '/../templates/fields/' . $this->template_name;
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function sanitize_input( $input ) {
		return $input;
	}

}
