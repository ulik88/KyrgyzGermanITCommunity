<?php

namespace Ionos\Performance\Caching;

/**
 * Class for HDD based caching.
 */
final class Caching {

	/**
	 * Inits the caching.
	 */
	public static function init() {
		add_action( 'template_redirect', [ __CLASS__, 'manage_cache' ], 0 );
	}

	/**
	 * Stores an item in the caching folder.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Removed ignored parameters.
	 *
	 * @param  string $data      Content of the entry.
	 */
	public static function store_item( $data ) {
		if ( empty( $data ) ) {
			return;
		}

		FileSystem::create_files(
			$data . self::cache_signature()
		);
	}

	/**
	 * Generates the caching signature.
	 *
	 * @since 1.0.0
	 *
	 * @return string Signature string.
	 */
	private static function cache_signature() {
		return sprintf(
			"\n\n<!-- %s\n%s @ %s -->",
			'IONOS Performance | https://www.ionos.com',
			esc_html__( 'Generated', 'ionos-performance' ),
			date_i18n(
				'd.m.Y H:i:s',
				// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
				current_time( 'timestamp' )
			)
		);
	}

	/**
	 * Clears the complete cache folder.
	 *
	 * @since 1.0.0
	 */
	public static function clear_cache() {
		FileSystem::clear_dir(
			CACHE_DIR,
			true
		);
	}

	/**
	 * Prints a cached file.
	 *
	 * @since 1.0.0
	 */
	public static function print_cache() {
		$filename = FileSystem::file_html();
		if ( ! is_readable( $filename ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		$bytes_read = readfile( $filename );

		if ( ! empty( $bytes_read ) ) {
			exit;
		}
	}

	/**
	 * Gets the cache size in bytes.
	 *
	 * @since 1.0.0
	 *
	 * @return integer Cache size in bytes.
	 */
	public static function get_cache_size() {
		$size = get_transient( 'ionos_performance_cache_size' );
		if ( ! $size ) {
			$size = (int) FileSystem::dir_size( CACHE_DIR );

			// Store the information for 15 minutes.
			set_transient(
				'ionos_performance_cache_size',
				$size,
				900
			);
		}

		return $size;
	}

	/**
	 * Flushes total cache.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Removed param to clear cache for all methods.
	 */
	public static function flush_total_cache() {
		if ( did_action( 'save_post_revision' ) ) {
			return;
		}

		self::clear_cache();

		delete_transient( 'ionos_performance_cache_size' );
	}

	/**
	 * Manages the cache.
	 *
	 * @since 1.0.0
	 */
	public static function manage_cache() {
		if ( self::skip_cache() ) {
			return;
		}

		$has_cache = is_readable( FileSystem::file_html() );

		if ( ! $has_cache ) {
			ob_start( [ __CLASS__, 'set_cache' ] );

			return;
		}

		self::print_cache();
	}

	/**
	 * Checks if we need to skip caching for this request.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Renamed method.
	 *
	 * @return boolean TRUE on exclusion.
	 */
	public static function skip_cache() {
		$options = Manager::get_options();

		if ( is_user_logged_in() ) {
			return true;
		}

		if ( Helper::has_conflicting_caching_plugins() ) {
			return true;
		}

		if ( isset( $options['caching_enabled'] ) && 0 == $options['caching_enabled'] ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			return true;
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return true;
		}

		if ( isset( $_SERVER['HTTP_ACCEPT'] ) && false === strpos( $_SERVER['HTTP_ACCEPT'], 'text/html' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET ) ) {
			return true;
		}

		if ( ! get_option( 'permalink_structure' ) ) {
			return true;
		}

		$is_index = basename( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) === 'index.php';
		if ( false === $is_index ) {
			return true;
		}

		if ( Helper::has_cookie_bypass() ) {
			return true;
		}

		/**
		 * Filters if the cache should be skipped.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $skip_cache If the cache should be skipped.
		 */
		if ( apply_filters( 'ionos_performance_skip_cache', false ) ) {
			return true;
		}

		if ( is_search() || is_404() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required() ) {
			return true;
		}

		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			return true;
		}

		if ( isset( $options['without_ids'] ) && is_singular() ) {
			$without_ids = array_map( 'intval', Helper::preg_split( $options['without_ids'] ) );
			if ( in_array( $GLOBALS['wp_query']->get_queried_object_id(), $without_ids, true ) ) {
				return true;
			}
		}

		if ( get_query_var( 'sitemap' ) || get_query_var( 'sitemap-subtype' ) || get_query_var( 'sitemap-stylesheet' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Stores the data in the cache, and provides filters to fine tune.
	 *
	 * @since 1.0.0
	 *
	 * @param string $data Content of the page.
	 *
	 * @return string Content of the page.
	 */
	public static function set_cache( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		/**
		 * Filters whether the buffered data should actually be cached
		 *
		 * @since 1.0.0
		 * @since 2.0.0 Renamed filter and removed $method param.
		 *
		 * @param  bool    $should_cache   Whether the data should be cached.
		 * @param  string  $data           The actual data.
		 * @param  string  $cache_hash     The cache hash.
		 * @param  int     $cache_expires  Cache validity period.
		 */
		if ( apply_filters( 'ionos_performance_store_item', true, $data, self::cache_hash(), self::cache_expires() ) ) {
			/**
			 * Filters the buffered data itself.
			 *
			 * @since 1.0.0
			 * @since 2.0.0 Renamed and removed $method param.
			 *
			 * @param string $data          The actual data.
			 * @param string $cache_hash    The cache hash.
			 * @param int    $cache_expires Cache validity period.
			 */
			$data = apply_filters( 'ionos_performance_modify_output', $data, self::cache_hash(), self::cache_expires() );

			self::store_item( $data );
		}

		return $data;
	}

	/**
	 * Gets the cache expiry timestamp.
	 *
	 * @since 1.0.0
	 *
	 * @return integer Validity period in seconds.
	 */
	private static function cache_expires() {
		return HOUR_IN_SECONDS * Manager::get_option( 'cache_expires' );
	}

	/**
	 * Gets hash value for caching.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url URL to hash [optional].
	 *
	 * @return string Cache hash value.
	 */
	private static function cache_hash( $url = '' ) {
		$prefix = is_ssl() ? 'https-' : '';

		if ( empty( $url ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$url = wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] );
		}

		$url_parts = wp_parse_url( $url );
		$hash_key  = $prefix . $url_parts['host'] . $url_parts['path'];

		return md5( $hash_key ) . '.ionos-performance';
	}

	/**
	 * Flushes the cache.
	 *
	 * @since 1.0.0
	 */
	public static function flush_cache() {
		if ( is_multisite() && is_network_admin() ) {
			$old = $GLOBALS['wpdb']->blogid;

			$ids = self::get_blog_ids();

			foreach ( $ids as $id ) {
				switch_to_blog( $id );
				self::flush_total_cache();
			}

			switch_to_blog( $old );
		} else {
			self::flush_total_cache();
		}

		Cron::re_schedule();

		if ( ! is_admin() ) {
			wp_safe_redirect(
				remove_query_arg(
					'_ionos-performance',
					wp_get_referer()
				)
			);

			exit();
		}
	}
}
