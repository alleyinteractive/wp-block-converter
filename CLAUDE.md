# WordPress Block Converter

This is a library for converting HTML to WordPress blocks (sometimes referred to as Gutenberg blocks) for use with the block editor in pure PHP.

## Architecture

- `Block_Converter` (`src/class-block-converter.php`) parses HTML with `DOMDocument` and walks the nodes, dispatching each tag to a protected method (`p`, `h`, `ul`, `img`, etc.) that returns a `Block`. It requires WordPress to be loaded (throws `RuntimeException` in the constructor otherwise) — it's not usable standalone despite being a Composer library.
- `Block` (`src/class-block.php`) is a plain value object (block name/attributes/content) that renders via WordPress' `get_comment_delimited_block_content()`.
- Tag handling is extensible without subclassing: `Block_Converter` uses Mantle's `Macroable` trait, so callers register/override handlers via `Block_Converter::macro( 'tag', ... )` (see README for examples). Prefer this mechanism over modifying the tag `match` in `convert_node()` when adding support for a new element from consuming code.
- `src/concerns/` traits are mixed into `Block_Converter`: `Listens_For_Attachments` (tracks attachment IDs created while sideloading images) and `Microsoft_Word_Content` (detects/cleans MS Word markup pasted into HTML).
- The other extension points are WordPress filters (`wp_block_converter_block`, `wp_block_converter_document_html`, etc.) — these are documented with examples in README.md, not duplicated here.

## Known gotchas

- `src/class-convert-to-blocks-command.php` (`Convert_To_Blocks_Command`) defines a `wp block-converter` WP-CLI command, and README.md still documents it as usable — but its registration file (`src/cli.php`) was reverted in PR #53 and is no longer autoloaded. The class currently exists but is **not wired up to WP-CLI**. It's also excluded from PHPStan (`phpstan.neon`). Don't assume this command is live; flag the discrepancy if asked to work on it.

## Conventions

- Filenames follow WordPress/Alley convention: `class-*.php`, `trait-*.php` (under `concerns/`), snake_case methods/properties, underscore-separated class names (e.g. `Block_Converter`).
- Coding standards are enforced via the Alley-Interactive PHPCS ruleset (`phpcs.xml`) and PHPStan at max level with the WordPress extension (`phpstan.neon`).
- Composer scripts (`phpcs`, `phpstan`, `phpunit`, `test`) are defined in `composer.json` — check there rather than here, since they're kept authoritative in one place.

## Testing

- Tests run against a real WordPress environment via `mantle-framework/testkit` (see `tests/bootstrap.php`, `tests/TestCase.php`), not plain PHPUnit with mocks.
- `tests/Feature/BlockConverterTest.php` combines PHPUnit data providers with a couple of snapshot assertions (`tests/Feature/__snapshots__/`); regenerate snapshots deliberately rather than hand-editing them.

## Working with Claude

Prefer the dedicated tools over shell commands for file operations, and prefer simple, already-allowed commands over compound or complex shell invocations that require interactive approval. This allows work to continue in the background without prompting for permission.

When navigating code, always prefer LSP tools (like goToDefinition and findReferences) over grep for intelligence. Fall back to grep/glob only if LSP is unavailable.
