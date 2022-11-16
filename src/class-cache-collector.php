<?php
/**
 * Cache_Collector class file
 *
 * @package cache-collector
 */

namespace Cache_Collector;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
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
	 * Post type for storage.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'cache_collector';

	/**
	 * Meta key for storage.
	 *
	 * @var string
	 */
	public const META_KEY = 'cache_collector_keys';

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
	 * Array of arrays with the key and group as the values.
	 *
	 * @var array<int, array<int, array<int, string>>
	 */
	protected array $pending_keys = [];

	/**
	 * Create a new Cache_Collector instance for a post.
	 *
	 * @param WP_Post|int $post Post object/ID.
	 * @param array       ...$args Arguments to pass to the constructor.
	 * @return static
	 *
	 * @throws InvalidArgumentException If the post is invalid.
	 */
	public static function for_post( WP_Post|int $post, array ...$args ): static {
		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}

		if ( empty( $post ) ) {
			throw new InvalidArgumentException( 'Invalid post.' );
		}

		return new static( $post->ID, $post, ...$args );
	}

	/**
	 * Create a new Cache_Collector instance for a term.
	 *
	 * @param WP_Term|int $term Term object/ID.
	 * @param array       ...$args Arguments to pass to the constructor.
	 * @return static
	 *
	 * @throws InvalidArgumentException If the term is invalid.
	 */
	public static function for_term( WP_Term|int $term, array ...$args ) {
		if ( is_numeric( $term ) ) {
			$term = get_term( $term );
		}

		if ( empty( $term ) ) {
			throw new InvalidArgumentException( 'Invalid term.' );
		}

		return new static( $term->term_id, $term, ...$args );
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
	 * Register the post type for the cache collector.
	 */
	public static function register_post_type() {
		register_post_type( // phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.NotStringLiteral
			static::POST_TYPE,
			[
				'public'             => false,
				'publicly_queryable' => false,
			]
		);
	}

	/**
	 * Cleanup the cache collector post stored in the database.
	 *
	 * Checks if the post is older than the threshold and if so, deletes it. If
	 * it is not older than the threshold, it will check if the keys in the
	 * collection it is storing are expired.
	 */
	public static function cleanup() {
		$page  = 1;
		$limit = 100;

		while ( true ) {
			if ( $page > $limit ) {
				if ( function_exists( 'ai_logger' ) ) {
					ai_logger()->warning( 'Cache Collector: Reached limit of posts to check.' );
				}

				break;
			}

			$posts = get_posts( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts
				[
					'date_query'       => [
						[
							'column' => 'post_modified',
							'before' => gmdate( 'Y-m-d H:i:s', time() - static::$expiration_threshold ),
						],
					],
					'paged'            => $page++,
					'post_type'        => static::POST_TYPE,
					'posts_per_page'   => 100,
					'suppress_filters' => false,
				]
			);

			if ( empty( $posts ) ) {
				break;
			}

			foreach ( $posts as $post ) {
				$collection = get_post_meta( $post->ID, '_collection', true );

				if ( empty( $collection ) ) {
					wp_delete_post( $post->ID, true );
					continue;
				}

				( new static( $collection, $post ) )->save();
			}
		}
	}

	/**
	 * Constructor.
	 *
	 * @param string               $collection Cache collection to attach to.
	 * @param WP_Post|WP_Term|null $parent Object to attach to, optional. Used for storage of the cache collection.
	 * @param LoggerInterface|null $logger Logger to use.
	 */
	public function __construct(
		public string $collection,
		public WP_Post|WP_Term|null $parent = null,
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
		$pending_key = [ $key, $group ];

		if ( ! in_array( $pending_key, $this->pending_keys[ $type ] ?? [], true ) ) {
			$this->pending_keys[ $type ][] = $pending_key;
		}

		return $this;
	}

	/**
	 * Save the pending registered keys.
	 *
	 * @return static
	 */
	public function save() {
		$keys = $this->keys();

		// Check if any of the existing keys are expired.
		foreach ( [ static::CACHE_OBJECT_CACHE, static::CACHE_TRANSIENT ] as $type ) {
			if ( empty( $keys[ $type ] ) ) {
				continue;
			}

			foreach ( $keys[ $type ] as $index => $expiration ) {
				// Check if the key is expired and should be removed.
				if ( $expiration && $expiration < time() ) {
					unset( $keys[ $type ][ $index ] );
					continue;
				}
			}
		}

		// Append the pending keys for each cache type.
		foreach ( [ static::CACHE_OBJECT_CACHE, static::CACHE_TRANSIENT ] as $type ) {
			if ( empty( $this->pending_keys[ $type ] ) ) {
				continue;
			}

			foreach ( $this->pending_keys[ $type ] as $data ) {
				[ $key, $group ] = $data;

				if ( isset( $keys[ $type ][ $key . static::DELIMITER . $group ] ) ) {
					// Update the expiration if the key is already registered.
					$keys[ $type ][ $key . static::DELIMITER . $group ] = time() + static::$post_update_threshold;
				} else {
					$keys[ $type ][ $key . static::DELIMITER . $group ] = time() + static::$post_update_threshold;
				}
			}
		}

		// Ensure the keys have a valid value before saving.
		$keys = array_filter( $keys );

		if ( ! empty( $keys ) ) {
			$this->store_keys( $keys );

			if ( $this->logger ) {
				$this->logger->info( 'Saved cache collection option for ' . $this->get_storage_name(), [ 'keys' => $keys ] );
			}
		} else {
			// Delete the parent object if there are no keys and if the parent
			// is a cache collection post.
			if ( $this->parent instanceof WP_Post && static::POST_TYPE === $this->parent->post_type ) {
				wp_delete_post( $this->parent->ID, true );

				if ( $this->logger ) {
					$this->logger->info( 'Deleted cache collection option for ' . $this->get_storage_name() );
				}
			}
		}

		// Reset the pending keys.
		$this->pending_keys = [];

		return $this;
	}

	/**
	 * Retrieve all the stored keys for the collector group.
	 *
	 * @return array<string, array<string, int>>
	 */
	public function keys(): array {
		if ( $this->parent ) {
			$keys = match ( $this->parent::class ) {
				WP_Post::class => get_post_meta( $this->parent->ID, static::META_KEY, true ),
				WP_Term::class => get_term_meta( $this->parent->term_id, static::META_KEY, true ),
				default => [],
			};

			return is_array( $keys ) ? $keys : [];
		}

		return [];
	}

	/**
	 * Purge the cache in the cache collection for the registered keys.
	 *
	 * @return static
	 */
	public function purge() {
		$keys = $this->keys();

		if ( empty( $keys ) ) {
			if ( $this->logger ) {
				$this->logger->info( 'No keys to purge for ' . $this->get_storage_name() );
			}

			return $this;
		}

		$dirty = false;

		foreach ( [ static::CACHE_OBJECT_CACHE, static::CACHE_TRANSIENT ] as $type ) {
			if ( empty( $keys[ $type ] ) ) {
				continue;
			}

			foreach ( $keys[ $type ] as $index => $expiration ) {
				[ $key, $cache_group ] = explode( static::DELIMITER, $index );

				// Check if the key is expired and should be removed.
				if ( $expiration && $expiration < time() ) {
					unset( $keys[ $type ][ $index ] );
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
		}

		// Update the keys if any were removed.
		if ( $dirty ) {
			$this->store_keys( $keys );
		}

		return $this;
	}

	/**
	 * Retrieve the storage name for the collector.
	 *
	 * This is the post name of the post that stores the cache collection keys
	 * for a collection.
	 *
	 * @return string
	 */
	public function get_storage_name(): string {
		return md5( $this->collection );
	}

	/**
	 * Retrieve the parent object for the collector.
	 *
	 * The parent is the post where the cache keys will be stored for the
	 * cache collection. If an existing parent is set it will return that
	 * object. If no parent is set it will attempt to retrieve the parent
	 * post if it exists.
	 *
	 * @param bool $create Whether to create the parent if it doesn't exist.
	 * @return WP_Post|WP_Term
	 */
	public function get_parent_object( bool $create = true ): WP_Post|WP_Term|null {
		if ( $this->parent ) {
			return $this->parent;
		}

		$post = get_posts( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts
			[
				'name'            => $this->get_storage_name(),
				'no_found_rows'   => true,
				'post_status'     => 'publish',
				'post_type'       => static::POST_TYPE,
				'posts_per_page'  => 1,
				'suppress_filter' => false,
			]
		);

		if ( ! empty( $post ) ) {
			$this->parent = array_shift( $post );
			return $this->parent;
		}

		if ( ! $create ) {
			return null;
		}

		$post_id = wp_insert_post( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_insert_post_wp_insert_post
			[
				'post_name'   => $this->get_storage_name(),
				'post_status' => 'publish',
				'post_title'  => $this->get_storage_name(),
				'post_type'   => static::POST_TYPE,
			]
		);

		if ( is_wp_error( $post_id ) ) {
			if ( $this->logger ) {
				$this->logger->error(
					sprintf(
						'Failed to create parent post for cache collection %s',
						$this->get_storage_name(),
					),
					[
						'error' => $post_id->get_error_message(),
					]
				);
			}

			return null;
		}

		$this->parent = get_post( $post_id );

		// Store the collection for reference later.
		update_post_meta( $post_id, '_collection', $this->collection );

		return $this->parent;
	}

	/**
	 * Store keys in the parent post/term.
	 *
	 * @param array $keys The keys to store.
	 * @return void
	 */
	protected function store_keys( array $keys ) {
		$this->get_parent_object();

		if ( ! $this->parent ) {
			return;
		}

		if ( $this->parent instanceof WP_Post ) {
			update_post_meta( $this->parent->ID, static::META_KEY, $keys );
		} elseif ( $this->parent instanceof WP_Term ) {
			update_term_meta( $this->parent->term_id, static::META_KEY, $keys );
		}
	}
}
