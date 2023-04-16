<?php

namespace Ionos\Performance\Caching;

use WP_REST_Server;

/**
 * Class Rest.
 */
class Rest {
	const REST_NAMESPACE   = 'ionos-performance/v1';
	const REST_ROUTE_FLUSH = 'flush';

	/**
	 * Inits the required actions.
	 *
	 * @since 2.0.0
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'add_flush_rest_endpoint' ] );
	}

	/**
	 * Registers an REST endpoint for the flush operation.
	 *
	 * @since 2.0.0
	 */
	public static function add_flush_rest_endpoint() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE_FLUSH,
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [
					Caching::class,
					'flush_cache',
				],
				'permission_callback' => [
					Helper::class,
					'current_user_can_flush_cache',
				],
			]
		);
	}

	/**
	 * Gets the rest namespace.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function get_rest_namespace() {
		return self::REST_NAMESPACE;
	}

	/**
	 * Gets the rest route for flushing the cache.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function get_rest_route_flush() {
		return self::REST_ROUTE_FLUSH;
	}
}
