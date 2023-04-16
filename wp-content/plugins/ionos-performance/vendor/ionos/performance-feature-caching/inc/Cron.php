<?php

namespace Ionos\Performance\Caching;

/**
 * Class for cron jobs.
 */
class Cron {

	const CLEAR_CACHE_CRON_NAME = 'ionos_performance_clear_cache';

	/**
	 * Initializes CronJobs.
	 *
	 * @since 2.0.0
	 */
	public static function init() {
		// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_cache_expiration' ] );
		add_action( self::CLEAR_CACHE_CRON_NAME, [ __CLASS__, 'run_cache_cron' ] );

		self::schedule();
	}

	/**
	 * Schedules the cron job if missing.
	 *
	 * @since 2.0.0
	 */
	public static function schedule() {
		$timestamp = wp_next_scheduled( self::CLEAR_CACHE_CRON_NAME );
		if ( false === $timestamp ) {
			wp_schedule_event( time(), 'ionos_performance_cache_expire', self::CLEAR_CACHE_CRON_NAME );
		}
	}

	/**
	 * Reschedules the cron job.
	 *
	 * @since 2.0.0
	 */
	public static function re_schedule() {
		$timestamp = wp_next_scheduled( self::CLEAR_CACHE_CRON_NAME );
		if ( false !== $timestamp ) {
			wp_reschedule_event( $timestamp, 'ionos_performance_cache_expire', self::CLEAR_CACHE_CRON_NAME );
			wp_unschedule_event( $timestamp, self::CLEAR_CACHE_CRON_NAME );
		}
	}

	/**
	 * Removes the cron job.
	 *
	 * @since 2.0.0
	 */
	public static function un_schedule() {
		$timestamp = wp_next_scheduled( self::CLEAR_CACHE_CRON_NAME );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CLEAR_CACHE_CRON_NAME );
		}
	}

	/**
	 * Add cache expiration cron schedule.
	 *
	 * @since 2.0.0
	 *
	 * @param  array $schedules  Array of previously added non-default schedules.
	 *
	 * @return array Array of non-default schedules with our tasks added.
	 */
	public static function add_cron_cache_expiration( $schedules ) {
		$expires                                     = isset( Manager::get_options()['cache_expires'] ) ? Manager::get_options()['cache_expires'] : 12;
		$schedules['ionos_performance_cache_expire'] = [
			'interval' => $expires * 3600,
			'display'  => esc_html__( 'Ionos-Performance expire', 'ionos-performance' ),
		];

		return $schedules;
	}

	/**
	 * Cache expiration cron action.
	 *
	 * @since 2.0.0
	 */
	public static function run_cache_cron() {
		Caching::clear_cache();
	}
}
