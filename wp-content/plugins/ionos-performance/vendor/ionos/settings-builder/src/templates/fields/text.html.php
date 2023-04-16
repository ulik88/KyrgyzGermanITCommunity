<div class="flex-col">
	<label for="<?php echo esc_attr( $args['name'] ); ?>" class="headline-small">
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
	<input class="inputfield" id='<?php echo esc_attr( $args['name'] ); ?>'
		   name='<?php echo esc_attr( $args['name'] ); ?>' type='text'
		   value='<?php echo isset( $args['value'] ) ? esc_attr( $args['value'] ) : ''; ?>'
		   <?php echo isset( $args['disabled'] ) && $args['disabled'] ? ' disabled' : ''; ?>
		   <?php echo isset( $args['readonly'] ) && $args['readonly'] ? ' readonly' : ''; ?>/>
</div>
