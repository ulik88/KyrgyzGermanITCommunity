<?php

namespace Ionos\Performance\Caching;

use Ionos\Performance\Config;
use Ionos\PluginDetection\PluginDetection;

/**
 * Helper class.
 */
class Helper {
	/**
	 * Checks if conflicting caching plugins are in use.
	 *
	 * @return bool
	 */
	public static function has_conflicting_caching_plugins() {
		$conflicting_caching_plugins = Config::get( 'features.conflictingCachingPlugins' );
		if ( ! empty( $conflicting_caching_plugins ) ) {
			return PluginDetection::has_active( $conflicting_caching_plugins );
		}

		return false;
	}

	/**
	 * Checks an option and returns if the current user can flush the cache.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function current_user_can_flush_cache() {
		/**
		 * Filters if the user can flush the cache.
		 *
		 * @since 1.0.0
		 * @since 2.0.0 Renamed filter.
		 *
		 * @param string $user_can_flush_cache If the user should be able to flush the cache.
		 */
		return (bool) apply_filters( 'ionos_performance_user_can_flush_cache', current_user_can( 'manage_options' ) );
	}

	/**
	 * Splits a string by comma and returns the parts as an array. Empty parts will be omitted.
	 *
	 * @since   1.0.0
	 *
	 * @param  string $input String to split.
	 *
	 * @return  array        Splitted values.
	 */
	public static function preg_split( $input ) {
		return (array) preg_split( '/,/', $input, - 1, PREG_SPLIT_NO_EMPTY );
	}

	/**
	 * Checks cookie, if user is logged in or author, etc.
	 *
	 * @since   1.0.0
	 *
	 * @return  boolean  $diff  TRUE on "marked" users
	 */
	public static function has_cookie_bypass() {
		if ( empty( $_COOKIE ) ) {
			return false;
		}

		foreach ( $_COOKIE as $k => $v ) {
			if ( preg_match( '/^(wp-postpass|wordpress_logged_in|comment_author)_/', $k ) ) {
				return true;
			}
		}

		return false;
	}
}
