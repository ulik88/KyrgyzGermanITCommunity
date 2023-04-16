<?php

namespace Ionos\SettingsPage\Elements;

use Ionos\SettingsPage\Interfaces\ISettingsField;

/**
 * Class CustomField.
 */
class CustomField implements ISettingsField {

	/**
	 * The name of the field.
	 *
	 * @var string $name
	 */
	private $name;

	/**
	 * The path of the template file.
	 *
	 * @var string $template
	 */
	private $template;

	/**
	 * The options of the field.
	 *
	 * @var array $options
	 */
	protected $options = [];

	/**
	 * Callback for sanitizing the field's value.
	 *
	 * @var callable $sanitize_callback
	 */
	private $sanitize_callback;

	/**
	 * Callback for validating the field's value.
	 *
	 * @var callable $validate_callback
	 */
	private $validate_callback;

	/**
	 * CustomField constructor.
	 *
	 * @param string   $name The name of the field.
	 * @param string   $template The path of the template file.
	 * @param callable $sanitize_callback Callback for sanitizing the field's value.
	 * @param callable $validate_callback Callback for validating the field's value.
	 * @param array    $options The options of the field.
	 */
	public function __construct( $name, $template, $sanitize_callback, $validate_callback, array $options = [] ) {
		$this->name              = $name;
		$this->template          = $template;
		$this->sanitize_callback = $sanitize_callback;
		$this->validate_callback = $validate_callback;
		$this->options           = $options;
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function get_name() {
		return $this->name;
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function get_template() {
		return $this->template;
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function validate_input( $input ) {
		if ( is_callable( $this->validate_callback ) ) {
			call_user_func( $this->validate_callback, $input );
		}
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function sanitize_input( $input ) {
		if ( is_callable( $this->sanitize_callback ) ) {
			return call_user_func( $this->sanitize_callback, $input );
		}
		return $input;
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function render() {
		$this->options['name']  = $this->get_name();
		$this->options['value'] = \get_option( $this->get_name() );
		\load_template( $this->get_template(), false, $this->options );
	}

}
