<?php

namespace Alley\WP\BlockConverter\Tests\Standalone;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * WP Block Converter Standalone Base Test Case
 *
 * Plain PHPUnit, no Mantle testkit — this suite exists to prove
 * BlockConverter runs with no WordPress loaded at all.
 */
abstract class TestCase extends PHPUnitTestCase
{
}
