<?php

namespace Ionos\Performance\Caching;

use Ionos\Performance\Config;
use Ionos\HtaccessHandler\HtaccessHandler;

/**
 * Class Htaccess.
 */
class Htaccess {
	/**
	 * The htaccess handler instance.
	 *
	 * @var HtaccessHandler $htaccess
	 */
	private $htaccess;

	const HTACCESS_MARKER_KEY = 'caching';
	const HTACCESS_MARKER     = [
		'wrapperMarker' => 'IONOS Performance Caching',
		'versionMarker' => 'IONOS Caching Snippet',
		'version'       => 'v2',
		'templatePath'  => FEATURE_MAIN_DIR . '/templates/template-htaccess-caching-snippet.tpl',
		'replacements'  => [
			'{{IONOS_PERFORMANCE_CACHE_DIR}}' => CACHE_DIR,
		],
		'migrations'    => [
			'wrapperMarker' => [
				'IONOS_Performance',
			],
			'versionMarker' => [
				'IONOS_Performance Version:',
			],
		],
	];

	/**
	 * Constructs the Htaccess handler.
	 */
	public function __construct() {
		$this->htaccess = new HtaccessHandler( [ self::HTACCESS_MARKER_KEY => self::HTACCESS_MARKER ] );
	}

	/**
	 * Adds or removes snippet from .htaccess if necessary.
	 */
	public function maybe_update() {
		if ( true === $this->should_remove_snippet() ) {
			$this->htaccess->remove_snippet( self::HTACCESS_MARKER_KEY );
			Caching::clear_cache();
			return;
		}

		if ( did_action( 'deactivate_' . BASENAME ) > 0 ) {
			return;
		}

		if ( false === $this->is_feature_enabled() ) {
			return;
		}

		if ( true === $this->should_insert_snippet() ) {
			$this->htaccess->insert_snippet( self::HTACCESS_MARKER_KEY );
			return;
		}

		if ( false === Helper::has_conflicting_caching_plugins() ) {
			$this->htaccess->maybe_update_snippets();
		}
	}

	/**
	 * Checks if snippets should be removed.
	 *
	 * @return bool
	 */
	private function should_remove_snippet() {
		if ( false === $this->htaccess->has_snippet( self::HTACCESS_MARKER_KEY ) ) {
			return false;
		}

		if ( false === $this->is_feature_enabled() ) {
			return true;
		}

		if ( did_action( 'deactivate_' . BASENAME ) > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if snippets should be inserted.
	 *
	 * @return bool
	 */
	private function should_insert_snippet() {
		if ( true === $this->htaccess->has_snippet( self::HTACCESS_MARKER_KEY ) ) {
			return false;
		}

		return $this->is_feature_enabled();
	}

	/**
	 * Checks if .htaccess needs to be updated after option changes.
	 *
	 * @return void
	 */
	public function handle_option_changes() {
		// If the caching feature is disabled because of another active caching plugin, the .htaccess snippet
		// is removed, so we need to check if we have to add it again after a plugin has been deactivated.
		add_action( 'update_option_active_plugins', [ $this, 'maybe_update' ] );

		add_action( 'update_option_permalink_structure', [ $this, 'maybe_update' ] );

		// Directly update .htaccess (if necessary) after performance option change.
		add_action( 'update_option_ionos-performance', [ $this, 'maybe_update' ] );
		add_action( 'add_option_ionos-performance', [ $this, 'maybe_update' ] );
	}

	/**
	 * Calls update function on plugin activation.
	 */
	public function handle_activation() {
		$this->maybe_update();
	}

	/**
	 * Cleans up on plugin deactivation.
	 */
	public function handle_deactivation() {
		if ( true === $this->should_remove_snippet() ) {
			$this->htaccess->remove_snippet( self::HTACCESS_MARKER_KEY );
		}
	}

	/**
	 * Checks if htaccess feature is enabled.
	 *
	 * @return bool
	 */
	public function is_feature_enabled() {
		if ( false === Config::get( 'features.caching.enabled' ) ) {
			return false;
		}

		if ( ! get_option( 'permalink_structure' ) ) {
			return false;
		}

		if ( true === Helper::has_conflicting_caching_plugins() ) {
			return false;
		}

		if ( ! Gui::is_caching_enabled() ) {
			return false;
		}

		return true;
	}
}
