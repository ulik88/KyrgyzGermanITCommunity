<div class="ionos-form-checkbox">
	<div class="switch">
		<?php
		$checked  = isset( $args['value'] ) && $args['value'];
		$disabled = isset( $args['disabled'] ) && $args['disabled'];
		if ( $checked ) {
			?>
			<input id='<?php echo esc_attr( $args['name'] ); ?>' name='<?php echo esc_attr( $args['name'] ); ?>'
				   type='checkbox' checked <?php echo $disabled ? 'disabled' : ''; ?>/>
			<?php
		} else {
			?>
			<input id='<?php echo esc_attr( $args['name'] ); ?>' name='<?php echo esc_attr( $args['name'] ); ?>'
				   type='checkbox' <?php echo $disabled ? 'disabled' : ''; ?>/>
			<?php
		}
		?>
		<span class="checkbox-toggle"></span>
	</div>
	<label for="<?php echo esc_attr( $args['name'] ); ?>" class="switch-text headline-small">
		<?php echo esc_html( $args['label'] ); ?>
	</label>
	<?php if ( ! empty( $args['description'] ) ) { ?>
		<p class="description">
			<?php
			if ( ! empty( $args['kses_for_description'] ) ) {
				echo wp_kses( $args['description'], $args['kses_for_description'] );
			} else {
				echo esc_html( $args['description'] );
			}
			?>
		</p>
	<?php } ?>
</div>
