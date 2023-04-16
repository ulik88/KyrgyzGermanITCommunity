<?php

namespace Ionos\SettingsPage\Interfaces;

/**
 * Interface ISettingsElement.
 */
interface ISettingsField {

	/**
	 * Returns the path of the template file.
	 *
	 * @return string
	 */
	public function get_template();

	/**
	 * Check if the input is valid.
	 *
	 * @param mixed $input The input value.
	 */
	public function validate_input( $input );

	/**
	 * Sanitizes the input.
	 *
	 * @param mixed $input The input value.
	 *
	 * @return mixed The sanitized input value.
	 */
	public function sanitize_input( $input );

	/**
	 * Renders the field.
	 */
	public function render();

	/**
	 * Returns the name of the field.
	 *
	 * @return string
	 */
	public function get_name();

}
