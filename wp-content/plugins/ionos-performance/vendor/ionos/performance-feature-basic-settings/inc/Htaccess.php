<?php

namespace Ionos\Performance\BasicSettings;

use Ionos\Performance\Config;
use Ionos\HtaccessHandler\HtaccessHandler;

/**
 * Class for handling the .htaccess markers.
 */
class Htaccess {
	/**
	 * The name of the option that stores the state of the .htaccess file.
	 *
	 * @var string
	 */
	const HEALTHY_HTACCESS_OPTION_NAME = 'ionos_performance_is_healthy_htaccess';

	/**
	 * HtaccessHandler instance used for .htaccess changes.
	 *
	 * @var HtaccessHandler $htaccess
	 */
	private $htaccess;

	/**
	 * Available .htaccess snippets.
	 *
	 * @var array
	 */
	const HTACCESS_MARKERS = [
		'directory_index'  => [
			'wrapperMarker' => 'IONOS Performance Directory Index',
			'versionMarker' => 'IONOS Directory Index',
			'version'       => 'v1',
			'templatePath'  => FEATURE_MAIN_DIR_PATH . '/templates/template-htaccess-directory-index-snippet.tpl',
		],
		'cache_expiration' => [
			'wrapperMarker' => 'IONOS Performance Cache Expiration',
			'versionMarker' => 'IONOS Cache Expiration',
			'version'       => 'v1',
			'templatePath'  => FEATURE_MAIN_DIR_PATH . '/templates/template-htaccess-cache-expiration-snippet.tpl',
		],
		'deflate'          => [
			'wrapperMarker' => 'IONOS Performance Deflate',
			'versionMarker' => 'IONOS Deflate',
			'version'       => 'v1',
			'templatePath'  => FEATURE_MAIN_DIR_PATH . '/templates/template-htaccess-deflate-snippet.tpl',
		],
	];

	/**
	 * Checksums of healthy .htaccess snippets.
	 *
	 * @var array
	 */
	const HEALTHY_HTACCESS_CONTENTS = [
		'wordpress_with_permalinks'                  => [
			'checksum' => '2e41169cee4a2bc99cc48c845ce587b0beb5fc09',
		],
		'wordpress_with_permalinks_and_module_check' => [
			'checksum' => 'a32fba8a5dbaef1abe1c381103e61a91e4ea5d93',
		],
		'wordpress_without_permalinks'               => [
			'checksum' => 'ab043d23eca357fc9bc61cc845f54a09db5f89bc',
		],
		'aps_package_without_modifications'          => [
			'checksum'        => '313da2b115207bf6d8eb6a0c16d1e6df7473191d',
			'enabled_options' => [
				'deflate',
				'cache_expiration',
				'directory_index',
			],
			'base_template'   => FEATURE_MAIN_DIR_PATH . '/templates/template-htaccess-aps-default-base.tpl',
		],
		'aps_package_without_modifications_20230119' => [
			'checksum'        => 'b135449c4d64ca190c53301532b78f9da43e7e46',
			'enabled_options' => [
				'deflate',
				'cache_expiration',
				'directory_index',
			],
			'base_template'   => FEATURE_MAIN_DIR_PATH . '/templates/template-htaccess-aps-default-base.tpl',
		],
		'aps_package_without_modifications_no_ssl'   => [
			'checksum'        => 'ad36633fe28def5c278eb3135e115c09f756025e',
			'enabled_options' => [
				'deflate',
				'cache_expiration',
				'directory_index',
			],
			'base_template'   => FEATURE_MAIN_DIR_PATH . '/templates/template-htaccess-aps-default-base-no-ssl.tpl',
		],
		'waas_default'                               => [
			'checksum'        => 'bea64df68edb2283ca87a166daf6b1476bb4bfa1',
			'enabled_options' => [
				'deflate',
				'cache_expiration',
				'directory_index',
			],
			'base_template'   => FEATURE_MAIN_DIR_PATH . '/templates/template-htaccess-waas-default-base.tpl',
		],
		'waas_default_20220113'                               => [
			'checksum'        => '2b47ee2ad0ef57d0a47ccd4462123bb1cf252fb8',
			'enabled_options' => [
				'deflate',
				'cache_expiration',
				'directory_index',
			],
			'base_template'   => FEATURE_MAIN_DIR_PATH . '/templates/template-htaccess-waas-default-base.tpl',
		],
	];

	/**
	 * Constructs the HtaccessHandler.
	 */
	public function __construct() {
		$this->htaccess = new HtaccessHandler( self::HTACCESS_MARKERS );
	}

	/**
	 * Migrates the .htaccess data if it is not up-to-date.
	 */
	public function maybe_migrate() {
		if ( ! Config::get( 'features.basicSettings.enabled' ) ) {
			return;
		}

		if ( get_option( self::HEALTHY_HTACCESS_OPTION_NAME ) ) {
			return;
		}

		$healthy_htaccess_type = $this->scan_htaccess();
		if ( 'none' === $healthy_htaccess_type ) {
			return;
		}

		update_option( self::HEALTHY_HTACCESS_OPTION_NAME, 1 );

		$healthy_htaccess_data = self::HEALTHY_HTACCESS_CONTENTS[ $healthy_htaccess_type ];
		if ( isset( $healthy_htaccess_data['base_template'] ) && @is_readable( $healthy_htaccess_data['base_template'] ) ) {
			$this->htaccess->write( file_get_contents( $healthy_htaccess_data['base_template'] ) );
		}

		if ( isset( $healthy_htaccess_data['enabled_options'] ) ) {
			$options_to_set = [];
			foreach ( $healthy_htaccess_data['enabled_options'] as $enabled_option ) {
				$options_to_set[ "basic_$enabled_option" ] = 1;
			}
			Manager::set_options( $options_to_set );

			wp_safe_remote_get( home_url( '/?no_cache' ) );
			$this->htaccess = new HtaccessHandler( self::HTACCESS_MARKERS );
		}
	}

	/**
	 * Compares .htaccess with known healthy files.
	 *
	 * @return string
	 */
	private function scan_htaccess() {
		$cleaned_htaccess = preg_replace( '/# START IONOS.*(.|\n)*?# END IONOS.*\n*/', '', $this->htaccess->get_content() );
		$cleaned_htaccess = preg_replace( '/^#.*\n/m', '', $cleaned_htaccess );

		$htaccess_hash = sha1( trim( $cleaned_htaccess ) );

		foreach ( self::HEALTHY_HTACCESS_CONTENTS as $htaccess_type => $htaccess_data ) {
			if ( $htaccess_hash === $htaccess_data['checksum'] ) {
				return $htaccess_type;
			}
		}

		return 'none';
	}

	/**
	 * Adds or removes snippet from .htaccess if necessary.
	 */
	public function maybe_update() {
		if ( $this->should_remove_snippet() ) {
			$this->remove_snippets();
			return;
		}

		if ( did_action( 'deactivate_' . BASENAME ) ) {
			return;
		}

		if ( $this->should_insert_snippet() ) {
			$this->insert_snippets();
		}

		$this->htaccess->maybe_update_snippets();
	}

	/**
	 * Checks if snippets should be removed.
	 *
	 * @return bool
	 */
	private function should_remove_snippet() {
		if ( empty( Config::get( 'features.basicSettings.enabled' ) ) ) {
			return true;
		}

		if ( did_action( 'deactivate_' . BASENAME ) > 0 ) {
			return true;
		}

		foreach ( self::HTACCESS_MARKERS as $option_name => $marker ) {
			if ( ! Manager::get_option( "basic_$option_name" ) && $this->htaccess->has_snippet( $option_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if snippets should be inserted.
	 *
	 * @return bool
	 */
	private function should_insert_snippet() {
		if ( empty( Config::get( 'features.basicSettings.enabled' ) ) ) {
			return false;
		}

		foreach ( self::HTACCESS_MARKERS as $option_name => $marker ) {
			if ( Manager::get_option( "basic_$option_name" ) && ! $this->htaccess->has_snippet( $option_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Inserts the snippets into the .htaccess-file.
	 */
	public function insert_snippets() {
		foreach ( self::HTACCESS_MARKERS as $key => $value ) {
			if ( Manager::get_option( "basic_$key" ) ) {
				$this->htaccess->insert_snippet( $key );
			}
		}
	}

	/**
	 * Removes the snippets from the .htaccess-file.
	 *
	 * @param bool $force If snippets should be removed even if option is active.
	 */
	public function remove_snippets( $force = false ) {
		foreach ( self::HTACCESS_MARKERS as $key => $value ) {
			if ( ! Manager::get_option( "basic_$key" ) || $force ) {
				$this->htaccess->remove_snippet( $key );
			}
		}
	}

	/**
	 * Callback used for plugin activation.
	 */
	public function handle_activation() {
		$this->maybe_migrate();
		$this->maybe_update();
	}

	/**
	 * Cleans up on plugin deactivation.
	 */
	public function handle_deactivation() {
		$should_remove_snippet = $this->should_remove_snippet();
		if ( $should_remove_snippet ) {
			$this->remove_snippets( true );
		}
	}

	/**
	 * Getter for the healthy .htaccess option name const.
	 *
	 * @return string
	 */
	public static function get_healthy_htaccess_option_name() {
		return self::HEALTHY_HTACCESS_OPTION_NAME;
	}

	/**
	 * Update .htaccess after option change.
	 */
	public function handle_option_changes() {
		// Directly update .htaccess (if necessary) after performance option change.
		add_action( 'update_option_ionos-performance', [ $this, 'maybe_update' ] );
		add_action( 'add_option_ionos-performance', [ $this, 'maybe_update' ] );
	}
}
