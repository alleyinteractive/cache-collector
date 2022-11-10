# Cache Collector

Stable tag: 0.1.0
Requires at least: 6.0
Tested up to: 6.0
Requires PHP: 8.0
License: GPL v2 or later
Tags: alleyinteractive, cache-collector
Contributors: srtfisher

[![Coding Standards](https://github.com/alleyinteractive/cache-collector/actions/workflows/coding-standards.yml/badge.svg)](https://github.com/alleyinteractive/cache-collector/actions/workflows/coding-standards.yml)
[![Testing Suite](https://github.com/alleyinteractive/cache-collector/actions/workflows/unit-test.yml/badge.svg)](https://github.com/alleyinteractive/cache-collector/actions/workflows/unit-test.yml)

Dynamic cache key collector for easy purging.

A cache key can be related to a post, term, or any other arbitrary string. The
cache collection "collects" all of the keys related to a post, term, or
arbitrary string and allows you to purge them all at once.

This can be useful when you have a cache key that is related to a post, term, or
arbitrary string and you want to purge that cache key when the post or term is
updated. Another use case is when you have a dynamic cache key and want to purge
all the cache keys in a collection but can't because your object cache doesn't
support group purging.

## Installation

You can install the package via composer:

```bash
composer require alleyinteractive/cache-collector
```

## Usage

Activate the plugin in WordPress and use the below methods to interface with the
cache collector.

### Register a Key in a Cache Collection

```php
cache_collector_register_key( string $collection, string $key );
```

### Purging a Cache Collection

```php
cache_collector_purge( string $collection );
```

### Registering a Key Related to a Post

A post cache collection is a collection of cache keys related to a post. When a
post is updated, the cache collection is purged. This allows you to purge all of
the cache keys related to a post at once. A post will only purge the cache
related to a post if the post was recently updated (within the last week by
default).

```php
cache_collector_register_post_key( \WP_Post|int $post, string $key, string $group = '', string $type = 'cache' );
```

### Purging a Post's Cache Collection

```php
cache_collector_purge_post( int $post_id );
```

### Registering a Key Related to a Term

```php
cache_collector_register_term_key( \WP_Term|int $term, string $key, string $group = '', string $type = 'cache' );
```

### Purging a Term's Cache Collection

```php
cache_collector_purge_term( \WP_Term|int $term );
```

## Testing

Run `composer test` to run tests against PHPUnit and the PHP code in the plugin.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

This project is actively maintained by [Alley
Interactive](https://github.com/alleyinteractive). Like what you see? [Come work
with us](https://alley.co/careers/).

- [Sean Fisher](https://github.com/srtfisher)
- [All Contributors](../../contributors)

## License

The GNU General Public License (GPL) license. Please see [License File](LICENSE) for more information.
