<?php

namespace Ionos\Performance\BasicSettings;

use Ionos\Performance\Options;

/**
 * Manager class
 */
class Manager {
	/**
	 * Inits relevant actions, hooks and filters.
	 *
	 * @since 2.0.0
	 */
	public function init() {
		register_uninstall_hook( MAIN_PLUGIN_FILE_PATH, [ __CLASS__, 'on_uninstall' ] );
	}

	/**
	 * Inits backend related actions, hooks and filters.
	 *
	 * @since 2.0.0
	 */
	public function admin_init() {
		if ( is_multisite() ) {
			add_action( 'delete_blog', [ __CLASS__, 'uninstall_later' ] );
		}
	}

	/**
	 * Gets plugin options.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Removed unused options and renamed method.
	 *
	 * @return array Array of option values
	 */
	public static function get_options() {
		return get_option( 'ionos-performance', [] );
	}

	/**
	 * Get options managed by the basic-settings feature.
	 *
	 * @return array
	 */
	public static function get_feature_options() {
		return array_filter(
			self::get_options(),
			function ( $option ) {
				return str_starts_with( $option, 'basic_' );
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Update the performance options.
	 *
	 * @param array $options The options to update.
	 *
	 * @return bool
	 */
	public static function set_options( $options ) {
		$merged_options = array_merge( self::get_options(), $options );
		return update_option( 'ionos-performance', $merged_options );
	}

	/**
	 * Gets IDs of installed blogs.
	 *
	 * @since 1.0.0
	 *
	 * @return array Blog IDs.
	 */
	private static function get_blog_ids() {
		global $wpdb;

		return $wpdb->get_col( "SELECT blog_id FROM `$wpdb->blogs`" );
	}

	/**
	 * Uninstalling of the plugin per MU blog.
	 *
	 * @since 1.0.0
	 */
	public static function on_uninstall() {
		global $wpdb;

		if ( is_multisite() && ! empty( $_GET['networkwide'] ) ) {
			$old = $wpdb->blogid;

			$ids = self::get_blog_ids();

			foreach ( $ids as $id ) {
				switch_to_blog( $id );
				self::uninstall_backend();
			}

			switch_to_blog( $old );
			return;
		}
		self::uninstall_backend();
	}

	/**
	 * Actual uninstalling of the plugin.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Renamed method.
	 */
	private static function uninstall_backend() {
		delete_option( 'ionos-performance' );

		Options::set_tenant_and_plugin_name( 'ionos', 'performance' );
		Options::clean_up();
	}

	/**
	 * Uninstalling of the plugin for MU and network.
	 *
	 * @since 1.0.0
	 *
	 * @param integer $id Blog ID.
	 */
	public static function uninstall_later( $id ) {
		if ( ! is_plugin_active_for_network( BASENAME ) ) {
			return;
		}

		switch_to_blog( $id );

		self::uninstall_backend();

		restore_current_blog();
	}

	/**
	 * Gets a specific option.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option The option key.
	 *
	 * @return mixed|null
	 */
	public static function get_option( $option ) {
		$options = self::get_options();
		if ( isset( $options[ $option ] ) ) {
			return $options[ $option ];
		}

		return null;
	}
}
