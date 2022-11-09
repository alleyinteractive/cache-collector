<?php
/**
 * Cache_Collector class file
 *
 * @package cache-collector
 */

namespace Cache_Collector;

use Psr\Log\LoggerInterface;
use WP_Post;
use WP_Term;

/**
 * Cache Collector
 *
 * @todo Add logger.
 * @todo Add CLI command.
 * @todo Add API commands.
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
	 * Prefix for the cache key.
	 *
	 * @var string
	 */
	public const STORAGE_PREFIX = '_ccollection_';

	/**
	 * Threshold in seconds for purging the cache related to a post when a post
	 * is updated.
	 *
	 * Defaults to 5 days.
	 *
	 * @var int
	 */
	public static int $post_update_threshold = 432000;

	/**
	 * Default threshold for a cache key to expire and be removed from the cache
	 * collector.
	 *
	 * @var integer
	 */
	public static int $expiration_threshold = 432000;

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
	 * Handle a post update and purge the cache if the post being updated is
	 * newer than the threshold.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_post_update( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		$threshold = static::$post_update_threshold;

		/**
		 * Filter the threshold for cache key expiration.
		 *
		 * @param int $threshold Threshold in seconds.
		 */
		$threshold = (int) apply_filters( 'cache_collector_post_threshold', $threshold, $post_id );

		// If the post is newer than the threshold, purge the cache.
		if ( get_the_date( 'U', $post ) > ( time() - $threshold ) ) {
			static::for_post( $post )->purge();
		}
	}

	/**
	 * Cleanup the cache collector options stored in the database.
	 *
	 * @return void
	 */
	public static function cleanup() {
		global $wpdb;

		// Retrieve all options that start with the cache collector prefix.
		$keys = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( static::STORAGE_PREFIX ) . '%'
			)
		);

		// Run save() on each to remove the expired keys.
		foreach ( $keys as $key ) {
			( new static( substr( $key, strlen( static::STORAGE_PREFIX ) ) ) )->save();
		}
	}

	/**
	 * Constructor.
	 *
	 * @param string               $collection Cache collection to attach to.
	 * @param LoggerInterface|null $logger Logger to use.
	 */
	public function __construct(
		public string $collection,
		public ?LoggerInterface $logger = null,
	) {
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
				$keys[ $key . static::DELIMITER . $cache_group ][0] = time() + static::$post_update_threshold;
			} else {
				$keys[ $key . static::DELIMITER . $cache_group ] = [
					time() + static::$post_update_threshold,
					$type,
				];
			}
		}

		if ( ! empty( $keys ) ) {
			update_option( $this->get_storage_name(), $keys );

			if ( $this->logger ) {
				$this->logger->info( 'Saved cache collection option for ' . $this->get_storage_name(), [ 'keys' => $keys ] );
			}
		} else {
			delete_option( $this->get_storage_name() );

			if ( $this->logger ) {
				$this->logger->info( 'Deleted cache collection option for ' . $this->get_storage_name() );
			}
		}

		// Reset the pending keys.
		$this->pending_keys = [];

		return $this;
	}

	/**
	 * Retrieve all the stored keys for the collector group.
	 *
	 * @param bool $split Whether to split up the keys into an array of arrays.
	 *                    Returns an array of arrays with the key and group.
	 * @return array[]
	 */
	public function keys( bool $split = false ): array {
		$keys = (array) get_option( $this->get_storage_name(), [] );

		if ( $split ) {
			$keys = array_map(
				fn( $key ) => explode( static::DELIMITER, $key ),
				array_keys( $keys ),
			);
		}

		return $keys;
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
				$deleted = wp_cache_delete( $key, $cache_group );
			} elseif ( self::CACHE_TRANSIENT === $type ) {
				$deleted = delete_transient( $key );
			}

			if ( $this->logger ) {
				if ( $deleted ) {
					$this->logger->debug(
						sprintf(
							'Purged %s cache key %s in group %s',
							$type,
							$key,
							$cache_group,
						),
						[
							'key'   => $key,
							'group' => $cache_group,
							'type'  => $type,
						]
					);
				} else {
					$this->logger->debug(
						sprintf(
							'Failed to purge %s cache key %s in group %s',
							$type,
							$key,
							$cache_group,
						),
						[
							'key'   => $key,
							'group' => $cache_group,
							'type'  => $type,
						]
					);
				}
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
		return static::STORAGE_PREFIX . $this->collection;
	}
}
