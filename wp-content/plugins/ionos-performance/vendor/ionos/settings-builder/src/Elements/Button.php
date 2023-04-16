<?php

namespace Ionos\SettingsPage\Elements;

use Ionos\SettingsPage\Interfaces\ISettingsElement;

/**
 * Class Button.
 */
class Button implements ISettingsElement {

	/**
	 * The options of the button.
	 *
	 * @var array $options
	 */
	protected $options = [];

	/**
	 * The name of the template.
	 *
	 * @var string $template_name
	 */
	protected $template_name = 'button.html.php';

	/**
	 * Button constructor.
	 *
	 * @param array $options The options of the button.
	 */
	public function __construct( array $options = [] ) {
		$this->options = $options;

		if ( ! empty( $this->options['href'] ) ) {
			$this->template_name = 'button-link.html.php';
		}
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
