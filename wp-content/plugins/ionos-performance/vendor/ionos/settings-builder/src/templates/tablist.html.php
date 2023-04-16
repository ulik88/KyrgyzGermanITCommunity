<ul class="filter-links">
	<?php foreach ( $args['tabs'] as $tab_name => $info ) : ?>
		<li class="ionos-settings--<?php echo esc_html( $tab_name ); ?>">
			<a href="<?php echo esc_url( $args['url'] . '&tab=' . $tab_name ); ?>" <?php echo ( $args['currentTab'] === $tab_name ) ? 'class="current' : ''; ?>
			   aria-current="page">
				<?php echo esc_html( $info['title'] ); ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
