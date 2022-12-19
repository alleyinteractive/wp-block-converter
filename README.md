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

Use this package like so to convert HTML into Gutenberg Blocks:

```php
use use Alley\WP\Block_Converter\Block_Converter;

$converter = new Block_Converter();

$blocks = $converter->convert( '<p>Some HTML</p>' );
```

### Filtering the Blocks

The blocks can be filtered on a tag-by-tag basis or for an entire HTML body.

#### `wp_block_converter_html_tag`

Filter the generated block for a specific HTML tag.

```php
add_filter( 'wp_block_converter_html_tag', function( $block, $node ) {
	return $block;
}, 10, 4 );
```

#### `wp_block_converter_html_content`

Filter the generated blocks for an entire HTML body.

```php
add_filter( 'wp_block_converter_html_content', function( $blocks, $html ) {
	// ...
	return $blocks;
}, 10, 2 );
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
