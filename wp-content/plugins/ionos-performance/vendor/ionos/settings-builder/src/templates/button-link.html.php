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
<a href="<?php echo esc_url_raw( $args['href'] ); ?>" class="button"><?php echo esc_html( $args['label'] ); ?></a>
