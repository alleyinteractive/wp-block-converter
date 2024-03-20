# WP Block Converter

[![Coding Standards](https://github.com/alleyinteractive/wp-block-converter/actions/workflows/coding-standards.yml/badge.svg)](https://github.com/alleyinteractive/wp-block-converter/actions/workflows/coding-standards.yml)
[![Testing Suite](https://github.com/alleyinteractive/wp-block-converter/actions/workflows/unit-test.yml/badge.svg)](https://github.com/alleyinteractive/wp-block-converter/actions/workflows/unit-test.yml)

Convert HTML into Gutenberg Blocks with PHP

## Installation

You can install the package via Composer:

```bash
composer require alleyinteractive/wp-block-converter
```

This project is built to be used in a WordPress environment, so it is
recommended to use this package in a WordPress plugin or theme. Using it in
isolation is not supported at this time.

## Usage

Use this package like so to convert HTML into Gutenberg Blocks:

```php
use Alley\WP\Block_Converter\Block_Converter;

$converter = new Block_Converter( '<p>Some HTML</p>' );

$blocks = $converter->convert();
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

#### `wp_block_converter_document_html`

Filter the generated blocks for an entire HTML body.

```php
add_filter( 'wp_block_converter_document_html', function( string $blocks, \DOMNodeList $content ): string {
	// ...
	return $blocks;
}, 10, 2 );
```

### Extending the Converter with Macros

You can extend the converter with macros to add custom tags that are not yet
supported by the converter.

```php
use Alley\WP\Block_Converter\Block_Converter;
use Alley\WP\Block_Converter\Block;

Block_Converter::macro( 'special-tag', function ( \DOMNode $node ) {
	return new Block( 'core/paragraph', [], $node->textContent );
} );

// You can also use the raw HTML with a helper method from Block Converter:
Block_Converter::macro( 'special-tag', function ( \DOMNode $node ) {
	return new Block( 'core/paragraph', [], Block_Converter::get_node_html( $node ) );
} );
```

Macros can also completely override the default behavior of the converter. This
is useful when you need to make one-off changes to the way the converter works
for a specific tag.

```php
use Alley\WP\Block_Converter\Block_Converter;
use Alley\WP\Block_Converter\Block;

Block_Converter::macro( 'p', function ( \DOMNode $node ) {
	if (special_condition()) {
		return new Block( 'core/paragraph', [ 'attribute' => 123 ], 'This is a paragraph' );
	}

	return Block_Converter::p( $node );
} );
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

This project is actively maintained by [Alley Interactive](https://github.com/alleyinteractive). Like what you see? [Come work with us](https://alley.com/careers/).

- [Sean Fisher](https://github.com/srtfisher)
- [All Contributors](../../contributors)

## License

The GNU General Public License (GPL) license. Please see [License File](LICENSE) for more information.
