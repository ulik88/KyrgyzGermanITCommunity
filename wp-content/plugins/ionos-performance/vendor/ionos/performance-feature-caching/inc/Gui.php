<?php

namespace Ionos\Performance\Caching;

use Ionos\Performance\Menu;
use Ionos\SettingsPage\Elements\Button;
use Ionos\SettingsPage\Elements\SectionHeader;
use Ionos\SettingsPage\Elements\NumberField;
use Ionos\SettingsPage\Elements\TextField;
use Ionos\SettingsPage\SettingsBuilder;
use Ionos\SettingsPage\Elements\CheckboxField;

/**
 * Class GUI.
 */
class Gui {
	const TAB_NAME = 'caching';

	/**
	 * SettingsBuilder instance.
	 *
	 * @var SettingsBuilder $settings_builder
	 */
	private static $settings_builder;

	/**
	 * Inits the actions for the GUI.
	 *
	 * @since 2.0.0
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_scripts' ] );
		add_action( 'init', [ __CLASS__, 'register_styles' ] );

		if ( self::is_caching_enabled() && ! Helper::has_conflicting_caching_plugins() ) {
			add_action( 'admin_bar_menu', [ __CLASS__, 'add_flush_icon' ], 90 );
			add_action( 'admin_bar_menu', [ __CLASS__, 'add_flush_icon_script' ], 90 );
		}

		if ( is_admin() ) {
			self::create_option_page();

			self::merge_with_base();

			if ( defined( __NAMESPACE__ . '\STANDALONE_PLUGIN' ) ) {
				self::init_for_standalone();
			}
		}
	}

	/**
	 * Inits the backend only
	 *
	 * @return void
	 */
	public static function admin_init() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'add_admin_resources' ] );
		add_action( 'admin_head', [ __CLASS__, 'admin_dashboard_styles' ] );
		add_action( 'admin_init', [ __CLASS__, 'add_permalink_warning' ] );
		add_action( 'admin_notices', [ __CLASS__, 'add_guided_component_activation_notice' ] );

		if ( get_option( 'ionos_performance_show_activation_admin_notice' ) ) {
			add_action( 'admin_notices', [ __CLASS__, 'add_existing_customer_installed_notice' ] );
			add_action( 'wp_ajax_ionos_perfomance_dismiss_admin_notice', [ __CLASS__, 'dismiss_admin_notice' ] );
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_dismiss_notice_script' ] );
		}

		add_filter( 'dashboard_glance_items', [ __CLASS__, 'add_dashboard_count' ] );
		add_action( 'update_option_ionos-performance', [ __CLASS__, 'maybe_remove_existing_customer_options' ], 10, 2 );
		add_action(
			'add_option_ionos-performance',
			function( $option, $value ) {
				self::maybe_remove_existing_customer_options( '', $value );
			},
			10,
			2
		);
	}

	/**
	 * Enqueues script to make existing customers admin notice dismissable.
	 *
	 * @param string $hook_suffix Hook suffix used for notice dismissal.
	 */
	public static function enqueue_dismiss_notice_script( $hook_suffix ) {
		if ( $hook_suffix !== 'index.php' ) {
			return;
		}

		wp_enqueue_script(
			'ionos_dismissible_notice',
			plugins_url( 'js/admin-notice-dismissible.js', FEATURE_MAIN_PLUGIN_FILE_PATH ),
			[ 'jquery', 'common' ],
			filemtime( plugin_dir_path( FEATURE_MAIN_PLUGIN_FILE_PATH ) . 'js/admin-notice-dismissible.js' ),
			true
		);
	}

	/**
	 * Action triggered by AJAX call when customer dismisses admin notice.
	 */
	public static function dismiss_admin_notice() {
		delete_option( 'ionos_performance_show_activation_admin_notice' );
		wp_die();
	}

	/**
	 * Adds flush icon to admin bar menu.
	 *
	 * @since 1.0.0
	 *
	 * @param object $wp_admin_bar Object of menu items.
	 */
	public static function add_flush_icon( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() || ! Helper::current_user_can_flush_cache() ) {
			return;
		}

		wp_enqueue_style( 'ionos-performance-admin-bar-flush' );

		?>
		<style>#wp-admin-bar-ionos-performance{display:list-item !important} #wp-admin-bar-ionos-performance .ab-icon{margin:0 !important} #wp-admin-bar-ionos-performance .ab-icon:before{top:2px;margin:0;} #wp-admin-bar-ionos-performance .ab-label{margin:0 5px}</style>
		<span class="ab-aria-live-area screen-reader-text" aria-live="polite"></span>
		<?php

		$dashicon_class = 'dashicons-trash';
		if ( isset( $_GET['_ionos-performance'] ) && 'flushed' === $_GET['_ionos-performance'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$dashicon_class = self::get_dashicon_success_class();
		}

		$wp_admin_bar->add_menu(
			[
				'id'     => 'ionos-performance',
				'href'   => wp_nonce_url( add_query_arg( '_ionos-performance', 'flush' ), '_ionos_performance_flush_nonce' ), // esc_url in /wp-includes/class-wp-admin-bar.php#L438.
				'parent' => 'top-secondary',
				'title'  => '<span class="ab-icon dashicons ' . $dashicon_class . '" aria-hidden="true"></span>' .
							'<span class="ab-label">' . esc_html__( 'Flush site cache', 'ionos-performance' ) . '</span>',
				'meta'   => [
					'title' => esc_html__( 'Flush the ionos-performance cache', 'ionos-performance' ),
				],
			]
		);
	}

	/**
	 * Adds a script to query the REST endpoint and animate the flush icon in admin bar menu.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Removed unused parameter.
	 */
	public static function add_flush_icon_script() {
		if ( ! is_admin_bar_showing() || ! Helper::current_user_can_flush_cache() ) {
			return;
		}

		wp_enqueue_script( 'ionos-performance-admin-bar-flush' );

		wp_localize_script(
			'ionos-performance-admin-bar-flush',
			'ionos_performance_admin_bar_flush_ajax_object',
			[
				'url'              => esc_url_raw( rest_url( Rest::get_rest_namespace() . '/' . Rest::get_rest_route_flush() ) ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'flushing'         => esc_html__( 'Flushing cache', 'ionos-performance' ),
				'flushed'          => esc_html__( 'Cache flushed successfully', 'ionos-performance' ),
				'dashicon_success' => self::get_dashicon_success_class(),
			]
		);
	}

	/**
	 * Returns the dashicon class for the success state in admin bar flush button.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_dashicon_success_class() {
		global $wp_version;
		if ( version_compare( $wp_version, '5.2', '<' ) ) {
			return 'dashicons-yes';
		}

		return 'dashicons-yes-alt';
	}

	/**
	 * Registers the scripts.
	 *
	 * @since 1.0.0
	 */
	public static function register_scripts() {
		wp_register_script(
			'ionos-performance-admin-bar-flush',
			plugins_url( 'js/admin-bar-flush.js', FEATURE_MAIN_PLUGIN_FILE_PATH ),
			[ 'jquery' ],
			[],
			filemtime( plugin_dir_path( FEATURE_MAIN_PLUGIN_FILE_PATH ) . 'js/admin-bar-flush.js' ),
			true
		);
	}

	/**
	 * Registers the styles.
	 *
	 * @since 1.0.0
	 */
	public static function register_styles() {
		wp_register_style(
			'ionos-performance-dashboard',
			plugins_url( 'css/dashboard.css', FEATURE_MAIN_PLUGIN_FILE_PATH ),
			[],
			filemtime( plugin_dir_path( FEATURE_MAIN_PLUGIN_FILE_PATH ) . 'css/dashboard.css' )
		);

		wp_register_style(
			'ionos-performance-admin-bar-flush',
			plugins_url( 'css/admin-bar-flush.css', FEATURE_MAIN_PLUGIN_FILE_PATH ),
			[],
			filemtime( plugin_dir_path( FEATURE_MAIN_PLUGIN_FILE_PATH ) . 'css/admin-bar-flush.css' )
		);
	}

	/**
	 * Registers custom CSS.
	 *
	 * @since   1.0.0
	 *
	 * @param  string $hook  Current hook.
	 */
	public static function add_admin_resources( $hook ) {
		if ( $hook === 'index.php' ) {
			wp_enqueue_style( 'ionos-performance-dashboard' );
		}
	}

	/**
	 * Fixes admin dashboard styles.
	 *
	 * @since 1.0.0
	 */
	public static function admin_dashboard_styles() {
		$wp_version = get_bloginfo( 'version' );

		if ( version_compare( $wp_version, '5.3', '<' ) ) {
			?>
			<style>#dashboard_right_now .ionos-performance-icon use { fill: #82878c; }</style>
			<?php
		}
	}

	/**
	 * Outputs an admin notice if the permalink structure is set to simple.
	 *
	 * @since   1.0.0
	 * @return void|null
	 */
	public static function add_permalink_warning() {
		if ( ! self::is_caching_enabled() || ! empty( get_option( 'permalink_structure' ) ) ) {
			return;
		}

		add_action(
			'admin_notices',
			function () {
				global $current_screen;
				if (
					'options-permalink.php' !== $GLOBALS['pagenow']
					&& 'ionos_page_ionos_performance' !== $current_screen->base
				) {
					return;
				}
				printf(
					'<div class="notice notice-warning ionos-performance-pretty-permalinks-warning"><p><strong>%s</strong> %s</p></div>',
					esc_html__( 'Warning:', 'ionos-performance' ),
					esc_html__( 'You need to enable pretty permalinks to use the caching feature of IONOS Performance.', 'ionos-performance' )
				);
			}
		);
	}

	/**
	 * Displays helpful text for existing customers getting IONOS Performance.
	 */
	public static function add_guided_component_activation_notice() {
		global $current_screen;
		if ( 'ionos_page_ionos_performance' !== $current_screen->base ) {
			return;
		}
		if ( get_option( 'ionos_performance_show_guided_component_activation' ) ) {
			?>
			<div class="notice notice-info ionos-performance-guided-component-notice">
				<p><?php esc_html_e( 'The IONOS Performance Plugin is now available for free to all customers of our WordPress plans.', 'ionos-performance' ); ?></p>
				<p><?php esc_html_e( 'The plugin shortens the loading time of your website by caching pages that would otherwise have to be dynamically generated by WordPress on every page load. A website’s loading time is a crucial factor for your visitors and successful search engine rankings.', 'ionos-performance' ); ?></p>
				<p><?php esc_html_e( 'If you want to try caching, select “Enable caching feature” on this page and click “Save changes”.', 'ionos-performance' ); ?></p>
				<p><?php esc_html_e( 'Verify that it works correctly by logging out and viewing your site as a non-logged-in visitor. Caching is only active for non-logged-in users.', 'ionos-performance' ); ?></p>
				<p><?php esc_html_e( 'For example, if you notice problems with contact forms, you can disable caching at any time on this page.', 'ionos-performance' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Displays notice for existing customers in dashboard after installing performance.
	 */
	public static function add_existing_customer_installed_notice() {
		global $current_screen;
		if ( 'dashboard' !== $current_screen->base ) {
			return;
		}

		printf(
			'<div data-dismissible="ionos_performance_show_activation_admin_notice" class="updated notice notice-info is-dismissible ionos-performance-existing-customer-installed-notice"><p>%s</p><p><a href="%s">%s</a></p></div>',
			esc_html__( 'We have installed the new IONOS Performance plugin for you. If you don’t use a caching plugin so far, you can significantly improve the loading time of your website with a few clicks.', 'ionos-performance' ),
			esc_url( admin_url( 'admin.php?page=ionos_performance' ) ),
			esc_html__( 'Learn more', 'ionos-performance' )
		);
	}

	/**
	 * Adds cache properties to the dashboard.
	 *
	 * @since 1.0.0
	 *
	 * @param array $items Initial array with dashboard items.
	 *
	 * @return array Merged array with dashboard items.
	 */
	public static function add_dashboard_count( $items = [] ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $items;
		}

		$size = Caching::get_cache_size();

		$method = 'HDD';

		$cachesize = ( 0 === $size )
			? esc_html__( 'Empty Cache', 'ionos-performance' )
			:
			/* translators: %s: cache size */
			sprintf( esc_html__( '%s Cache', 'ionos-performance' ), size_format( $size ) );

		$items[] = sprintf(
			'<a href="%s" title="%s: %s" class="ionos-performance-glance">
            <svg class="ionos-performance-icon ionos-performance-icon--%s" aria-hidden="true" role="img">
                <use href="%s#ionos-performance-icon-%s" xlink:href="%s#ionos-performance-icon-%s">
            </svg> %s</a>',
			add_query_arg(
				[
					'page' => 'ionos_performance',
				],
				admin_url( 'admin.php' )
			),
			esc_attr( strtolower( $method ) ),
			esc_html__( 'Caching method', 'ionos-performance' ),
			esc_attr( $method ),
			plugins_url( 'images/symbols.svg', FEATURE_MAIN_PLUGIN_FILE_PATH ),
			esc_attr( strtolower( $method ) ),
			plugins_url( 'images/symbols.svg', FEATURE_MAIN_PLUGIN_FILE_PATH ),
			esc_attr( strtolower( $method ) ),
			$cachesize
		);

		return $items;
	}

	/**
	 * Displays the options page.
	 *
	 * @since 1.0.0
	 */
	public static function options_page() {
		self::$settings_builder->render();
	}

	/**
	 * Inits the standalone mode.
	 */
	public static function init_for_standalone() {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );

		add_action(
			'admin_menu',
			function () {
				Menu::add_submenu_page(
					'Performance',
					'Performance',
					'manage_options',
					'ionos_performance',
					[ __CLASS__, 'options_page' ],
					null
				);
			}
		);
	}

	/**
	 * Registers the settings when used in standalone mode.
	 */
	public static function register_settings() {
		self::$settings_builder->add_sanitize_callback( 'ionos-performance' );
	}

	/**
	 * Adds the settings page to the admin menu.
	 *
	 * @since 1.0.0
	 */
	public static function create_option_page() {
		self::$settings_builder = new SettingsBuilder( 'ionos-performance', esc_html__( 'IONOS Performance', 'ionos-performance' ), 'ionos_performance' );
		self::$settings_builder->register_assets();
		self::$settings_builder->add_tab( self::TAB_NAME, esc_html__( 'Caching', 'ionos-performance' ) );
		self::$settings_builder->add_element(
			self::TAB_NAME,
			new SectionHeader(
				[
					'heading' => esc_html__( 'Caching', 'ionos-performance' ),
				]
			)
		);
		self::$settings_builder->add_field(
			self::TAB_NAME,
			new CheckboxField(
				'ionos-performance[caching_enabled]',
				[
					'label'       => esc_html__( 'Enable caching feature', 'ionos-performance' ),
					'description' => esc_html__( 'IONOS Performance uses a cache to store HTML content generated by WordPress temporarily.', 'ionos-performance' ),
					'value'       => self::is_caching_enabled(),
					'disabled'    => Helper::has_conflicting_caching_plugins(),
				]
			)
		);
		self::$settings_builder->add_element(
			self::TAB_NAME,
			new SectionHeader(
				[
					'heading'     => esc_html__( 'Cache expiration', 'ionos-performance' ),
					'description' => esc_html__( 'IONOS Performance removes the cache files regularly. Here you can adjust the interval.', 'ionos-performance' ),
				]
			)
		);
		self::$settings_builder->add_field(
			self::TAB_NAME,
			new NumberField(
				'ionos-performance[cache_expires]',
				[
					'label'     => esc_html__( 'Expiration interval (in hours)', 'ionos-performance' ),
					'min_value' => 1,
					'value'     => isset( Manager::get_options()['cache_expires'] ) ? Manager::get_options()['cache_expires'] : 12,
				]
			)
		);
		self::$settings_builder->add_element(
			self::TAB_NAME,
			new Button(
				[
					'label'                => esc_html__( 'Flush cache now', 'ionos-performance' ),
					'href'                 => wp_nonce_url( add_query_arg( '_ionos-performance', 'flush' ), '_ionos_performance_flush_nonce' ),
					'description'          => __( 'Flush the cache by clicking the button below or the <span class="dashicons dashicons-trash"></span> icon in the admin bar.', 'ionos-performance' ),
					'kses_for_description' => [
						'span' => [
							'class' => true,
						],
					],
				]
			)
		);
		self::$settings_builder->add_element(
			self::TAB_NAME,
			new SectionHeader(
				[
					'heading'     => esc_html__( 'Cache exceptions', 'ionos-performance' ),
					'description' => esc_html__( 'Sometimes it can be useful to exclude pages or other content from being cached.', 'ionos-performance' ),
				]
			)
		);
		self::$settings_builder->add_field(
			self::TAB_NAME,
			new TextField(
				'ionos-performance[without_ids]',
				[
					'label'       => esc_html__( 'Exclude content by IDs', 'ionos-performance' ),
					'description' => esc_html__( 'Separate more than one with comma, e.g. 1, 2, 3,…', 'ionos-performance' ),
				]
			)
		);
	}

	/**
	 * Calls merge_settings_page filter to merge the settings builder.
	 */
	private static function merge_with_base() {
		apply_filters( 'ionos_performance_merge_settings_page', self::$settings_builder );
		add_filter( 'ionos-performance_options_array_sanitize_callback', [ __CLASS__, 'validate_options' ], 10, 2 );
	}

	/**
	 * Validates the options.
	 *
	 * @param array  $data Array of raw form values.
	 * @param string $tab The tab name.
	 *
	 * @return array Array of validated values.
	 */
	public static function validate_options( $data, $tab ) {
		if ( $tab !== self::TAB_NAME ) {
			if ( ! is_array( $data ) ) {
				$data = [];
			}
			return array_merge( Manager::get_feature_options(), $data );
		}

		if ( empty( $data ) ) {
			return [];
		}

		if ( isset( $data['without_ids'] ) ) {
			$data['without_ids'] = self::sanitize_post_ids( $data['without_ids'] );
		}

		return array_merge(
			$data,
			[
				'caching_enabled' => (int) ( ! empty( $data['caching_enabled'] ) ),
				'cache_expires'   => empty( $data['cache_expires'] ) ? 0 : (int) $data['cache_expires'],
			]
		);
	}

	/**
	 * Sanitizes the post IDs.
	 *
	 * @param string $post_ids The post IDs.
	 *
	 * @return string The sanitized post IDs.
	 */
	private static function sanitize_post_ids( $post_ids ) {
		if ( empty( $post_ids ) ) {
			return '';
		}

		$ids = explode( ',', $post_ids );

		foreach ( $ids as $key => $id ) {
			if ( ! is_numeric( $id ) || get_permalink( $id ) === false ) {
				unset( $ids[ $key ] );
			}
		}

		return implode( ',', $ids );
	}

	/**
	 * Checks if caching is enabled.
	 *
	 * @return bool True if caching is enabled, false otherwise.
	 */
	public static function is_caching_enabled() {
		return get_option( 'ionos-performance' ) === false
			   || ( get_option( 'ionos_performance_is_healthy_htaccess' ) == 1 && Manager::get_option( 'caching_enabled' ) === null )
			   || Manager::get_option( 'caching_enabled' );
	}

	/**
	 * Checks if the options to mark existing customers that got IONOS performance need to be removed.
	 *
	 * @param mixed $old_value The old option value.
	 * @param mixed $value The new option value.
	 */
	public static function maybe_remove_existing_customer_options( $old_value, $value ) {
		if ( empty( get_option( 'ionos_performance_show_guided_component_activation', false ) ) && empty( get_option( 'ionos_performance_show_activation_admin_notice', false ) ) ) {
			return;
		}

		if ( ! empty( $value['caching_enabled'] ) ) {
			delete_option( 'ionos_performance_show_guided_component_activation' );
			delete_option( 'ionos_performance_show_activation_admin_notice' );
		}
	}
}
