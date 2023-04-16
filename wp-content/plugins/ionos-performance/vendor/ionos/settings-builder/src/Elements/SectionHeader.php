<?php

namespace Ionos\SettingsPage\Elements;

use Ionos\SettingsPage\Interfaces\ISettingsElement;

/**
 * Class SectionHeader.
 */
class SectionHeader implements ISettingsElement {

	/**
	 * The options of the section header.
	 *
	 * @var array $options
	 */
	protected $options = [];

	// phpcs:ignore Squiz.Commenting.VariableComment.Missing
	protected $template_name = 'form-section-header.html.php';

	/**
	 * SectionHeader constructor.
	 *
	 * @param array $options The options of the section header.
	 */
	public function __construct( array $options = [] ) {
		$this->options = $options;
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function get_template() {
		return __DIR__ . '/../templates/' . $this->template_name;
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function render() {
		\load_template( $this->get_template(), false, $this->options );
	}
}
