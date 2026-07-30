# Adopt PSR-12 code style, universally, including a full StudlyCaps/camelCase rename

_Status: accepted_

Now that the library can run without WordPress loaded (as of the in-progress, unreleased `v2.0.0`), the WordPress-based `Alley-Interactive` PHPCS ruleset (`automattic/vipwpcs` → `wp-coding-standards/wpcs`) actively fights the codebase — see [issue #66](https://github.com/alleyinteractive/wp-block-converter/issues/66) — forcing WordPress-specific alternative functions (`wp_parse_url` over `parse_url`) and global-prefixing rules that no longer apply. We're replacing it with PHP_CodeSniffer's built-in `PSR12` standard plus `slevomat/coding-standard`, applied universally across `src/` and `tests/` with no per-file exceptions, and taking the opportunity to do the PSR-1 StudlyCaps/camelCase rename (classes, methods, properties, variables) in the same breaking `v2.0.0` release rather than deferring it to a future major version.

## Considered options

- **Formatting-only PSR-12, keep snake_case naming.** Rejected: since `v2.0.0` is already an unreleased breaking-change release, deferring the naming rename to a hypothetical future major version would mean fighting a mixed snake_case/camelCase convention for another full release cycle for no benefit.
- **Keep WordPress-specific sniffs for the two files that still call WordPress APIs** (`WordPress_Image_Uploader.php`, `Convert_To_Blocks_Command.php`). Rejected: reintroduces the exact "fighting the ruleset, carving out exclusions" problem this change is meant to eliminate, just scoped to files instead of the whole project.
- **Pin an older `slevomat/coding-standard` release to stay on `php_codesniffer` 3.x.** Rejected: `php_codesniffer` 4.0.1 is a genuine stable release (November 2025), and pinning an aging Slevomat release to dodge an equally-safe tool upgrade would be inconsistent with an already-in-flight breaking release.
- **Exclude `tests/` from the new standard**, preserving `test_snake_case()` method names (a common, independently-justifiable PHPUnit convention). Rejected in favor of applying the rename and ruleset universally, per explicit preference.
- **Custom PHPCS sniff to forbid underscore-prefixed private properties.** No existing sniff in PHP_CodeSniffer core, PHPCSExtra, or Slevomat enforces this (PEAR/Zend's `ValidVariableName` actually require the opposite). Rejected in favor of leaving it as an unenforced convention rather than maintaining bespoke sniff code.
- **`SlevomatCodingStandard.Classes.ClassStructure` for full alphabetical-by-name ordering within a group.** Not possible — the sniff only enforces group/visibility order, not intra-group alphabetization by identifier name. Accepted the gap; alphabetizing within visibility is documented as a convention in `CLAUDE.md` instead of being PHPCS-enforced.
- **Keep PHPCompatibility cross-version checking** (previously transitive via `automattic/vipwpcs`, pinned to `testVersion 8.1-`). Rejected: `composer.json` requires a single PHP floor (`^8.4`) with no stated intent to support older versions, so the cross-version check no longer solves a real problem.

## Consequences

- `composer.json` drops `alleyinteractive/alley-coding-standards` and gains direct requirements on `squizlabs/php_codesniffer` (^4.x), `slevomat/coding-standard`, and `dealerdirect/phpcodesniffer-composer-installer`.
- `phpcs.xml` rebuilds on `PSR12` plus `Squiz.NamingConventions.ValidVariableName` (property/variable camelCase) and `SlevomatCodingStandard.Classes.ClassStructure` (group order: `uses` → constants by visibility → properties, static-before-instance per visibility → constructor → destructor → magic methods → `__invoke` → methods, static-before-instance per visibility) and `SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses` (defaults).
- Every class/trait/interface, method, property, and variable across `src/` and `tests/` is renamed to StudlyCaps/camelCase — a breaking change for any consumer referencing these symbols directly, documented in README's "Upgrading from v1.x" section.
- No automated enforcement exists (nor is planned) for forbidding underscore-prefixed private properties or for true alphabetical-by-name member ordering within a visibility group — both are documented conventions only.
