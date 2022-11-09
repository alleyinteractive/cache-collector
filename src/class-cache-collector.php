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
 *
 * @todo Add logger.
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
	 * Flag if the instance is dirty/modified and needs to be saved.
	 *
	 * @var bool
	 */
	// protected bool $dirty = false;

	/**
	 * Keys to be registered with the collector.
	 *
	 * @var string[]
	 */
	protected array $pending_keys = [];

	/**
	 * Create a new Cache_Collector instance for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   ...$args Arguments to pass to the constructor.
	 * @return static
	 */
	public static function for_post( WP_Post $post, array ...$args ): static {
		return new static( "post-{$post->ID}", ...$args );
	}

	/**
	 * Create a new Cache_Collector instance for a term.
	 *
	 * @param WP_Term $term Term object.
	 * @param array   ...$args Arguments to pass to the constructor.
	 * @return static
	 */
	public static function for_term( WP_Term $term, array ...$args ) {
		return new static( "term-{$term->term_id}", ...$args );
	}

	/**
	 * Constructor.
	 *
	 * @param string $collection Cache collection to attach to.
	 * @param string $type Type of cache (cache/transient).
	 */
	public function __construct(
		public string $collection,
		public string $type = self::CACHE_OBJECT_CACHE,
	) {
		if ( ! is_array( $collection ) ) {
			$collection = [ $collection ];
		}
	}

	/**
	 * Save the collector on destruct.
	 */
	public function __destruct() {
		if ( $this->is_dirty() ) {
			$this->save();
		}
	}

	/**
	 * Register a cache key.
	 *
	 * @param string $key
	 * @param string $cache_group Cache group to use, optional.
	 * @return static
	 */
	public function register( string $key, string $cache_group = '' ) {
		$this->pending_keys[ "{$key}-{$cache_group}" ] = [ $key, $cache_group ];

		return $this;
	}

	/**
	 * Save the pending registered keys.
	 *
	 * @return static
	 */
	public function save() {
		$keys = $this->keys();

		foreach ( $keys as $index => $key ) {
			[ $key, $cache_group, $expiration ] = $key;

			// Check if the key is expired and should be removed.
			if ( $expiration && $expiration < time() ) {
				unset( $keys[ $index ] );
				continue;
			}
		}

		// Append the pending keys to the existing keys.
		foreach ( $this->pending_keys as $key ) {
			[ $key, $cache_group ] = $key;

			// Check if the key is already registered.
			if ( isset( $keys[ $key . static::DELIMITER . $cache_group ] ) ) {
				// Update the expiration if the key is already registered.
				$keys[ $key . static::DELIMITER . $cache_group ] = time() + static::$threshold;
			} else {
				$keys[ $key . static::DELIMITER . $cache_group ] = time() + static::$threshold;
			}
		}

		update_option( $this->get_storage_name(), $keys );

		// Reset the pending keys.
		$this->pending_keys = [];
	}

	/**
	 * Retrieve all the stored keys for the collector group.
	 *
	 * @return array[]
	 */
	public function keys() {
		return (array) get_option( $this->get_storage_name(), [] );
	}

	public function purge( string $key ) {}


	public function is_dirty(): bool {
		return ! empty( $this->pending_keys );
	}

	/**
	 * Retrieve the storage name for the collector.
	 *
	 * @return string
	 */
	protected function get_storage_name(): string {
		return "cache-collector-{$this->collection}";
	}
}
