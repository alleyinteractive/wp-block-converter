# WP Block Converter

[![Testing Suite](https://github.com/alleyinteractive/wp-block-converter/actions/workflows/all-pr-tests.yml/badge.svg)](https://github.com/alleyinteractive/wp-block-converter/actions/workflows/all-pr-tests.yml)

Convert HTML into Gutenberg Blocks with PHP

## Installation

Requires PHP 8.4 or later, since HTML parsing is handled by the `Dom\HTMLDocument` API.

You can install the package via Composer:

```bash
composer require alleyinteractive/wp-block-converter
```

This package does not use any NPM library such as `@wordpress/blocks` to convert HTML to blocks,
and — aside from the optional `WordPress_Image_Uploader` described below — has no WordPress
dependency of its own, so it can be used inside a WordPress plugin/theme or in a plain PHP
project.

## Usage

Use this package like so to convert HTML into Gutenberg Blocks:

```php
use Alley\WP\Block_Converter\Block_Converter;

$converter = new Block_Converter( '<p>Some HTML</p>' );

$blocks = $converter->convert(); // Returns a string of converted blocks.
```

### Filtering the Blocks

> [!IMPORTANT]
> As of 2.0.0, the `wp_block_converter_*` WordPress filters/actions no longer exist. Each hook
> point is now an optional `?Closure` constructor parameter on `Block_Converter`, passed directly
> instead of registered globally with `add_filter()`/`add_action()`. This also means each hook
> only accepts a single callback, rather than any number of WordPress listeners.

The blocks can be filtered on a block-by-block basis or for an entire HTML body by passing
closures into the `Block_Converter` constructor.

#### `on_block`

Filter the generated block for a specific node.

```php
use Alley\WP\Block_Converter\Block;
use Alley\WP\Block_Converter\Block_Converter;

$converter = new Block_Converter(
	html: '<p>Some HTML</p>',
	on_block: function ( ?Block $block, \Dom\Node $node ): ?Block {
		// Modify the block before it is serialized.
		$block->content = '...';
		$block->blockName = '...';
		$block->attributes = [ ... ];

		return $block;
	},
);
```

#### `on_document_html`

Filter the generated blocks for an entire HTML body.

```php
$converter = new Block_Converter(
	html: '<p>Some HTML</p>',
	on_document_html: function ( string $blocks, \Dom\HTMLCollection $content ): string {
		// ...
		return $blocks;
	},
);
```

#### Other hooks

The remaining hook points work the same way — pass a closure into the constructor in place of
the WordPress filter/action of the same name (minus the `wp_block_converter_` prefix):

| Constructor parameter | Called with |
|---|---|
| `on_skip_minify_block` | `( bool $skip_minify_block, string $block, \Dom\Node $node ): bool` |
| `on_pre_sideload_image` | `( bool $pre, string $src, \Dom\Node $child_node, Block_Converter $converter ): bool` |
| `on_sideloaded_image` | `( string $src, \Dom\Node $child_node ): void` |
| `on_sanitized_image_url` | `( string $sanitized_url, string $url ): string` |

### Sideloading Images

By default, `Block_Converter` leaves `<img>` sources untouched — no HTTP requests are made and no
images are downloaded. To sideload images, pass an `Image_Uploader` implementation into the
`uploader` constructor parameter. Inside WordPress, pass `WordPress_Image_Uploader`, which
sideloads into the media library exactly as this package always has:

```php
use Alley\WP\Block_Converter\Block_Converter;
use Alley\WP\Block_Converter\WordPress_Image_Uploader;

$converter = new Block_Converter(
	html: '<p>Some HTML <img src="https://example.org/image.jpg" /></p>',
	uploader: new WordPress_Image_Uploader(),
);

$blocks = $converter->convert();
```

Outside of WordPress (or if you want different sideloading behavior inside WordPress), implement
the `Image_Uploader` interface yourself:

```php
use Alley\WP\Block_Converter\Image_Uploader;

class My_Image_Uploader implements Image_Uploader {
	public function upload( string $src, string $alt ): string {
		// Download $src and return the URL where it now lives.
		return $src;
	}

	public function attachment_id_for( string $url ): ?int {
		// Return an ID for the uploaded image if your storage has one, or null.
		return null;
	}

	public function get_created_attachment_ids(): array {
		// No-op if your storage has no "attachment" concept.
		return [];
	}

	public function assign_parent_to_attachments( int $parent_post_id ): void {
		// No-op if your storage has no "attachment" concept.
	}
}
```

### Attachment Parents

When converting HTML to blocks with a `WordPress_Image_Uploader` (or any `Image_Uploader` that
tracks attachment IDs), you may need to attach the images that were sideloaded to a post parent.
After the HTML is converted to blocks, you can get the attachment IDs that were created or simply
attach them to a post.

```php
$converter = new Block_Converter(
	html: '<p>Some HTML <img src="https://example.org/" /></p>',
	uploader: new WordPress_Image_Uploader(),
);
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

Block_Converter::macro( 'special-tag', function ( \Dom\Node $node ) {
	return new Block( 'core/paragraph', [], $node->textContent );
} );

// You can also use the raw HTML with a helper method from Block Converter:
Block_Converter::macro( 'special-tag', function ( \Dom\Node $node ) {
	return new Block( 'core/paragraph', [], Block_Converter::get_node_html( $node ) );
} );
```

Macros can also completely override the default behavior of the converter. This
is useful when you need to make one-off changes to the way the converter works
for a specific tag.

```php
use Alley\WP\Block_Converter\Block_Converter;
use Alley\WP\Block_Converter\Block;

Block_Converter::macro( 'p', function ( \Dom\Node $node ) {
	if ( special_condition() ) {
		return new Block( 'core/paragraph', [ 'attribute' => 123 ], 'This is a paragraph' );
	}

	return Block_Converter::p( $node );
} );
```

## Using outside of WordPress

`Block_Converter` has no WordPress dependency of its own — the only WordPress-specific code in
this package is the optional `WordPress_Image_Uploader` class described in
[Sideloading Images](#sideloading-images) above. By default (`new Block_Converter( $html )`, no
`uploader` passed), converting HTML to blocks runs entirely in plain PHP: no WordPress functions,
classes, globals, or database access, and no HTTP calls.

- If you don't need image sideloading, no further setup is required — just require this package
  with Composer and call `Block_Converter::convert()`.
- If you do need image sideloading outside of WordPress, supply your own `Image_Uploader`
  implementation (see [Sideloading Images](#sideloading-images)) instead of
  `WordPress_Image_Uploader`, which throws if WordPress isn't loaded.
- Rich embeds (Twitter/X, Instagram, Facebook, YouTube, Vimeo, and other providers WordPress core
  supports via oEmbed) are generated from a static, hardcoded provider table rather than a live
  oEmbed HTTP request, so embed URLs convert identically with or without WordPress loaded. The
  trade-off: some providers (notably YouTube) vary details like aspect ratio per-URL in ways that
  normally require the oEmbed response to detect; the provider table uses sensible fixed defaults
  instead. URLs that don't match a known provider fall back to a plain link/paragraph, same as
  before.

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
