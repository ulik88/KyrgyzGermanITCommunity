<?php

namespace Ionos\SettingsPage\Interfaces;

/**
 * Interface ISettingsButton.
 */
interface ISettingsButton {

	/**
	 * Path to the template file.
	 *
	 * @return string
	 */
	public function get_template();

	/**
	 * Rendering the button.
	 */
	public function render();
}
