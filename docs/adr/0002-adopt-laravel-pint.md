# Adopt Laravel Pint, replacing phpcs/PSR-12/Slevomat

_Status: accepted_

ADR 0001 adopted PHP_CodeSniffer's `PSR12` standard plus `slevomat/coding-standard`, but explicitly noted that `SlevomatCodingStandard.Classes.ClassStructure` could not enforce alphabetical-by-name ordering within a visibility group, leaving it a documented-only convention. PHP-CS-Fixer's `ordered_class_elements` rule (as wrapped by Laravel Pint) supports a `sort_algorithm: alpha` option that closes this gap, so we're replacing phpcs entirely with Pint, configured with the `laravel` preset plus a custom `ordered_class_elements` (mirroring the current group order, alphabetized) and `case_sensitive: false`.

## Considered options

- **Keep phpcs installed alongside Pint just for the sniffs Pint can't replicate** (`Squiz.NamingConventions.ValidVariableName` for camelCase naming, `Generic.Files.LineLength`). Rejected: running two coding-standard tools to cover a couple of sniffs defeats the point of replacing phpcs, and adds ongoing maintenance for a shrinking sliver of coverage.
- **Give `__invoke` and "named static constructor" methods their own dedicated buckets** to fully preserve the old group order. Rejected: PHP-CS-Fixer's `ordered_class_elements` has no such tokens — `__invoke` can only be classified as a generic `magic` method, and there's no factory-method heuristic. Neither gap is currently visible in this codebase (no class mixes `__invoke` with other magic methods; no static factory constructors exist), so there's nothing concrete to preserve.
- **Position the `phpunit` group (setUp/tearDown/etc.) to approximate today's alphabetical, visibility-grouped placement.** Rejected: `ordered_class_elements` always pulls these hook methods into one fixed slot regardless of visibility — there's no way to make them blend back into the surrounding protected-method group, so we accepted the tool's conventional slot (right after `magic`, before all other methods) instead of fighting it.

## Consequences

- `composer.json` drops `squizlabs/php_codesniffer`, `slevomat/coding-standard`, and `dealerdirect/phpcodesniffer-composer-installer` (and the corresponding `allow-plugins` entry), and gains `laravel/pint`. `phpcs.xml` and `.phpcs.cache.json` are removed.
- CamelCase/StudlyCaps naming and max line length are no longer automatically enforced anywhere in this codebase — they join underscore-prefixed-private-properties and alphabetical-by-name ordering (from ADR 0001) as documented-only conventions.
- `tests/WordPress/Feature/BlockConverterTest.php`'s `setUp()` and `tests/Shared/Concerns/SupportsMacros.php`'s `tearDown()` move to the top of their classes' method lists, ahead of every other method, instead of sorting alphabetically among other protected methods.
- No CI changes are needed: `alleyinteractive/action-test-php` only runs `composer run-script test`, which is updated to call the new `pint` script instead of `phpcs`.
