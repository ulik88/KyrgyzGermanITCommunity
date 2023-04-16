<?php

namespace Ionos\SettingsPage\Elements;

/**
 * Class CheckboxField
 */
class CheckboxField extends AbstractSettingsField {

	// phpcs:ignore Squiz.Commenting.VariableComment.Missing
	protected $template_name = 'checkbox.html.php';

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function validate_input( $input ) { }
}
