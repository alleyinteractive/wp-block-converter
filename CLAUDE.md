# WordPress Block Converter

This is a library for converting HTML to WordPress blocks (sometimes referred to as Gutenberg blocks) for use with the block editor in pure PHP.

## Architecture

- `BlockConverter` (`src/BlockConverter.php`) parses HTML with `Dom\HTMLDocument` and walks the nodes, dispatching each tag to a protected method (`p`, `h`, `ul`, `img`, etc.) that returns a `Block`. As of 2.0.0 it has no WordPress dependency of its own and runs standalone; the only WordPress-specific code left in the library is `WordPressImageUploader` (see below).
- `Block` (`src/Block.php`) is a plain value object (block name/attributes/content) that hand-rolls WordPress' `get_comment_delimited_block_content()` rather than calling it, so it has no WP dependency either.
- Cross-cutting extension points (block filtering, image-sideload gating, minification skip, etc.) are optional `?Closure` constructor parameters on `BlockConverter` (`onBlock`, `onDocumentHtml`, `onSkipMinifyBlock`, `onPreSideloadImage`, `onSideloadedImage`, `onSanitizedImageUrl`) — the caller passes a closure directly instead of registering a WordPress filter/action. These replaced the old `wp_block_converter_*` filters/actions in 2.0.0 (documented with examples in README.md, not duplicated here).
- Image sideloading is pluggable via the `ImageUploader` interface (`src/ImageUploader.php`), passed as the `uploader` constructor parameter; images are left untouched unless one is supplied. `WordPressImageUploader` (`src/WordPressImageUploader.php`) is the WordPress-backed implementation (sideloads into the media library, throws `RuntimeException` if instantiated without WordPress loaded) and is the only WordPress-specific class in the library.
- Tag handling is extensible without subclassing: `BlockConverter` uses Illuminate's `Macroable` trait (`illuminate/macroable`, swapped in from Mantle's equivalent), so callers register/override handlers via `BlockConverter::macro( 'tag', ... )` (see README for examples). Prefer this mechanism over modifying the tag `match` in `convertNode()` when adding support for a new element from consuming code.
- `src/Concerns/MicrosoftWordContent` is mixed into `BlockConverter` to detect/clean MS Word markup pasted into HTML. (`Concerns/Listens_For_Attachments` was removed in 2.0.0 — its attachment-tracking logic moved into `WordPressImageUploader`.)
- Rich embeds (Twitter/X, Instagram, Facebook, YouTube, etc.) are generated from a static provider table (`BlockConverter::OEMBED_PROVIDERS`, consumed by `embedForUrl()`) rather than a live oEmbed HTTP request — a deliberate 2.0.0 change from the previous `wp_oembed_get()`-based implementation, accepting fixed per-provider defaults (e.g. aspect ratio) in place of what live oEmbed discovery used to detect per-URL.

## Known gotchas

- `src/ConvertToBlocksCommand.php` (`ConvertToBlocksCommand`) defines a `wp block-converter` WP-CLI command, and README.md still documents it as usable — but its registration file (`src/cli.php`) was reverted in PR #53 and is no longer autoloaded. The class currently exists but is **not wired up to WP-CLI**. It's also excluded from PHPStan (`phpstan.neon`). Don't assume this command is live; flag the discrepancy if asked to work on it.

## Conventions

- Autoloading is standard Composer PSR-4 (no `composer-wordpress-autoloader`), so filenames match class/trait names exactly (e.g. `src/BlockConverter.php`, `src/Concerns/MicrosoftWordContent.php`). As of the PSR-12 adoption (`docs/adr/0001-adopt-psr-12.md`), the codebase uses PSR-1 StudlyCaps for classes/traits/interfaces (e.g. `BlockConverter`, no underscores) and camelCase for methods, properties, and variables (e.g. `convertNode()`, `$onBlock`). `SCREAMING_SNAKE_CASE` constants (e.g. `OEMBED_PROVIDERS`) are unchanged — that's correct PSR-1 style already. Two conventions are followed but not PHPCS-enforced (see the ADR's "Consequences"): no underscore-prefixed private properties, and alphabetical-by-name ordering of members within a visibility group (member *group* order — uses, constants, properties, constructor, etc. — is enforced by `SlevomatCodingStandard.Classes.ClassStructure` in `phpcs.xml`). `src/helpers.php` (a Composer `files` autoload entry) was removed in 2.0.0 along with the `"files"` entry — its one function moved into `WordPressImageUploader`.
- Coding standards are enforced via PHP_CodeSniffer's built-in `PSR12` standard plus `slevomat/coding-standard` (`phpcs.xml`) and PHPStan at max level with the WordPress extension (`phpstan.neon`). This replaced the WordPress-based `Alley-Interactive` ruleset in the same 2.0.0 release as the StudlyCaps/camelCase rename — see `docs/adr/0001-adopt-psr-12.md` for why.
- Composer scripts (`phpcs`, `phpstan`, `phpunit`, `test`) are defined in `composer.json` — check there rather than here, since they're kept authoritative in one place.

## Testing

- Two independent PHPUnit suites live under `tests/`, each with its own bootstrap/config/base `TestCase` (neither extends the other's), but they share their actual test coverage via traits in `tests/Shared/Concerns/` (plus fixtures in `tests/Shared/Fixtures/`, e.g. `NoopImageUploader.php` and `image.png`) rather than duplicating assertions or fixture files. The three subdirectories (`tests/WordPress/`, `tests/Standalone/`, `tests/Shared/`) are capitalized to match their namespace segments (`Tests\WordPress`, `Tests\Standalone`, `Tests\Shared`) — this lets `composer.json`'s `autoload-dev` use a single PSR-4 entry (`Alley\\WP\\BlockConverter\\Tests\\` → `tests`) instead of one per subdirectory:
  - `tests/Standalone/` (config: `phpunit.xml`, script: `composer phpunit`) never bootstraps WordPress — plain `PHPUnit\Framework\TestCase` (see `tests/Standalone/TestCase.php`) — and exists to prove `BlockConverter` actually runs with no WordPress loaded. Its `BlockConverterTest` is nothing but the same shared traits. This is the baseline suite, reflecting the library's WordPress-optional architecture.
  - `tests/WordPress/` (config: `phpunit-wordpress.xml`, script: `composer phpunit-wordpress`) runs against a real WordPress environment via `mantle-framework/testkit` (see `tests/WordPress/bootstrap.php`, `tests/WordPress/TestCase.php`), not plain PHPUnit with mocks. Its `BlockConverterTest` `use`s every trait in `tests/Shared/Concerns/` and adds only the one thing that genuinely needs WordPress loaded: real sideloading via `WordPressImageUploader` (`testImage`).
  - When adding coverage: if it doesn't require WordPress to be loaded, add it to a trait in `tests/Shared/Concerns/` (or a new one) so both suites run it and prove parity — don't add it directly to either suite's `BlockConverterTest`. Only add directly to `tests/WordPress/Feature/BlockConverterTest.php` if the behavior is genuinely WordPress-specific (i.e. it wouldn't work under `tests/Standalone/`).
  - `Concerns/SupportsMacros`'s `tearDown()` calls `BlockConverter::flushMacros()` so macro-registering tests (including one that overrides every built-in tag) are safe to run in any order — don't remove that without checking whether trait composition order still guarantees cleanup some other way.
  - Test method and data-provider names follow the same camelCase convention as production code (e.g. `testConvertToBlocks()`, `converterDataProvider()`) — PSR-12 applies to `tests/` too, with no snake_case exception carved out for test methods.

## Working with Claude

Prefer the dedicated tools over shell commands for file operations, and prefer simple, already-allowed commands over compound or complex shell invocations that require interactive approval. This allows work to continue in the background without prompting for permission.

When navigating code, always prefer LSP tools (like goToDefinition and findReferences) over grep for intelligence. Fall back to grep/glob only if LSP is unavailable.

## Agent skills

### Issue tracker

Issues are tracked in GitHub Issues for alleyinteractive/wp-block-converter, using the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Domain docs

Single-context layout (`CONTEXT.md` + `docs/adr/` at repo root, created lazily as needed). See `docs/agents/domain.md`.
