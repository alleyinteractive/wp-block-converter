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
use Alley\WP\Block_Converter\Block_Converter;

$converter = new Block_Converter();

$blocks = $converter->convert( '<p>Some HTML</p>' );
```

### Filtering the Blocks

The blocks can be filtered on a block-by-block basis or for an entire HTML body.

#### `wp_block_converter_block`

Filter the generated block for a specific node.

```php
use Alley\WP\Block_Converter\Block;

add_filter( 'wp_block_converter_block', function ( Block $block, \DOMElement $node ): ?Block {
	// Modify the block before it is serialized.
	$block->content = '...';
	$block->blockName = '...';
	$block->attributes = [ ... ];

	return $block;
}, 10, 2 );
```

#### `wp_block_converter_html_content`

Filter the generated blocks for an entire HTML body.

```php
add_filter( 'wp_block_converter_document_html', function( string $blocks, \DOMNodeList $content ): string {
	// ...
	return $blocks;
}, 10, 2 );
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

This project is actively maintained by [Alley
Interactive](https://github.com/alleyinteractive). Like what you see? [Come work
with us](https://alley.com/careers/).

- [Sean Fisher](https://github.com/srtfisher)
- [All Contributors](../../contributors)

## License

The GNU General Public License (GPL) license. Please see [License File](LICENSE) for more information.
