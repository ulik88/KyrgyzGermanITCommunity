<?php

namespace Ionos\SettingsPage\Elements;

/**
 * Class CheckboxGroupField.
 */
class CheckboxGroupField extends AbstractSettingsField {

	// phpcs:ignore Squiz.Commenting.VariableComment.Missing
	protected $template_name = 'checkboxgroup.html.php';

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function validate_input( $input ) {
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function sanitize_input( $input ) {
		return $input;
	}
}
