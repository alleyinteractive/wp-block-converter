<?php

/**
 * Class BlockConverterTest
 */

namespace Alley\WP\BlockConverter\Tests\Standalone\Feature;

use Alley\WP\BlockConverter\Tests\Shared\Concerns\ConvertsRepresentativeHtml;
use Alley\WP\BlockConverter\Tests\Shared\Concerns\ConvertsUrlsToEmbeds;
use Alley\WP\BlockConverter\Tests\Shared\Concerns\ExercisesConstructorCallbacks;
use Alley\WP\BlockConverter\Tests\Shared\Concerns\SupportsMacros;
use Alley\WP\BlockConverter\Tests\Standalone\TestCase;

/**
 * Runs the full shared test suite (see tests/Shared/Concerns) with no
 * WordPress loaded at all (see tests/Standalone/bootstrap.php) — proves
 * BlockConverter needs nothing WordPress specific for any of this coverage.
 *
 * WordPress-specific behavior (real sideloading via WordPressImageUploader)
 * is covered only in tests/WordPress/Feature/BlockConverterTest.php, since it
 * can't run without WordPress loaded.
 */
class BlockConverterTest extends TestCase
{
    use ConvertsRepresentativeHtml;
    use ConvertsUrlsToEmbeds;
    use ExercisesConstructorCallbacks;
    use SupportsMacros;
}
