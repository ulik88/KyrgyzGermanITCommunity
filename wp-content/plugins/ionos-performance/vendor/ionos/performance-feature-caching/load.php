<?php

namespace Ionos\Performance\Caching;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

use Ionos\Performance\Config;
use Ionos\Performance\Options;
use Ionos\Performance\Updater;
use Ionos\Performance\Warning;
use Exception;
use Ionos\HtaccessHandler\HtaccessHandler;
use Ionos\PluginStateHookHandler\PluginState;

Options::set_tenant_and_plugin_name( 'ionos', 'performance' );

const FEATURE_MAIN_PLUGIN_FILE_PATH = __DIR__ . '/caching.php';
const CACHE_DIR                     = WP_CONTENT_DIR . '/cache/ionos-performance';

const FEATURE_MAIN_DIR = __DIR__;
try {
	define( __NAMESPACE__ . '\MAIN_PLUGIN_FILE_PATH', Options::get_main_plugin_file_path( __DIR__ . '/caching.php' ) );
	define( __NAMESPACE__ . '\BASENAME', Options::get_main_plugin_file_basename( __DIR__ . '/caching.php' ) );
} catch ( Exception $e ) {
	wp_die( $e->getMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

( new PluginState( MAIN_PLUGIN_FILE_PATH ) )
	->register_cleanup_hooks()
	->remove_options_on_uninstall(
		[
			'ionos-performance',
			'ionos_performance_show_guided_component_activation',
			'ionos_performance_show_activation_admin_notice',
		]
	);

Options::clean_up( MAIN_PLUGIN_FILE_PATH );

/**
 * Init plugin.
 *
 * @return void
 */
function init() {
	new Updater();
	new Warning( 'ionos-performance' );

	if ( ! Config::get( 'features.caching.enabled' ) ) {
		return;
	}

	if ( ! wp_next_scheduled( 'ionos_performance_health_check_cron' ) && defined( __NAMESPACE__ . '\STANDALONE_PLUGIN' ) ) {
		wp_schedule_event( time(), 'twicedaily', 'ionos_performance_health_check_cron' );
	}

	$htaccess = new Htaccess();

	$htaccess->handle_option_changes();

	if ( Helper::has_conflicting_caching_plugins() ) {
		add_action(
			'admin_notices',
			function() {
				global $current_screen;
				if ( 'ionos_page_ionos_performance' !== $current_screen->base ) {
					return;
				}

				$message = esc_html__( 'IONOS Performance is not compatible with other caching plugins.', 'ionos-performance' );
				if ( Gui::is_caching_enabled() ) {
					$message = esc_html__( 'IONOS Performance has been disabled because it conflicts with another caching plugin.', 'ionos-performance' );
				}

				printf(
					'<div class="notice notice-info"><p>%s</p></div>',
					$message // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}
		);
	}

	$manager = new Manager();
	$manager->init();

	if ( is_admin() ) {
		$manager->admin_init();
	}

	if ( ! is_admin() ) {
		$manager->frontend_init();
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

/**
 * Check if the .htaccess needs to be updated and if we should flush the rewrite rules.
 */
function health_check_cron() {
	if ( Config::get( 'features.caching.maybeFlushRewriteRules' ) ) {
		// Needed to make the `save_mod_rewrite_rules()` function available that is called in WP_Rewrite::flush_rules().
		require_once ABSPATH . '/wp-admin/includes/misc.php';
		HtaccessHandler::flush_rewrite_rules_if_wp_htaccess_snippet_missing();
	}

	( new Htaccess() )->maybe_update();
}
add_action( 'ionos_performance_health_check_cron', __NAMESPACE__ . '\\health_check_cron' );

/**
 * Plugin activation routine
 *
 * @return void
 */
function activate() {
	$htaccess = new Htaccess();
	$htaccess->handle_activation();
}
register_activation_hook( MAIN_PLUGIN_FILE_PATH, __NAMESPACE__ . '\\activate' );

/**
 * Plugin deactivation routine
 *
 * @return void
 */
function deactivate() {
	$htaccess = new Htaccess();
	$htaccess->handle_deactivation();

	$timestamp = wp_next_scheduled( 'ionos_performance_health_check_cron' );
	wp_unschedule_event( $timestamp, 'ionos_performance_health_check_cron' );
}
register_deactivation_hook( MAIN_PLUGIN_FILE_PATH, __NAMESPACE__ . '\\deactivate' );

/**
 * Plugin translation.
 *
 * @return void
 */
function load_textdomain() {
	if ( ! defined( __NAMESPACE__ . '\STANDALONE_PLUGIN' ) ) {
		return;
	}

	load_plugin_textdomain(
		'ionos-performance',
		false,
		\dirname( \plugin_basename( __FILE__ ) ) . '/languages/'
	);
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_textdomain', 5 );
