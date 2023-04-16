<?php

namespace Ionos\Performance\BasicSettings;

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

const FEATURE_MAIN_DIR_PATH = __DIR__;

try {
	define( __NAMESPACE__ . '\MAIN_PLUGIN_FILE_PATH', Options::get_main_plugin_file_path( __DIR__ . '/basic-settings.php' ) );
	define( __NAMESPACE__ . '\BASENAME', Options::get_main_plugin_file_basename( __DIR__ . '/basic-settings.php' ) );
} catch ( Exception $e ) {
	wp_die( $e->getMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

( new PluginState( MAIN_PLUGIN_FILE_PATH ) )
	->register_cleanup_hooks()
	->remove_options_on_uninstall( [ 'ionos-performance', Htaccess::get_healthy_htaccess_option_name() ] );

Options::clean_up( MAIN_PLUGIN_FILE_PATH );

/**
 * Inits the plugin.
 */
function init() {
	new Updater();
	new Warning( 'ionos-performance' );

	if ( ! Config::get( 'features.basicSettings.enabled' ) ) {
		return;
	}

	$htaccess = new Htaccess();

	$htaccess->handle_option_changes();

	if ( ! wp_next_scheduled( 'ionos_performance_health_check_cron' ) && defined( __NAMESPACE__ . '\STANDALONE_PLUGIN' ) ) {
		wp_schedule_event( time(), 'twicedaily', 'ionos_performance_health_check_cron' );
	}

	$manager  = new Manager();
	$settings = new Settings();

	if ( is_admin() ) {
		$manager->admin_init();

		if ( defined( __NAMESPACE__ . '\STANDALONE_PLUGIN' ) ) {
			$settings->init_for_standalone();
		}
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );

/**
 * Check if the .htaccess needs to be updated and if we should flush the rewrite rules.
 */
function health_check_cron() {
	if ( Config::get( 'features.basicSettings.maybeFlushRewriteRules' ) ) {
		// Needed to make the `save_mod_rewrite_rules()` function available that is called in WP_Rewrite::flush_rules().
		require_once ABSPATH . '/wp-admin/includes/misc.php';
		HtaccessHandler::flush_rewrite_rules_if_wp_htaccess_snippet_missing();
	}

	$htaccess = new Htaccess();
	$htaccess->maybe_migrate();
	$htaccess->maybe_update();
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
register_activation_hook( MAIN_PLUGIN_FILE_PATH, __NAMESPACE__ . '\activate' );

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
register_deactivation_hook( MAIN_PLUGIN_FILE_PATH, __NAMESPACE__ . '\deactivate' );

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
		__DIR__ . '/languages/'
	);
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\load_textdomain', 5 );
