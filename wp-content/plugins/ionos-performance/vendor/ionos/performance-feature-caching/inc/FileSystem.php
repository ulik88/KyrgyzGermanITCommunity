<?php

namespace Ionos\Performance\Caching;

/**
 * Class FileSystem handling.
 */
class FileSystem {
	/**
	 * Creates the file that will be delivered as cached result.
	 *
	 * @since 1.0.0
	 *
	 * @param string $data Cache content.
	 */
	public static function create_files( $data ) {
		$file_path = self::file_path();

		if ( ! wp_mkdir_p( $file_path ) ) {
			return;
		}

		self::create_file( self::file_html( $file_path ), $data );
		self::create_file( self::file_gzip( $file_path ), gzencode( $data, 9 ) );
	}

	/**
	 * Creates cache file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file Path to cache file.
	 * @param string $data Cache content.
	 */
	private static function create_file( $file, $data ) {
		$handle = @fopen( $file, 'wb' );
		if ( ! $handle ) {
			return;
		}

		@fwrite( $handle, $data );
		fclose( $handle );
		clearstatcache();

		$stat  = @stat( dirname( $file ) );
		$perms = $stat['mode'] & 0007777;
		$perms = $perms & 0000666;
		@chmod( $file, $perms );
		clearstatcache();
	}


	/**
	 * Gets the path to cache file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Optional. Request URI or permalink. Default null.
	 *
	 * @return string Path to cache file
	 */
	public static function file_path( $path = null ) {
		$prefix = is_ssl() ? 'https-' : '';

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$path_parts = wp_parse_url( $path ? $path : wp_unslash( $_SERVER['REQUEST_URI'] ) );

		$path = sprintf(
			'%s%s%s%s%s',
			CACHE_DIR,
			DIRECTORY_SEPARATOR,
			$prefix,
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			strtolower( wp_unslash( $_SERVER['HTTP_HOST'] ) ),
			$path_parts['path']
		);

		if ( validate_file( $path ) > 0 ) {
			wp_die( 'Invalid file path.' );
		}

		return trailingslashit( $path );
	}

	/**
	 * Gets the path to the GZIP cache file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Optional. File path. Default empty string.
	 *
	 * @return string Path to GZIP file.
	 */
	private static function file_gzip( $file_path = '' ) {
		return ( empty( $file_path ) ? self::file_path() : $file_path ) . 'index.html.gz';
	}

	/**
	 * Gets path to HTML cache file.
	 *
	 * @since   1.0.0
	 *
	 * @param string $file_path Optional. File path. Default empty string.
	 *
	 * @return  string Path to HTML file
	 */
	public static function file_html( $file_path = '' ) {
		return ( empty( $file_path ) ? self::file_path() : $file_path ) . 'index.html';
	}


	/**
	 * Gets the directory size.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir Default. Directory path. Default '.'.
	 *
	 * @return mixed Directory size in bytes.
	 */
	public static function dir_size( $dir = '.' ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$objects = array_diff(
			scandir( $dir ),
			[ '..', '.' ]
		);

		if ( empty( $objects ) ) {
			return;
		}

		$size = 0;

		foreach ( $objects as $object ) {
			$object = $dir . DIRECTORY_SEPARATOR . $object;

			if ( is_dir( $object ) ) {
				$size += self::dir_size( $object );
			} else {
				$size += filesize( $object );
			}
		}

		return $size;
	}

	/**
	 * Clear the caching directory.
	 *
	 * @since   1.0.0
	 *
	 * @param string  $dir       Directory path.
	 * @param boolean $recursive Optional. Set to true for clearing subdirectories as well. Default false.
	 */
	public static function clear_dir( $dir, $recursive = false ) {
		$dir = untrailingslashit( $dir );

		if ( ! is_dir( $dir ) ) {
			return;
		}

		$objects = array_diff(
			scandir( $dir ),
			[ '..', '.' ]
		);

		if ( empty( $objects ) ) {
			return;
		}

		foreach ( $objects as $object ) {
			$object = $dir . DIRECTORY_SEPARATOR . $object;

			if ( is_dir( $object ) && $recursive ) {
				self::clear_dir( $object, $recursive );
			} else {
				if ( self::is_deletable( $object ) ) {
					unlink( $object );
				}
			}
		}

		if ( $recursive ) {
			if ( self::is_deletable( $dir ) && 0 === count( glob( trailingslashit( $dir ) . '*' ) ) ) {
				@rmdir( $dir );
			}
		}

		clearstatcache();
	}

	/**
	 * Checks if the file is deletable.
	 *
	 * Does it exist and is it on the right location.
	 *
	 * @since @TODO
	 *
	 * @param string $file The file name.
	 *
	 * @return bool
	 */
	private static function is_deletable( $file ) {
		if ( ! is_file( $file ) && ! is_dir( $file ) ) {
			return false;
		}

		if ( 0 !== strpos( $file, CACHE_DIR ) ) {
			return false;
		}

		// If it’s just a single blog, the user has the right to delete this file.
		// But also, if you are in the network admin, you should be able to delete all files.
		if ( ! is_multisite() || is_network_admin() ) {
			return true;
		}

		if ( is_dir( $file ) ) {
			$file = trailingslashit( $file );
		}

		$ssl_prefix   = is_ssl() ? 'https-' : '';
		$current_blog = get_blog_details( get_current_blog_id() );
		$blog_path    = CACHE_DIR . DIRECTORY_SEPARATOR . $ssl_prefix . $current_blog->domain . $current_blog->path;

		if ( 0 !== strpos( $file, $blog_path ) ) {
			return false;
		}

		// We are on a subdirectory installation and the current blog is in a subdirectory.
		if ( '/' !== $current_blog->path ) {
			return true;
		}

		// If we are on the root blog in a subdirectory multisite, we check if the current dir is the root dir.
		$root_site_dir = CACHE_DIR . DIRECTORY_SEPARATOR . $ssl_prefix . DOMAIN_CURRENT_SITE . DIRECTORY_SEPARATOR;
		if ( $root_site_dir === $file ) {
			return false;
		}

		// If we are on the root blog in a subdirectory multisite, we check, if the current file is part of another blog.
		global $wpdb;
		$results = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT path FROM ' . $wpdb->base_prefix . 'blogs WHERE domain = %s && blog_id != %d',
				$current_blog->domain,
				$current_blog->blog_id
			)
		);
		foreach ( $results as $site ) {
			$forbidden_path = CACHE_DIR . DIRECTORY_SEPARATOR . $ssl_prefix . $current_blog->domain . $site;
			if ( 0 === strpos( $file, $forbidden_path ) ) {
				return false;
			}
		}

		return true;
	}
}
