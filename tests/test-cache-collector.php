<?php
namespace Cache_Collector\Tests;

use Cache_Collector\Cache_Collector;

/**
 * Visit {@see https://mantle.alley.co/testing/test-framework.html} to learn more.
 */
class Cache_Collector_Test extends Test_Case {
	public function test_register_key() {
		$instance = new Cache_Collector( __FUNCTION__ );

		$this->assertEmpty( $instance->keys() );

		$instance->register( 'example-key' );

		$this->assertEmpty( $instance->keys() );

		$instance->save();

		$this->assertNotEmpty( $instance->keys() );
		$this->assertContains( [ 'example-key', '' ], $instance->keys( true ) );
		$this->assertCount( 1, $instance->keys() );
	}

	public function test_register_key_helper() {
		$instance = cache_collector_register_key( __FUNCTION__, 'example-key' );

		$instance->save();

		$this->assertNotEmpty( $instance->keys() );
		$this->assertContains( [ 'example-key', '' ], $instance->keys( true ) );
		$this->assertCount( 1, $instance->keys() );
	}

	public function test_register_multiple_keys() {
		$instance = new Cache_Collector( __FUNCTION__ );

		$this->assertEmpty( $instance->keys() );

		$instance
			->register( 'example-key' )
			->register( 'example-key-2' )
			->register( 'example-key-3', 'cache-group' );

		$this->assertEmpty( $instance->keys() );

		$instance->save();

		$this->assertNotEmpty( $instance->keys() );
		$this->assertContains( [ 'example-key', '' ], $instance->keys( true ) );
		$this->assertContains( [ 'example-key-2', '' ], $instance->keys( true ) );
		$this->assertContains( [ 'example-key-3', 'cache-group' ], $instance->keys( true ) );
		$this->assertCount( 3, $instance->keys() );
	}

	public function test_register_duplicates() {
		$instance = new Cache_Collector( __FUNCTION__ );

		$this->assertEmpty( $instance->keys() );

		$instance
			->register( 'example-key' )
			->register( 'example-key' )
			->register( 'example-key-2' )
			->register( 'example-key-2' )
			->register( 'example-key-3', 'cache-group' )
			->register( 'example-key-3', 'cache-group' );

		$this->assertEmpty( $instance->keys() );

		$instance->save();

		$this->assertNotEmpty( $instance->keys() );
		$this->assertContains( [ 'example-key', '' ], $instance->keys( true ) );
		$this->assertContains( [ 'example-key-2', '' ], $instance->keys( true ) );
		$this->assertContains( [ 'example-key-3', 'cache-group' ], $instance->keys( true ) );
		$this->assertCount( 3, $instance->keys() );
	}

	public function test_expiration_removal_on_save() {
		$instance = new Cache_Collector( __FUNCTION__ );

		update_option(
			$instance->get_storage_name(),
			[
				'example-key_:_' => [ time() - Cache_Collector::$expiration_threshold - 1000, 'cache' ],
			]
		);

		$this->assertNotEmpty( $instance->keys() );

		$instance->save();

		$this->assertEmpty( $instance->keys() );
	}

	public function test_expiration_removal_on_new_registration() {
		$instance = new Cache_Collector( __FUNCTION__ );

		update_option(
			$instance->get_storage_name(),
			[
				'example-key_:_' => [ time() - Cache_Collector::$expiration_threshold - 1000, 'cache' ],
			]
		);

		$this->assertNotEmpty( $instance->keys() );

		$instance->register( 'another-key' );

		$this->assertNotEmpty( $instance->keys() );

		$instance->save();

		$this->assertNotEmpty( $instance->keys() );
		$this->assertContains( [ 'another-key', '' ], $instance->keys( true ) );
		$this->assertCount( 1, $instance->keys() );
	}

	public function test_expiration_bumped_when_saved() {
		$instance = new Cache_Collector( __FUNCTION__ );

		update_option(
			$instance->get_storage_name(),
			[
				'example-key_:_' => [ time() - Cache_Collector::$expiration_threshold + 1000, 'cache' ],
			]
		);

		$this->assertNotEmpty( $instance->keys() );

		$instance->register( 'example-key' );

		$instance->save();

		$this->assertNotEmpty( $instance->keys() );
		$this->assertArrayHasKey( 'example-key_:_', $instance->keys() );
		$this->assertGreaterThan( time() - Cache_Collector::$expiration_threshold, $instance->keys()['example-key_:_'] );
	}

	public function test_item_preserved_when_saved() {
		$instance = new Cache_Collector( __FUNCTION__ );

		update_option(
			$instance->get_storage_name(),
			[
				'example-key_:_' => [ time() + 1000, 'cache' ],
			]
		);

		$this->assertNotEmpty( $instance->keys() );

		$instance->save();

		$this->assertNotEmpty( $instance->keys() );
		$this->assertContains( [ 'example-key', '' ], $instance->keys( true ) );
	}

	public function test_key_saved_on_destruct() {
		$instance = new Cache_Collector( __FUNCTION__ );

		$this->assertEmpty( $instance->keys() );

		$instance->register( 'example-key' );

		$this->assertEmpty( $instance->keys() );

		$storage_name = $instance->get_storage_name();

		$this->assertEmpty( get_option( $storage_name ) );

		unset( $instance );

		$this->assertNotEmpty( get_option( $storage_name ) );
	}

	public function test_purge() {
		wp_cache_set( 'example-key', 'value', 'cache-group' );

		$this->assertNotEmpty( wp_cache_get( 'example-key', 'cache-group' ) );

		$instance = new Cache_Collector( __FUNCTION__ );

		$instance->register( 'example-key', 'cache-group' );

		$instance->save();

		$this->assertNotEmpty( $instance->keys() );

		$instance->purge();

		$this->assertEmpty( wp_cache_get( 'example-key', 'cache-group' ) );
	}

	public function test_for_post() {
		$post_id = static::factory()->post->create();

		wp_cache_set( 'post-example-key', 'value', 'cache-group' );

		$this->assertNotEmpty( wp_cache_get( 'post-example-key', 'cache-group' ) );

		$instance = Cache_Collector::for_post( $post_id );

		$instance->register( 'post-example-key', 'cache-group' );

		$instance->save();

		$this->assertNotEmpty( $instance->keys() );

		$instance->purge();

		$this->assertEmpty( wp_cache_get( 'post-example-key', 'cache-group' ) );
	}

	public function test_for_post_on_post_update() {
		$this->expectApplied( 'cache_collector_post_threshold' );

		$post_id = static::factory()->post->create();

		wp_cache_set( 'post-update-key', 'value', 'cache-group' );

		$this->assertNotEmpty( wp_cache_get( 'post-update-key', 'cache-group' ) );

		$instance = Cache_Collector::for_post( $post_id );

		$instance->register( 'post-update-key', 'cache-group' );

		$instance->save();

		$this->assertNotEmpty( $instance->keys() );

		wp_update_post( [ 'ID' => $post_id ] );

		$this->assertEmpty( wp_cache_get( 'post-update-key', 'cache-group' ) );
	}

	public function test_for_post_older_than_threshold() {
		$this->expectApplied( 'cache_collector_post_threshold' );

		$post_id = static::factory()->post->create(
			[
				'post_date' => date( 'Y-m-d H:i:s', time() - YEAR_IN_SECONDS ),
			]
		);

		wp_cache_set( 'post-update-key', 'value', 'cache-group' );

		$this->assertNotEmpty( wp_cache_get( 'post-update-key', 'cache-group' ) );

		$instance = Cache_Collector::for_post( $post_id );

		$instance->register( 'post-update-key', 'cache-group' );

		$instance->save();

		$this->assertNotEmpty( $instance->keys() );

		wp_update_post( [ 'ID' => $post_id ] );

		$this->assertNotEmpty( wp_cache_get( 'post-update-key', 'cache-group' ) );
	}

	public function test_for_term() {
		$term_id = static::factory()->category->create();

		wp_cache_set( 'term-example-key', 'value', 'cache-group' );

		$this->assertNotEmpty( wp_cache_get( 'term-example-key', 'cache-group' ) );

		$instance = Cache_Collector::for_term( $term_id );

		$instance->register( 'term-example-key', 'cache-group' );

		$instance->save();

		$this->assertNotEmpty( $instance->keys() );

		$instance->purge();

		$this->assertEmpty( wp_cache_get( 'term-example-key', 'cache-group' ) );
	}

	public function test_for_term_on_term_update() {
		$term_id = static::factory()->category->create();

		wp_cache_set( 'term-update-key', 'value', 'cache-group' );

		$this->assertNotEmpty( wp_cache_get( 'term-update-key', 'cache-group' ) );

		$instance = Cache_Collector::for_term( $term_id );

		$instance->register( 'term-update-key', 'cache-group' );

		$instance->save();

		$this->assertNotEmpty( $instance->keys() );

		wp_update_term( $term_id, 'category', [ 'name' => 'Updated Name' ] );

		$this->assertEmpty( wp_cache_get( 'term-update-key', 'cache-group' ) );
	}

	public function test_cron_job_registered() {
		$this->assertInCronQueue( 'cache_collector_cleanup' );
	}

	public function test_cleanup_old_keys() {
		$instance = new Cache_Collector( __FUNCTION__ );

		update_option(
			$instance->get_storage_name(),
			[
				'example-key_:_' => [ time() - Cache_Collector::$expiration_threshold - 1000, 'cache' ],
			]
		);

		$this->assertNotEmpty( get_option( $instance->get_storage_name() ) );

		$instance->cleanup();

		$this->assertFalse( get_option( $instance->get_storage_name() ) );
	}

	public function test_cleanup_preserve_valid_keys() {
		$instance = new Cache_Collector( __FUNCTION__ );

		update_option(
			$instance->get_storage_name(),
			[
				// One expired key to purge and one valid to key to preserve.
				'expired_:_'   => [ time() - Cache_Collector::$expiration_threshold - 1000, 'cache' ],
				'valid-key_:_' => [ time() + 1000, 'cache' ],
			]
		);

		$this->assertNotEmpty( get_option( $instance->get_storage_name() ) );
		$this->assertCount( 2, $instance->keys() );

		$instance->save();

		$this->assertNotEmpty( get_option( $instance->get_storage_name() ) );
		$this->assertCount( 1, $instance->keys() );
		$this->assertContains( [ 'valid-key', '' ], $instance->keys( true ) );
	}
}
