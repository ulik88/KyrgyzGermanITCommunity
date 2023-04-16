<?php
if ( ! empty( $args['heading'] ) ) {
	?>
	<h2 class="headline"><?php echo esc_html( $args['heading'] ); ?></h2>
	<?php
}
if ( ! empty( $args['description'] ) ) {
	?>
	<p class="description">
		<?php
		if ( ! empty( $args['kses_for_description'] ) ) {
			echo wp_kses( $args['description'], $args['kses_for_description'] );
		} else {
			echo esc_html( $args['description'] );
		}
		?>
	</p>
	<?php
}
