<?php
/**
 * CLI class file
 *
 * @package cache-collector
 */

namespace Cache_Collector;

/**
 * CLI Command for the plugin.
 */
class CLI {
	/**
	 * Purge a cache for a specific collection.
	 *
	 * ## OPTIONS
	 *
	 * <collection>
	 * : The name of the collection to purge.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function purge( $args, $assoc_args ) {
		[ $collection ] = $args;

		$instance = new Cache_Collector( $collection, function_exists( 'ai_logger' ) ? ai_logger() : null );

		$instance->purge();
	}
}
