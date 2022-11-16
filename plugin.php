<?php
/**
 * Plugin Name: cache-collector
 * Plugin URI: https://github.com/alleyinteractive/cache-collector
 * Description: Dynamic cache key collector for easy purging.
 * Version: 0.1.0
 * Author: Sean Fisher
 * Author URI: https://github.com/alleyinteractive/cache-collector
 * Requires at least: 5.9
 * Tested up to: 5.9
 *
 * @package cache-collector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cache_Collector\Cache_Collector;

require_once __DIR__ . '/src/class-cache-collector.php';

/**
 * Instantiate the plugin.
 */
function cache_collector_setup() {
	Cache_Collector::register_post_type();

	/**
	 * Filter the threshold for cache key expiration.
	 *
	 * @param int $threshold Threshold in seconds.
	 */
	Cache_Collector::$post_update_threshold = apply_filters( 'cache_collector_threshold', Cache_Collector::$post_update_threshold );

	// Register the post/term purge actions.
	add_action( 'save_post', fn ( $post_id ) => Cache_Collector::on_post_update( $post_id ) );
	add_action( 'delete_post', fn ( $post_id ) => Cache_Collector::on_post_update( $post_id ) );
	add_action( 'edit_term', fn ( $term_id ) => Cache_Collector::for_term( $term_id )->purge() );
	add_action( 'delete_term', fn ( $term_id ) => Cache_Collector::for_term( $term_id )->purge() );

	// Setup a cleanup task that runs once a day to cleanup any expired keys and
	// delete any unused options.
	if ( ! wp_next_scheduled( 'cache_collector_cleanup' ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'cache_collector_cleanup' );
	}

	add_action( 'cache_collector_cleanup', fn () => Cache_Collector::cleanup() );

	// Register the WP-CLI command.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once __DIR__ . '/src/class-cli.php';

		\WP_CLI::add_command( 'cache-collector', 'Cache_Collector\CLI' );
	}
}
cache_collector_setup();

/**
 * Register a cache key for a collection.
 *
 * @param string $collection Collection name.
 * @param string $key Cache key.
 * @param string $type Cache type.
 * @return Cache_Collector
 */
function cache_collector_register_key( string $collection, string $key, string $type = Cache_Collector::CACHE_OBJECT_CACHE ): Cache_Collector {
	return ( new Cache_Collector( $collection ) )->register( $key );
}

/**
 * Register a cache key for a post.
 *
 * @param int|\WP_Post $post Post ID or object.
 * @param string       $key Cache key.
 * @param string       $group Cache group, optional.
 * @param string       $type Cache type, optional.
 * @return Cache_Collector
 */
function cache_collector_register_post_key( \WP_Post|int $post, string $key, string $group = '', string $type = Cache_Collector::CACHE_OBJECT_CACHE ): Cache_Collector {
	return Cache_Collector::for_post( $post )->register( $key, $group, $type );
}

/**
 * Register a cache key for a term.
 *
 * @param int|\WP_Term $term Term ID or object.
 * @param string       $key Cache key.
 * @param string       $group Cache group, optional.
 * @param string       $type Cache type, optional.
 * @return Cache_Collector
 */
function cache_collector_register_term_key( \WP_Term|int $term, string $key, string $group = '', string $type = Cache_Collector::CACHE_OBJECT_CACHE ): Cache_Collector {
	return Cache_Collector::for_term( $term )->register( $key, $group, $type );
}

/**
 * Purge a collection.
 *
 * @param string $collection Collection name.
 * @return Cache_Collector
 */
function cache_collector_purge( string $collection ): Cache_Collector {
	return ( new Cache_Collector( $collection ) )->purge();
}

/**
 * Purge a post's collection.
 *
 * @param int|\WP_Post $post Post ID or object.
 * @return Cache_Collector
 */
function cache_collector_purge_post( \WP_Post|int $post ): Cache_Collector {
	return Cache_Collector::for_post( $post )->purge();
}

/**
 * Purge a term's collection.
 *
 * @param int|\WP_Term $term Term ID or object.
 * @return Cache_Collector
 */
function cache_collector_purge_term( \WP_Term|int $term ): Cache_Collector {
	return Cache_Collector::for_term( $term )->purge();
}
