<div class="wrap">
	<h2 class="headline"><?php echo esc_html( $args['settingsTitle'] ); ?></h2>
	<div class="container">
		<div class="wp-filter">
			<?php $args['builder']->render_tab_list( $args['currentTab'] ); ?>
		</div>
		<span class="horizontal-line"></span>
		<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
			<input type="hidden" name="current_tab" value="<?php echo esc_attr( $args['currentTab'] ); ?>">
			<?php
			settings_fields( $args['builder']->get_option_name() );
			$args['builder']->render_tab_elements( $args['currentTab'] );
			submit_button();
			?>
		</form>
	</div>
</div>
