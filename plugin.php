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

namespace Cache_Collector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/class-cache-collector.php';

/**
 * Instantiate the plugin.
 */
function main() {
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
}
main();
