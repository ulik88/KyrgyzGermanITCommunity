<div>
	<p><?php echo esc_html( $args['label'] ); ?></p>
	<?php
	foreach ( $args['values'] as $key => $value ) :
		$current_values = $args['value'] ? $args['value'] : [];
		$checked        = in_array( $key, $current_values, true ) ? 'checked' : '';
		?>
		<label for="<?php echo esc_attr( $args['name'] . '_' . $key ); ?>"><?php echo esc_html( $value ); ?></label>
		<input type="checkbox" id="<?php echo esc_attr( $args['name'] . '_' . $key ); ?>"
			   name="<?php echo esc_attr( $args['name'] ); ?>[]"
			   value="<?php echo esc_attr( $key ); ?>" <?php echo esc_attr( $checked ); ?>
			<?php echo isset( $args['disabled'] ) && $args['disabled'] ? ' disabled' : ''; ?>>
		<?php
	endforeach;
	?>
</div>
