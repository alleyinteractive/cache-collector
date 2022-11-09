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

## Installation

You can install the package via composer:

```bash
composer require alleyinteractive/cache-collector
```

## Usage

Activate the plugin in WordPress and use it like so:

```php
$plugin = Cache_Collector\Cache_Collector\Cache_Collector();
$plugin->perform_magic();
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
