# WP Block Converter

[![Coding Standards](https://github.com/alleyinteractive/wp-block-converter/actions/workflows/coding-standards.yml/badge.svg)](https://github.com/alleyinteractive/wp-block-converter/actions/workflows/coding-standards.yml)
[![Testing Suite](https://github.com/alleyinteractive/wp-block-converter/actions/workflows/unit-test.yml/badge.svg)](https://github.com/alleyinteractive/wp-block-converter/actions/workflows/unit-test.yml)

Convert HTML into Gutenberg Blocks with PHP

## Installation

You can install the package via composer:

```bash
composer require alleyinteractive/wp-block-converter
```

## Usage

Use this package like so:

```php
$package = Alley\\Block_Converter\WP_Block_Converter\WP_Block_Converter();
$package->perform_magic();
```

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
