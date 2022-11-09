<?php
/**
 * Cache_Collector class file
 *
 * @package cache-collector
 */

namespace Cache_Collector;

use WP_Post;
use WP_Term;

/**
 * Cache Collector
 */
class Cache_Collector {
	/**
	 * Cache type for the object cache.
	 *
	 * @var string
	 */
	public const CACHE_OBJECT_CACHE = 'cache';

	/**
	 * Cache type for the transient cache.
	 *
	 * @var string
	 */
	public const CACHE_TRANSIENT = 'transient';

	/**
	 * Delimiter for the cache key.
	 *
	 * @var string
	 */
	public const DELIMITER = '_:_';

	/**
	 * Threshold in seconds for purging the cache related to a post when a post
	 * is updated.
	 *
	 * Defaults to 5 days.
	 *
	 * @var int
	 */
	public static int $threshold = 432000;

	/**
	 * Keys to be registered with the collector.
	 *
	 * @var string[]
	 */
	protected array $pending_keys = [];

	/**
	 * Create a new Cache_Collector instance for a post.
	 *
	 * @param WP_Post|int $post Post object/ID.
	 * @param array       ...$args Arguments to pass to the constructor.
	 * @return static
	 */
	public static function for_post( WP_Post|int $post, array ...$args ): static {
		if ( is_int( $post ) ) {
			return new static( "post-{$post}", ...$args );
		} else {
			return new static( "post-{$post->ID}", ...$args );
		}
	}

	/**
	 * Create a new Cache_Collector instance for a term.
	 *
	 * @param WP_Term|int $term Term object/ID.
	 * @param array       ...$args Arguments to pass to the constructor.
	 * @return static
	 */
	public static function for_term( WP_Term|int $term, array ...$args ) {
		if ( is_int( $term ) ) {
			return new static( "term-{$term}", ...$args );
		} else {
			return new static( "term-{$term->term_id}", ...$args );
		}
	}

	/**
	 * Constructor.
	 *
	 * @param string $collection Cache collection to attach to.
	 */
	public function __construct( public string $collection ) {
	}

	/**
	 * Save the collector on destruct.
	 */
	public function __destruct() {
		if ( ! empty( $this->pending_keys ) ) {
			$this->save();
		}
	}

	/**
	 * Register a cache key.
	 *
	 * @param string $key   Cache key to register.
	 * @param string $group Cache group to use, optional.
	 * @param string $type  Type of cache, optional (cache/transient).
	 * @return static
	 */
	public function register( string $key, string $group = '', string $type = self::CACHE_OBJECT_CACHE ) {
		$this->pending_keys[ "{$key}-{$group}" ] = [ $key, $group, $type ];

		return $this;
	}

	/**
	 * Save the pending registered keys.
	 *
	 * @return static
	 */
	public function save() {
		$keys = $this->keys();

		foreach ( $keys as $index => $data ) {
			[ $key, $cache_group ] = explode( static::DELIMITER, $index );
			[ $expiration ]        = $data;

			// Check if the key is expired and should be removed.
			if ( $expiration && $expiration < time() ) {
				unset( $keys[ $index ] );
				continue;
			}
		}

		// Append the pending keys to the existing keys.
		foreach ( $this->pending_keys as $key ) {
			[ $key, $cache_group, $type ] = $key;

			// Check if the key is already registered.
			if ( isset( $keys[ $key . static::DELIMITER . $cache_group ] ) ) {
				// Update the expiration if the key is already registered.
				$keys[ $key . static::DELIMITER . $cache_group ][0] = time() + static::$threshold;
			} else {
				$keys[ $key . static::DELIMITER . $cache_group ] = [
					time() + static::$threshold,
					$type,
				];
			}
		}

		update_option( $this->get_storage_name(), $keys );

		// Reset the pending keys.
		$this->pending_keys = [];

		return $this;
	}

	/**
	 * Retrieve all the stored keys for the collector group.
	 *
	 * @return array[]
	 */
	public function keys() {
		return (array) get_option( $this->get_storage_name(), [] );
	}

	/**
	 * Purge the cache in the cache collection for the registered keys.
	 *
	 * @return static
	 */
	public function purge() {
		$keys = $this->keys();

		if ( empty( $keys ) ) {
			return $this;
		}

		$dirty = false;

		foreach ( $keys as $index => $data ) {
			[ $key, $cache_group ] = explode( static::DELIMITER, $index );
			[ $expiration, $type ] = $data;

			// Check if the key is expired and should be removed.
			if ( $expiration && $expiration < time() ) {
				unset( $keys[ $index ] );
				continue;
			}

			// Purge the cache.
			if ( self::CACHE_OBJECT_CACHE === $type ) {
				wp_cache_delete( $key, $cache_group );
			} elseif ( self::CACHE_TRANSIENT === $type ) {
				delete_transient( $key );
			}
		}

		// Update the keys if any were removed.
		if ( $dirty ) {
			update_option( $this->get_storage_name(), $keys );
		}

		return $this;
	}

	/**
	 * Retrieve the storage name for the collector.
	 *
	 * @return string
	 */
	public function get_storage_name(): string {
		return "_ccollection_{$this->collection}";
	}
}
