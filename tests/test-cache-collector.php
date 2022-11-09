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
		$this->assertArrayHasKey( 'example-key_:_', $instance->keys() );
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
		$this->assertArrayHasKey( 'example-key_:_', $instance->keys() );
		$this->assertArrayHasKey( 'example-key-2_:_', $instance->keys() );
		$this->assertArrayHasKey( 'example-key-3_:_cache-group', $instance->keys() );
		$this->assertCount( 3, $instance->keys() );
	}

	// public function test_expiration() {
	// 	$instance = new Cache_Collector( __FUNCTION__ );

	// 	update_option(
	// 		$instance->get_storage_name(),
	// 		[
	// 			'example-key-' => [ 'example-key', '', time() - 1 ],
	// 		]
	// 	);
	// 	)

	// public function test_purge() {}

	// public function test_for_post() {}

	// public function test_for_term() {}
}
