<?php

namespace Ionos\Performance\Caching;

use WP_REST_Server;
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
		add_action( 'init', [ __CLASS__, 'register_publish_hooks' ], 99 );
		add_action( 'init', [ __CLASS__, 'register_flush_cache_hooks' ], 10, 0 );
		add_action( 'init', [ __CLASS__, 'process_flush_request' ] );

		Gui::init();

		if ( Gui::is_caching_enabled() && ! Helper::has_conflicting_caching_plugins() ) {
			Rest::init();
			Cron::init();
		}

		add_action( 'pre_comment_approved', [ __CLASS__, 'pre_comment' ], 99, 2 );

		register_activation_hook( MAIN_PLUGIN_FILE_PATH, [ __CLASS__, 'on_activation' ] );
		register_deactivation_hook( MAIN_PLUGIN_FILE_PATH, [ __CLASS__, 'on_deactivation' ] );
		register_uninstall_hook( MAIN_PLUGIN_FILE_PATH, [ __CLASS__, 'on_uninstall' ] );

		add_action(
			'update_option_rewrite_rules',
			function( $old_value, $value ) {
				if ( $old_value === $value ) {
					return;
				}
				Caching::flush_total_cache();
			},
			10,
			2
		);
	}

	/**
	 * Inits backend related actions, hooks and filters.
	 *
	 * @since 2.0.0
	 */
	public function admin_init() {
		if ( is_multisite() ) {
			add_action( 'wpmu_new_blog', [ __CLASS__, 'install_later' ] );
			add_action( 'delete_blog', [ __CLASS__, 'uninstall_later' ] );
		}

		add_action( 'transition_comment_status', [ __CLASS__, 'touch_comment' ], 10, 3 );

		add_action( 'edit_comment', [ __CLASS__, 'edit_comment' ] );

		Gui::admin_init();
	}

	/**
	 * Inits frontend related actions
	 *
	 * @since 2.0.0
	 */
	public function frontend_init() {
		Caching::init();
		add_action( 'do_robots', [ __CLASS__, 'robots_txt' ] );
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
	 * Gets feature plugin options.
	 *
	 * @return array Array of feature options
	 */
	public static function get_feature_options() {
		$feature_options = [
			'caching_enabled' => 1,
			'cache_expires'   => 12,
			'without_ids'     => '',
		];

		foreach ( $feature_options as $key => $value ) {
			if ( isset( self::get_options()[ $key ] ) ) {
				$feature_options[ $key ] = self::get_options()[ $key ];
			}
		}

		return $feature_options;
	}

	/**
	 * Deactivation hook.
	 *
	 * @since 1.0.0
	 */
	public static function on_deactivation() {
		Cron::un_schedule();

		Caching::flush_total_cache();
	}

	/**
	 * Activation hook.
	 *
	 * @since 1.0.0
	 */
	public static function on_activation() {
		if ( is_multisite() && ! empty( $_GET['networkwide'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$ids = self::get_blog_ids();

			foreach ( $ids as $id ) {
				switch_to_blog( $id );
				Caching::flush_total_cache();
			}

			restore_current_blog();
			return;
		}

		Caching::flush_total_cache();
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
	 * Plugin installation on new MU blog.
	 *
	 * @since 1.0.0
	 *
	 * @param integer $id Blog ID.
	 */
	public static function install_later( $id ) {
		if ( ! is_plugin_active_for_network( BASENAME ) ) {
			return;
		}

		switch_to_blog( $id );

		Caching::flush_total_cache();

		restore_current_blog();
	}

	/**
	 * Uninstalling of the plugin per MU blog.
	 *
	 * @since 1.0.0
	 */
	public static function on_uninstall() {
		global $wpdb;

		if ( is_multisite() && ! empty( $_GET['networkwide'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
		Options::clean_up( MAIN_PLUGIN_FILE_PATH );

		Caching::flush_total_cache();
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

	/**
	 * Modifies the robots.txt.
	 *
	 * @since 1.0.0
	 */
	public static function robots_txt() {
		echo 'Disallow: */cache/ionos-performance/';
	}

	/**
	 * Processes the plugin's meta actions.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Removed unused parameter.
	 */
	public static function process_flush_request() {
		if ( empty( $_GET['_ionos-performance'] ) || 'flush' !== $_GET['_ionos-performance'] ) {
			return;
		}

		$verified_nonce = wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), '_ionos_performance_flush_nonce' );
		if ( empty( $_GET['_wpnonce'] ) || ! $verified_nonce ) {
			return;
		}

		if ( ! is_admin_bar_showing() || ! Helper::current_user_can_flush_cache() ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		Caching::flush_cache();

		wp_safe_redirect(
			add_query_arg(
				'_ionos-performance',
				'flushed',
				wp_get_referer()
			)
		);

		exit();
	}

	/**
	 * Removes page from cache or flush on comment edit.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Removed unused param.
	 */
	public static function edit_comment() {
		Caching::flush_total_cache();
	}

	/**
	 * Removes page from cache or flush on new comment.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $approved Comment status.
	 *
	 * @return mixed Comment status.
	 */
	public static function pre_comment( $approved ) {
		if ( 1 === $approved ) {
			Caching::flush_total_cache();
		}

		return $approved;
	}

	/**
	 * Flushes cache or flush on comment edit.
	 *
	 * @since 1.0.0
	 *
	 * @param string $new_status New comment status.
	 * @param string $old_status Old comment status.
	 */
	public static function touch_comment( $new_status, $old_status ) {
		if ( $new_status !== $old_status ) {
			Caching::flush_total_cache();
		}
	}

	/**
	 * Registers publish hook for custom post types.
	 *
	 * @since 1.0.0
	 */
	public static function register_publish_hooks() {
		$post_types = get_post_types(
			[
				'public' => true,
			]
		);

		if ( empty( $post_types ) ) {
			return;
		}

		foreach ( $post_types as $post_type ) {
			add_action( 'publish_' . $post_type, [ __CLASS__, 'publish_post_types' ], 10, 2 );
			add_action( 'publish_future_' . $post_type, [ Caching::class, 'flush_total_cache' ] );
		}
	}

	/**
	 * Removes the post type cache on post updates.
	 *
	 * @since 1.0.0
	 *
	 * @param integer $post_id The WordPress Post ID.
	 * @param object  $post    The WordPress Post object.
	 */
	public static function publish_post_types( $post_id, $post ) {
		if ( empty( $post_id ) || empty( $post ) ) {
			return;
		}

		if ( ! in_array( $post->post_status, [ 'publish', 'future' ], true ) ) {
			return;
		}

		if ( ! current_user_can( 'publish_posts' ) ) {
			return;
		}

		Caching::flush_total_cache();
	}

	/**
	 * Registers all hooks to flush the total cache.
	 *
	 * @since 1.0.0
	 */
	public static function register_flush_cache_hooks() {
		$flush_cache_hooks = [
			'ionos_performance_flush_cache' => 10,
			'_core_updated_successfully'    => 10,
			'switch_theme'                  => 10,
			'before_delete_post'            => 10,
			'wp_trash_post'                 => 10,
			'create_term'                   => 10,
			'delete_term'                   => 10,
			'edit_terms'                    => 10,
			'user_register'                 => 10,
			'edit_user_profile_update'      => 10,
			'delete_user'                   => 10,
		];

		/**
		 * Filters the registered hooks to flush the total cache.
		 *
		 * @since 1.0.0
		 * @since 2.0.0 Renamed filter.
		 *
		 * @param int $flush_cache_hooks List of registered hooks.
		 */
		$flush_cache_hooks = apply_filters( 'ionos_performance_flush_cache_hooks', $flush_cache_hooks );

		foreach ( $flush_cache_hooks as $hook => $priority ) {
			add_action( $hook, [ Caching::class, 'flush_total_cache' ], $priority, 0 );
		}
	}
}
