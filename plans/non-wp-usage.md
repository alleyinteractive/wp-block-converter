# Plan: Support Using `wp-block-converter` Outside of WordPress

Issue: [alleyinteractive/wp-block-converter#62](https://github.com/alleyinteractive/wp-block-converter/issues/62)

**This is a breaking change and ships as v2.0.0.** `Block_Converter`'s
constructor signature changes (hook callbacks replace WordPress filters/
actions) and the built-in oEmbed handling changes shape (static provider
table instead of live oEmbed discovery). Both are called out explicitly
below and must land in the changelog/PR description.

## Goal

Allow `Block_Converter::convert()` to run in a pure PHP context — no
WordPress functions, classes, globals, or database — while still producing
WordPress-compatible block markup. At the end of this refactor, the **only**
WordPress-specific code left in the library is the new
`WordPress_Image_Uploader` class (the default image-sideloading
implementation). Everything else — DOM walking, block generation, embeds,
hook points — is plain PHP with zero WordPress dependency.

## Current WordPress surface

| Call site | Function(s) | Why it's WP-only |
|---|---|---|
| `Block_Converter::__construct()` | `function_exists( 'do_action' )` gate | Blanket check that blocks all non-WP usage today |
| `Block_Converter::convert()` / `finalize_block()` / `sideload_child_images()` | `apply_filters()`, `do_action()` | Extensibility hooks documented in README |
| `Block_Converter::h()` | `absint()` | Trivial int coercion |
| `Block_Converter::p()` | `wp_oembed_get()` | Generic oEmbed detection/HTTP call |
| `Block_Converter::oembed()` | `_wp_oembed_get_object()`, `sanitize_title()` | Generic oEmbed data + slugging |
| `Block_Converter::img()` | `attachment_url_to_postid()` | Attachment ID lookup after sideloading |
| `Block_Converter::upload_image()` / `remove_image_args()` | `wp_get_attachment_url()`, `wp_parse_url()` | Sideloading + URL cleanup |
| `helpers.php: create_or_get_attachment_from_url()` | `get_posts()`, `media_sideload_image()`, `is_wp_error()`, `update_post_meta()`, `wp_update_post()`, `__()`, `esc_html()` | Media library sideloading |
| `Concerns/Listens_For_Attachments` | `add_action()`, `remove_action()`, `wp_update_post()` | Tracks attachment IDs created via WP's `add_attachment` hook |

`Block.php` already has no WP dependency (it hand-rolls
`get_comment_delimited_block_content()`) — that's the model to follow
elsewhere.

## Design decisions (confirmed with maintainer)

1. **`absint()`** — replace every call with a plain `(int)` cast. No
   absolute-value semantics needed (heading level parsing is the only call
   site, and it's already a single digit). No trace of `absint` should
   remain anywhere in the repo when done.
2. **`wp_parse_url()`** — replace with native PHP `parse_url()`. Disable the
   `WordPress.WP.AlternativeFunctions.parse_url_parse_url` phpcs sniff (the
   library can't depend on `wp_parse_url()` existing). No trace of
   `wp_parse_url` should remain anywhere in the repo when done.
3. **Hooks (`apply_filters`/`do_action`)** — **not** reimplemented as an
   internal dispatcher (reversing the earlier plan). Instead, each hook
   point becomes an optional `?Closure` constructor parameter on
   `Block_Converter`, supplied directly by the caller in place of
   registering a WordPress filter/action. This is a deliberate, accepted
   reduction from "any number of WP listeners per hook" to "one
   caller-supplied callback per hook" — acceptable as a v2 breaking change.
4. **Image sideloading** — pluggable `Image_Uploader` contract (unchanged
   from the original plan). The WP-based implementation
   (`WordPress_Image_Uploader`) is the sole remaining WP-specific code in
   the library and is used automatically by default; a non-WP consumer (or
   a WP consumer with different needs) supplies their own implementation
   instead.
5. **oEmbed-based rich embeds** — replace the generic `wp_oembed_get()` /
   `_wp_oembed_get_object()` path entirely with a hardcoded provider table,
   using the same no-HTTP methodology already used for Twitter/Instagram/
   Facebook. **Known limitation:** some providers (e.g. YouTube) vary
   parameters like aspect ratio per-URL in ways that normally require the
   oEmbed HTTP response to detect; the provider table ships sensible fixed
   defaults and this can be revisited later if it proves insufficient.
6. **Testing** — add a second, WP-free PHPUnit suite alongside the existing
   `mantle-framework/testkit`-based suite, proving the non-WP path actually
   runs standalone.

## Implementation steps

### 1. `absint()` → `(int)` cast

- `Block_Converter::h()`: replace
  `absint( str_replace( 'h', '', strtolower( $node->nodeName ) ) )` with
  `(int) str_replace( 'h', '', strtolower( $node->nodeName ) )`.
- Grep the repo after the change to confirm zero remaining references to
  `absint` (code, tests, README, CLAUDE.md).

### 2. `wp_parse_url()` → `parse_url()`

- `Block_Converter::remove_image_args()`: replace `wp_parse_url( $url )`
  with `parse_url( $url )`.
- `phpcs.xml`: add
  `WordPress.WP.AlternativeFunctions.parse_url_parse_url` to the existing
  `WordPress.WP.AlternativeFunctions` exclude block (alongside the current
  `json_encode` exclusion), with a comment explaining the library can't
  assume `wp_parse_url()` exists.
- Grep the repo after the change to confirm zero remaining references to
  `wp_parse_url`.

### 3. Replace WordPress filters/actions with constructor callbacks

`Block_Converter::__construct()` gains one nullable `Closure` parameter per
existing hook point, replacing the corresponding `apply_filters()`/
`do_action()` call. Naming mirrors the current filter/action names, minus
the `wp_block_converter_` prefix:

| New constructor param | Replaces | Signature (called with) |
|---|---|---|
| `?Closure $on_skip_minify_block` | `wp_block_converter_skip_minify_block` filter | `( bool $skip_minify_block, string $block, Node $node ): bool` |
| `?Closure $on_document_html` | `wp_block_converter_document_html` filter | `( string $html, HTMLCollection $content ): string` |
| `?Closure $on_block` | `wp_block_converter_block` filter | `( ?Block $block, Node $node ): ?Block` |
| `?Closure $on_pre_sideload_image` | `wp_block_converter_pre_sideload_image` filter | `( bool $pre, string $src, Node $child_node, Block_Converter $converter ): bool` |
| `?Closure $on_sideloaded_image` | `wp_block_converter_sideloaded_image` action | `( string $src, Node $child_node ): void` |
| `?Closure $on_sanitized_image_url` | `wp_block_converter_sanitized_image_url` filter | `( string $sanitized_url, string $url ): string` |

- Each filter-style call site (all but `on_sideloaded_image`) changes from
  `apply_filters( 'tag', $value, ...$args )` to going through a small
  private helper, e.g.:
  ```php
  protected function apply( ?Closure $callback, mixed $value, mixed ...$args ): mixed {
      return $callback ? $callback( $value, ...$args ) : $value;
  }
  ```
  so call sites stay terse (`$block = $this->apply( $this->on_block, $block, $node );`)
  and behave identically to "no filter registered" when the param is left
  `null` (the default).
- The action-style call site (`on_sideloaded_image`) simply invokes the
  closure directly if set, with no return value used.
- `Block_Converter::macro()` (Mantle's `Macroable`, used for per-tag
  extensibility) is unaffected — it's a separate mechanism from these
  cross-cutting hooks and doesn't depend on WordPress today.
- Update `tests/Feature/BlockConverterTest.php` wherever it registers
  `add_filter()`/`add_action()` against these hooks — those tests now pass
  the equivalent closure into the constructor instead.

### 4. Replace generic oEmbed handling with a static provider table

- Remove the `wp_oembed_get()` / `_wp_oembed_get_object()` calls from
  `Block_Converter::p()` / `oembed()`. `sanitize_title()` is no longer
  needed either, since each provider table entry supplies its
  `providerNameSlug` directly.
- Build a provider table (host pattern → `type`, `providerNameSlug`, block
  `className`/aspect-ratio default) covering WordPress core's built-in
  oEmbed providers (from `wp-includes/class-wp-oembed.php`'s provider
  list), the same way `twitter_embed()`/`instagram_embed()`/
  `facebook_embed()` already hardcode their shape.
- Generalize `twitter_embed()`/`instagram_embed()`/`facebook_embed()` into
  one table-driven method (e.g. `embed_for_url( string $url ): ?Block`)
  that the three existing hardcoded cases become entries in, avoiding
  parallel code paths.
- URLs that don't match a known provider fall through to the existing
  default (`html()` block / plain paragraph), same as today's behavior for
  non-oEmbed URLs.
- Regenerate `tests/Feature/__snapshots__/` deliberately (not by hand) for
  any embed URLs whose output shape changes (e.g. YouTube, Vimeo).

### 5. Introduce an `Image_Uploader` contract; delete `helpers.php`

- New interface, `src/Image_Uploader.php` (pure PHP, no WP dependency):
  ```php
  interface Image_Uploader {
      public function upload( string $src, string $alt ): string;
      public function attachment_id_for( string $url ): ?int;
      public function get_created_attachment_ids(): array;
      public function assign_parent_to_attachments( int $parent_post_id ): void;
  }
  ```
  Implementers with no "attachment" concept can trivially no-op the last two
  methods (return `[]` / do nothing).
- New class `src/WordPress_Image_Uploader.php` implementing `Image_Uploader`
  — **this is the only WordPress-specific file left in the library at the
  end of this refactor.** It absorbs:
  - `helpers.php`'s `create_or_get_attachment_from_url()` logic (`upload()`).
  - `attachment_url_to_postid()` (`attachment_id_for()`).
  - `Concerns/Listens_For_Attachments`'s attachment-ID tracking
    (`add_action( 'add_attachment', ... )` / `remove_action()`) and
    `assign_parent_to_attachments()` (`wp_update_post()`).
  - Its constructor throws a `RuntimeException` if instantiated without
    WordPress loaded (moving today's blanket `Block_Converter`
    constructor-level check down to just this class).
- Delete `src/helpers.php` entirely and remove the
  `"files": ["src/helpers.php"]` entry from `composer.json`'s `autoload`
  block, once its one function has moved into `WordPress_Image_Uploader`.
- Delete `src/Concerns/Listens_For_Attachments.php` (folded into
  `WordPress_Image_Uploader`) and remove its `use` from `Block_Converter`.
- `Block_Converter::__construct()` gains a final `?Image_Uploader $uploader
  = null` parameter. When `sideload_images` is true and no uploader was
  supplied, default to `new WordPress_Image_Uploader()` — preserving
  today's default behavior 1:1 inside WordPress, and this is where the
  `RuntimeException` now surfaces if WP isn't loaded and no custom uploader
  was given.
- `img()` / `sideload_child_images()` / `upload_image()` change to call
  `$this->uploader->upload( ... )` / `$this->uploader->attachment_id_for( ... )`
  instead of direct WP function calls.
- `remove_image_args()` stays on `Block_Converter` as a plain-PHP utility
  (only needs `parse_url()` from step 2), called before handing the src to
  the uploader — reusable by any `Image_Uploader` implementation regardless
  of whether it talks to WordPress.
- `Block_Converter::get_created_attachment_ids()` /
  `assign_parent_to_attachments()` become thin proxies to `$this->uploader`,
  returning `[]` / no-op if no uploader is set (e.g. `sideload_images` was
  never true), preserving today's public API shape (just no longer backed
  by the trait directly).
- `Concerns/Microsoft_Word_Content` and `Macroable` needn't change — neither
  depends on WordPress today.

### 6. Composer / autoload housekeeping

- Remove `"files": ["src/helpers.php"]` from `autoload` (step 5).
- Confirm `mantle-framework/support` (for `Macroable`) has no WP dependency
  of its own — it doesn't; no change needed there.
- `alleyinteractive/wp-bulk-task` is only used by the already-unwired
  `Convert_To_Blocks_Command` (per `CLAUDE.md`, not autoloaded/registered).
  Leave it as a required dependency as-is — out of scope for this issue,
  since fixing the CLI command's wiring is a separate, pre-existing
  discrepancy. Flag it in the PR description so it isn't confused with new
  work.
- Bump `composer.json` version expectations / add a `CHANGELOG.md` entry
  (or equivalent) marking this v2.0.0 with the two breaking changes called
  out (constructor callback params replacing WP hooks; oEmbed provider
  table replacing live discovery).

### 7. Update documentation

- README: add a "Using outside of WordPress" section documenting:
  - The default (`sideload_images: false`, no uploader) path works with
    zero WordPress dependency.
  - How to supply a custom `Image_Uploader` for sideloading outside WP.
  - Rewrite the "Filtering the Blocks" section entirely: replace
    `add_filter( 'wp_block_converter_block', ... )`-style examples with the
    new constructor callback parameters (`on_block`, `on_document_html`,
    etc.), and note this is a breaking change from v1.
  - The oEmbed behavior change (static provider table, no more live HTTP
    discovery) and its aspect-ratio limitation.
- Remove/soften the "Using it in isolation is not supported at this time"
  line in the Installation section.
- Update `CLAUDE.md`'s architecture notes once the new/removed files land
  (new `Image_Uploader` contract + `WordPress_Image_Uploader` default,
  removal of `helpers.php` and `Listens_For_Attachments`, hook mechanism
  now constructor callbacks instead of WP filters/actions).

### 8. Testing

- New standalone suite that never bootstraps WordPress:
  - `tests-standalone/bootstrap.php` — plain PHP bootstrap, only
    `require __DIR__ . '/../vendor/autoload.php'` (no `Mantle\Testing`
    manager call).
  - `phpunit-standalone.xml` — separate PHPUnit config pointing at the new
    bootstrap and a new test directory (e.g. `tests-standalone/Feature/`).
  - New composer script `phpunit-standalone`, added to the `test` script
    array alongside the existing `phpcs`/`phpstan`/`phpunit`.
  - Base test case for this suite is a plain `PHPUnit\Framework\TestCase`
    subclass (no Mantle testkit), since the point is proving no WP is
    loaded at all.
  - Coverage: convert representative HTML (paragraphs, headings, lists,
    images without sideloading, the new provider-table embeds, blockquotes,
    preformatted/HTML fallback blocks) with no WP bootstrapped; the
    constructor-callback hooks (`on_block`, `on_document_html`, etc.)
    exercised directly (no WP `add_filter` involved, since that mechanism
    is gone); a custom no-op `Image_Uploader` test double exercising the
    sideloading extension point end-to-end.
- Existing `tests/Feature/BlockConverterTest.php` (WP-bootstrapped via
  testkit) keeps covering the `WordPress_Image_Uploader` default path and
  anything still WP-specific (actual sideloading, real attachment
  creation/parent assignment), and is updated per step 3 to pass closures
  into the constructor instead of registering WP filters/actions.
- Regenerate embed-related snapshots deliberately once step 4 lands.

### 9. PHPStan

- `phpstan.neon` currently excludes only `Convert_To_Blocks_Command.php`.
  New files (`Image_Uploader`, `WordPress_Image_Uploader`) should be
  included in analysis, not excluded — they're real, wired-up code, and
  `paths: src/` already covers them by default.
- Confirm removing `helpers.php` doesn't leave a stale reference anywhere
  in `phpstan.neon` / `composer.json`.

## Rollout / compatibility notes

- **v2.0.0, breaking changes:**
  - `Block_Converter`'s constructor signature changes: six new nullable
    `Closure` parameters replace WordPress filter/action registration for
    the library's cross-cutting hooks, and a new `Image_Uploader`
    parameter replaces implicit sideloading behavior. Existing consumers
    using `add_filter()`/`add_action()` against
    `wp_block_converter_*` hooks must migrate to passing closures into the
    constructor instead.
  - The oEmbed provider-table change (step 4) affects existing WP-based
    consumers too, not just non-WP ones — some embed output may differ in
    shape from what live oEmbed discovery previously produced.
- No other public API is removed — `get_created_attachment_ids()` /
  `assign_parent_to_attachments()` keep their existing signatures and
  behavior when the default `WordPress_Image_Uploader` is in play.
- Sequencing: steps 1–2 (absint/wp_parse_url) are trivial and can land
  first/together. Step 3 (constructor callbacks) and step 5 (uploader
  contract) both touch the constructor signature, so land them in the same
  PR to avoid two separate breaking-change releases. Step 4 (oEmbed table)
  is the riskiest/most visible behavior change and is worth its own
  reviewable commit within that PR (or a follow-up PR, still under v2.0.0
  before release). Step 8 (standalone test suite) lands last, once the
  library is actually WP-free end to end, so it validates the finished
  state rather than a partial one.
