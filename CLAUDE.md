# WordPress Block Converter

This is a library for converting HTML to WordPress blocks (sometimes referred to as Gutenberg blocks) for use with the block editor in pure PHP.

## Architecture

- `Block_Converter` (`src/Block_Converter.php`) parses HTML with `DOMDocument` and walks the nodes, dispatching each tag to a protected method (`p`, `h`, `ul`, `img`, etc.) that returns a `Block`. As of 2.0.0 it has no WordPress dependency of its own and runs standalone; the only WordPress-specific code left in the library is `WordPress_Image_Uploader` (see below).
- `Block` (`src/Block.php`) is a plain value object (block name/attributes/content) that hand-rolls WordPress' `get_comment_delimited_block_content()` rather than calling it, so it has no WP dependency either.
- Cross-cutting extension points (block filtering, image-sideload gating, minification skip, etc.) are optional `?Closure` constructor parameters on `Block_Converter` (`on_block`, `on_document_html`, `on_skip_minify_block`, `on_pre_sideload_image`, `on_sideloaded_image`, `on_sanitized_image_url`) — the caller passes a closure directly instead of registering a WordPress filter/action. These replaced the old `wp_block_converter_*` filters/actions in 2.0.0 (documented with examples in README.md, not duplicated here).
- Image sideloading is pluggable via the `Image_Uploader` interface (`src/Image_Uploader.php`), passed as the `uploader` constructor parameter; images are left untouched unless one is supplied. `WordPress_Image_Uploader` (`src/WordPress_Image_Uploader.php`) is the WordPress-backed implementation (sideloads into the media library, throws `RuntimeException` if instantiated without WordPress loaded) and is the only WordPress-specific class in the library.
- Tag handling is extensible without subclassing: `Block_Converter` uses Illuminate's `Macroable` trait (`illuminate/macroable`, swapped in from Mantle's equivalent), so callers register/override handlers via `Block_Converter::macro( 'tag', ... )` (see README for examples). Prefer this mechanism over modifying the tag `match` in `convert_node()` when adding support for a new element from consuming code.
- `src/Concerns/Microsoft_Word_Content` is mixed into `Block_Converter` to detect/clean MS Word markup pasted into HTML. (`Concerns/Listens_For_Attachments` was removed in 2.0.0 — its attachment-tracking logic moved into `WordPress_Image_Uploader`.)
- Rich embeds (Twitter/X, Instagram, Facebook, YouTube, etc.) are generated from a static provider table (`Block_Converter::OEMBED_PROVIDERS`, consumed by `embed_for_url()`) rather than a live oEmbed HTTP request — a deliberate 2.0.0 change from the previous `wp_oembed_get()`-based implementation, accepting fixed per-provider defaults (e.g. aspect ratio) in place of what live oEmbed discovery used to detect per-URL.

## Known gotchas

- `src/Convert_To_Blocks_Command.php` (`Convert_To_Blocks_Command`) defines a `wp block-converter` WP-CLI command, and README.md still documents it as usable — but its registration file (`src/cli.php`) was reverted in PR #53 and is no longer autoloaded. The class currently exists but is **not wired up to WP-CLI**. It's also excluded from PHPStan (`phpstan.neon`). Don't assume this command is live; flag the discrepancy if asked to work on it.

## Conventions

- Autoloading is standard Composer PSR-4 (no `composer-wordpress-autoloader`), so filenames match class/trait names exactly (e.g. `src/Block_Converter.php`, `src/Concerns/Microsoft_Word_Content.php`). Class/trait names themselves keep the Alley convention of underscore-separated names (e.g. `Block_Converter`), and methods/properties stay snake_case. `src/helpers.php` (a Composer `files` autoload entry) was removed in 2.0.0 along with the `"files"` entry — its one function moved into `WordPress_Image_Uploader`.
- Coding standards are enforced via the Alley-Interactive PHPCS ruleset (`phpcs.xml`) and PHPStan at max level with the WordPress extension (`phpstan.neon`).
- Composer scripts (`phpcs`, `phpstan`, `phpunit`, `test`) are defined in `composer.json` — check there rather than here, since they're kept authoritative in one place.

## Testing

- Two independent PHPUnit suites live under `tests/`, each with its own bootstrap/config/base `TestCase` (neither extends the other's), but they share their actual test coverage via traits in `tests/shared/Concerns/` (plus fixtures in `tests/shared/Fixtures/`, e.g. `Noop_Image_Uploader.php` and `image.png`) rather than duplicating assertions or fixture files:
  - `tests/wordpress/` (config: `phpunit.xml`, script: `composer phpunit`) runs against a real WordPress environment via `mantle-framework/testkit` (see `tests/wordpress/bootstrap.php`, `tests/wordpress/TestCase.php`), not plain PHPUnit with mocks. Its `BlockConverterTest` `use`s every trait in `tests/shared/Concerns/` and adds only the one thing that genuinely needs WordPress loaded: real sideloading via `WordPress_Image_Uploader` (`test_image`).
  - `tests/standalone/` (config: `phpunit-standalone.xml`, script: `composer phpunit-standalone`) never bootstraps WordPress — plain `PHPUnit\Framework\TestCase` (see `tests/standalone/TestCase.php`) — and exists to prove `Block_Converter` actually runs with no WordPress loaded. Its `BlockConverterTest` is nothing but the same shared traits.
  - When adding coverage: if it doesn't require WordPress to be loaded, add it to a trait in `tests/shared/Concerns/` (or a new one) so both suites run it and prove parity — don't add it directly to either suite's `BlockConverterTest`. Only add directly to `tests/wordpress/Feature/BlockConverterTest.php` if the behavior is genuinely WordPress-specific (i.e. it wouldn't work under `tests/standalone/`).
  - `Concerns/Supports_Macros`'s `tearDown()` calls `Block_Converter::flushMacros()` so macro-registering tests (including one that overrides every built-in tag) are safe to run in any order — don't remove that without checking whether trait composition order still guarantees cleanup some other way.

## Working with Claude

Prefer the dedicated tools over shell commands for file operations, and prefer simple, already-allowed commands over compound or complex shell invocations that require interactive approval. This allows work to continue in the background without prompting for permission.

When navigating code, always prefer LSP tools (like goToDefinition and findReferences) over grep for intelligence. Fall back to grep/glob only if LSP is unavailable.
