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
and — aside from the optional `WordPressImageUploader` described below — has no WordPress
dependency of its own, so it can be used inside a WordPress plugin/theme or in a plain PHP
project.

## Usage

Use this package like so to convert HTML into Gutenberg Blocks:

```php
use Alley\WP\BlockConverter\BlockConverter;

$converter = new BlockConverter( '<p>Some HTML</p>' );

$blocks = $converter->convert(); // Returns a string of converted blocks.
```

### Logging

Pass a PSR-3 `LoggerInterface` into the `logger` constructor parameter to receive error-level log
entries when an individual image fails to sideload (the conversion otherwise continues without
that image):

```php
use Alley\WP\BlockConverter\BlockConverter;

$converter = new BlockConverter(
	html: '<p>Some HTML</p>',
	logger: $psrLogger,
);
```

### Filtering the Blocks

The blocks can be filtered on a block-by-block basis or for an entire HTML body by passing
closures into the `BlockConverter` constructor.

#### `onBlock`

Filter the generated block for a specific node.

```php
use Alley\WP\BlockConverter\Block;
use Alley\WP\BlockConverter\BlockConverter;

$converter = new BlockConverter(
	html: '<p>Some HTML</p>',
	onBlock: function ( ?Block $block, \Dom\Node $node ): ?Block {
		// Modify the block before it is serialized.
		$block->content = '...';
		$block->blockName = '...';
		$block->attributes = [ ... ];

		return $block;
	},
);
```

#### `onDocumentHtml`

Filter the generated blocks for an entire HTML body.

```php
$converter = new BlockConverter(
	html: '<p>Some HTML</p>',
	onDocumentHtml: function ( string $blocks, \Dom\HTMLCollection $content ): string {
		// ...
		return $blocks;
	},
);
```

#### Other hooks

The remaining hook points work the same way — pass a closure into the constructor:

| Constructor parameter | Called with |
|---|---|
| `onSkipMinifyBlock` | `( bool $skipMinifyBlock, string $block, \Dom\Node $node ): bool` |
| `onPreSideloadImage` | `( bool $pre, string $src, \Dom\Node $childNode, BlockConverter $converter ): bool` |
| `onSideloadedImage` | `( string $src, \Dom\Node $childNode ): void` |
| `onSanitizedImageUrl` | `( string $sanitizedUrl, string $url ): string` |

Each hook accepts a single closure; if you need multiple listeners for the same hook, compose them
into one closure yourself.

### Sideloading Images

By default, `BlockConverter` leaves `<img>` sources untouched — no HTTP requests are made and no
images are downloaded. To sideload images, pass an `ImageUploader` implementation into the
`uploader` constructor parameter. Inside WordPress, pass `WordPressImageUploader`, which
sideloads into the media library:

```php
use Alley\WP\BlockConverter\BlockConverter;
use Alley\WP\BlockConverter\WordPressImageUploader;

$converter = new BlockConverter(
	html: '<p>Some HTML <img src="https://example.org/image.jpg" /></p>',
	uploader: new WordPressImageUploader(),
);

$blocks = $converter->convert();
```

Outside of WordPress (or if you want different sideloading behavior inside WordPress), implement
the `ImageUploader` interface yourself:

```php
use Alley\WP\BlockConverter\ImageUploader;

class MyImageUploader implements ImageUploader {
	public function upload( string $src, string $alt ): string {
		// Download $src and return the URL where it now lives.
		return $src;
	}

	public function attachmentIdFor( string $url ): ?int {
		// Return an ID for the uploaded image if your storage has one, or null.
		return null;
	}

	public function getCreatedAttachmentIds(): array {
		// No-op if your storage has no "attachment" concept.
		return [];
	}

	public function assignParentToAttachments( int $parentPostId ): void {
		// No-op if your storage has no "attachment" concept.
	}
}
```

### Attachment Parents

When converting HTML to blocks with a `WordPressImageUploader` (or any `ImageUploader` that
tracks attachment IDs), you may need to attach the images that were sideloaded to a post parent.
After the HTML is converted to blocks, you can get the attachment IDs that were created or simply
attach them to a post.

```php
$converter = new BlockConverter(
	html: '<p>Some HTML <img src="https://example.org/" /></p>',
	uploader: new WordPressImageUploader(),
);
$blocks = $converter->convert();

// Get the attachment IDs that were created.
$attachmentIds = $converter->getCreatedAttachmentIds();

// Attach the images to a post.
$parentId = 123;
$converter->assignParentToAttachments( $parentId );
```

### Extending the Converter with Macros

You can extend the converter with macros to add custom tags that are not yet
supported by the converter.

```php
use Alley\WP\BlockConverter\BlockConverter;
use Alley\WP\BlockConverter\Block;

BlockConverter::macro( 'special-tag', function ( \Dom\Node $node ) {
	return new Block( 'core/paragraph', [], $node->textContent );
} );

// You can also use the raw HTML with a helper method from Block Converter:
BlockConverter::macro( 'special-tag', function ( \Dom\Node $node ) {
	return new Block( 'core/paragraph', [], BlockConverter::getNodeHtml( $node ) );
} );
```

Macros can also completely override the default behavior of the converter. This
is useful when you need to make one-off changes to the way the converter works
for a specific tag.

```php
use Alley\WP\BlockConverter\BlockConverter;
use Alley\WP\BlockConverter\Block;

BlockConverter::macro( 'p', function ( \Dom\Node $node ) {
	if ( special_condition() ) {
		return new Block( 'core/paragraph', [ 'attribute' => 123 ], 'This is a paragraph' );
	}

	return BlockConverter::p( $node );
} );
```

### Rich Embeds

URLs on their own line (e.g. a link to a tweet or a YouTube video) are converted into the
corresponding embed block (Twitter/X, Instagram, Facebook, YouTube, Vimeo, and other providers
WordPress core supports via oEmbed) using a static, hardcoded provider table rather than a live
oEmbed HTTP request — so embed URLs convert identically with or without WordPress loaded. The
trade-off: some providers (notably YouTube) vary details like aspect ratio per-URL in ways that
normally require an oEmbed response to detect; the provider table uses sensible fixed defaults
instead. URLs that don't match a known provider fall back to a plain link/paragraph. If you need
live oEmbed responses, you can do this yourself by filtering the block output using a closure
passed to the constructor, either using core WordPress functions if you are running your
conversion in a WordPress install, or using pure PHP.

## Using outside of WordPress

`BlockConverter` has no WordPress dependency of its own — the only WordPress-specific code in
this package is the optional `WordPressImageUploader` class described in
[Sideloading Images](#sideloading-images) above. By default (`new BlockConverter( $html )`, no
`uploader` passed), converting HTML to blocks runs entirely in plain PHP: no WordPress functions,
classes, globals, or database access, and no HTTP calls.

- If you don't need image sideloading, no further setup is required — just require this package
  with Composer and call `BlockConverter::convert()`.
- If you do need image sideloading outside of WordPress, supply your own `ImageUploader`
  implementation (see [Sideloading Images](#sideloading-images)) instead of
  `WordPressImageUploader`, which throws if WordPress isn't loaded.

## WP-CLI Command

This package includes a `ConvertToBlocksCommand` class to bulk convert posts from HTML to
Gutenberg blocks, using [wp-bulk-task](https://github.com/alleyinteractive/wp-bulk-task) for
efficient processing of large numbers of posts with resume support. The class is not registered
with WP-CLI automatically — register it yourself (e.g. in your plugin or theme's `functions.php`):

```php
if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\Alley\WP\BlockConverter\ConvertToBlocksCommand' ) ) {
	\WP_CLI::add_command( 'block-converter', \Alley\WP\BlockConverter\ConvertToBlocksCommand::class );
}
```

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

## Upgrading from v1.x

Version 2.0.0 contains several breaking changes related to a shift in philosophy: this package no
longer assumes WordPress is loaded. Previously, `BlockConverter` (formerly `Block_Converter`)
threw a `RuntimeException` unless WordPress was present, used WordPress hooks and
`wp_oembed_get()` internally, and always sideloaded through the media library. Now WordPress is
entirely optional, and every WordPress-specific behavior is something you opt into explicitly
rather than something the library assumes.

Specifically:

- **PHP 8.4 is now required**, updated from 8.2 in v1.x.
- **The constructor no longer requires WordPress to be loaded.** `new BlockConverter( $html )`
  previously threw a `RuntimeException` outside of WordPress; it now works standalone.
- **`wp_block_converter_*` filters/actions were replaced with constructor closures.** Each
  WordPress hook is now an optional `?Closure` constructor parameter on `BlockConverter`, passed
  directly instead of registered globally with `add_filter()`/`add_action()`. This also means each
  hook accepts only a single callback, rather than any number of WordPress listeners. Update your
  code as follows:

  | v1.x | v2.0.0 |
  |---|---|
  | `add_filter( 'wp_block_converter_skip_minify_block', ... )` | `onSkipMinifyBlock` constructor parameter |
  | `add_filter( 'wp_block_converter_document_html', ... )` | `onDocumentHtml` constructor parameter |
  | `add_filter( 'wp_block_converter_block', ... )` | `onBlock` constructor parameter |
  | `add_filter( 'wp_block_converter_pre_sideload_image', ... )` | `onPreSideloadImage` constructor parameter |
  | `add_action( 'wp_block_converter_sideloaded_image', ... )` | `onSideloadedImage` constructor parameter |
  | `add_filter( 'wp_block_converter_sanitized_image_url', ... )` | `onSanitizedImageUrl` constructor parameter |

- **Image sideloading is now driven by an `ImageUploader` implementation, not a `sideload_images`
  boolean.** The `sideload_images` constructor parameter is gone. Pass `uploader: new
  WordPressImageUploader()` to keep sideloading into the media library exactly as before, pass
  your own `ImageUploader` implementation to sideload somewhere else, or omit `uploader` entirely
  to leave images untouched (the new default — v1.x defaulted `sideload_images` to `false` as
  well, but always required WordPress to be loaded even when not sideloading). See
  [Sideloading Images](#sideloading-images).
- **Rich embeds no longer make a live oEmbed HTTP request.** `wp_oembed_get()` has been replaced
  with a static, hardcoded provider table. See [Rich Embeds](#rich-embeds) for the trade-offs.
- **Macros now use Illuminate's `Macroable`** (`illuminate/macroable`) instead of Mantle's. The
  public `BlockConverter::macro()` API is unchanged, so existing macro registrations don't need
  to be rewritten.
- **`Concerns\Listens_For_Attachments` was removed** along with `src/helpers.php`. Their
  attachment-tracking logic moved into `WordPressImageUploader`, which implements the new
  `ImageUploader` interface. If you called either directly rather than going through
  `BlockConverter`, switch to `WordPressImageUploader`.
- **Every class, method, property, and variable was renamed to StudlyCaps/camelCase** (PSR-12
  adoption, see `docs/adr/0001-adopt-psr-12.md`), and the namespace root itself moved from
  `Alley\WP\Block_Converter` to `Alley\WP\BlockConverter`. Notably:

  | v1.x | v2.0.0 |
  |---|---|
  | `Alley\WP\Block_Converter\Block_Converter` | `Alley\WP\BlockConverter\BlockConverter` |
  | `Alley\WP\Block_Converter\Image_Uploader` | `Alley\WP\BlockConverter\ImageUploader` |
  | `Alley\WP\Block_Converter\WordPress_Image_Uploader` | `Alley\WP\BlockConverter\WordPressImageUploader` |
  | `Alley\WP\Block_Converter\Convert_To_Blocks_Command` | `Alley\WP\BlockConverter\ConvertToBlocksCommand` |
  | `Block::$block_name` | `Block::$blockName` |
  | `get_created_attachment_ids()` / `assign_parent_to_attachments()` | `getCreatedAttachmentIds()` / `assignParentToAttachments()` |

  Update any code that references these symbols directly, or that subclasses/extends them.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

This project is actively maintained by [Alley Interactive](https://github.com/alleyinteractive). Like what you see? [Come work with us](https://alley.com/careers/).

- [Sean Fisher](https://github.com/srtfisher)
- [All Contributors](../../contributors)

## License

The GNU General Public License (GPL) license. Please see [License File](LICENSE) for more information.
