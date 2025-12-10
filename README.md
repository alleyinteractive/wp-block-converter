# WP Block Converter

[![Testing Suite](https://github.com/alleyinteractive/wp-block-converter/actions/workflows/all-pr-tests.yml/badge.svg)](https://github.com/alleyinteractive/wp-block-converter/actions/workflows/all-pr-tests.yml)

Convert HTML into Gutenberg Blocks with PHP

## Installation

You can install the package via Composer:

```bash
composer require alleyinteractive/wp-block-converter
```

This project is built to be used in a WordPress environment, so it is recommended to use this
package in a WordPress plugin or theme. Using it in isolation is not supported at this time. This
package does not use any NPM library such as `@wordpress/blocks` to convert HTML to blocks.

## Usage

Use this package like so to convert HTML into Gutenberg Blocks:

```php
use Alley\WP\Block_Converter\Block_Converter;

$converter = new Block_Converter( '<p>Some HTML</p>' );

$blocks = $converter->convert(); // Returns a string of converted blocks.
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

### Attachment Parents

When converting HTML to blocks, you may need to attach the images that were
sideloaded to a post parent. After the HTML is converted to blocks, you can get
the attachment IDs that were created or simply attach them to a post.

```php
$converter = new Block_Converter( '<p>Some HTML <img src="https://example.org/" /></p>' );
$blocks = $converter->convert();

// Get the attachment IDs that were created.
$attachment_ids = $converter->get_created_attachment_ids();

// Attach the images to a post.
$parent_id = 123;
$converter->assign_parent_to_attachments( $parent_id );
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
	if ( special_condition() ) {
		return new Block( 'core/paragraph', [ 'attribute' => 123 ], 'This is a paragraph' );
	}

	return Block_Converter::p( $node );
} );
```

## WP-CLI Command

This package includes a WP-CLI command to bulk convert posts from HTML to Gutenberg blocks. The command uses [wp-bulk-task](https://github.com/alleyinteractive/wp-bulk-task) for efficient processing of large numbers of posts with resume support.

### Basic Usage

```bash
# Convert all published posts to blocks
wp block-converter

# Preview changes without saving (dry run)
wp block-converter --dry-run

# Convert a specific post
wp block-converter --post-id=123

# Convert multiple specific posts
wp block-converter --post-id=123,456,789

# Convert custom post type
wp block-converter --post-type=page

# Convert with image sideloading
wp block-converter --sideload-images

# Reset the cursor to start from the beginning
wp block-converter --rewind
```

### Command Options

- `--post-type=<post-type>` - The post type to convert. Default: `post`
- `--post-status=<post-status>` - The post status to filter by. Default: `publish`
- `--post-id=<post-id>` - Comma-separated list of post IDs to convert. If provided, only these posts will be processed.
- `--dry-run` - If present, no updates will be made. Shows what would be changed.
- `--rewind` - Resets the cursor so the next time the command is run it will start from the beginning.
- `--sideload-images` - If present, images will be sideloaded and attached to the post.

### Features

- **Resume Support**: If the command is interrupted, it will resume from where it left off on the next run
- **Progress Bar**: Shows real-time progress during bulk processing
- **Dry Run Mode**: Preview changes before actually modifying posts
- **Smart Skipping**: Automatically skips posts that already have blocks or have empty content
- **Error Handling**: Continues processing even if individual posts fail, with detailed error reporting
- **Statistics**: Displays a summary of processed, converted, skipped, and failed posts

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

This project is actively maintained by [Alley Interactive](https://github.com/alleyinteractive). Like what you see? [Come work with us](https://alley.com/careers/).

- [Sean Fisher](https://github.com/srtfisher)
- [All Contributors](../../contributors)

## License

The GNU General Public License (GPL) license. Please see [License File](LICENSE) for more information.
