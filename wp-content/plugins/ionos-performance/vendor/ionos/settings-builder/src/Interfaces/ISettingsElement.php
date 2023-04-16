<?php

namespace Ionos\SettingsPage\Interfaces;

/**
 * Interface ISettingsElement.
 */
interface ISettingsElement {

	/**
	 * Returngs the path of the template file.
	 *
	 * @return string
	 */
	public function get_template();

	/**
	 * Renders the element.
	 */
	public function render();
}
