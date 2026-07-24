<?php
/**
 * Class BlockConverterTest
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter\Tests\Standalone\Feature;

use Alley\WP\Block_Converter\Tests\Shared\Concerns\Converts_Representative_Html;
use Alley\WP\Block_Converter\Tests\Shared\Concerns\Converts_Urls_To_Embeds;
use Alley\WP\Block_Converter\Tests\Shared\Concerns\Exercises_Constructor_Callbacks;
use Alley\WP\Block_Converter\Tests\Shared\Concerns\Supports_Macros;
use Alley\WP\Block_Converter\Tests\Standalone\TestCase;

/**
 * Runs the full shared test suite (see tests/shared/Concerns) with no
 * WordPress loaded at all (see tests/standalone/bootstrap.php) — proves
 * Block_Converter needs nothing WordPress specific for any of this coverage.
 *
 * WordPress-specific behavior (real sideloading via WordPress_Image_Uploader)
 * is covered only in tests/wordpress/Feature/BlockConverterTest.php, since it
 * can't run without WordPress loaded.
 */
class BlockConverterTest extends TestCase {
	use Converts_Representative_Html;
	use Converts_Urls_To_Embeds;
	use Exercises_Constructor_Callbacks;
	use Supports_Macros;
}
