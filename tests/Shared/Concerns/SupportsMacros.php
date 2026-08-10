<?php

/**
 * Trait SupportsMacros
 */

namespace Alley\WP\BlockConverter\Tests\Shared\Concerns;

use Alley\WP\BlockConverter\Block;
use Alley\WP\BlockConverter\BlockConverter;
use BadMethodCallException;
use Dom\Node;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Macro registration/override coverage (Illuminate's Macroable, mixed into
 * BlockConverter) — pure PHP, nothing WordPress specific, so it's shared
 * between the WordPress and standalone suites.
 *
 * Macros are registered on the static BlockConverter class, so tearDown()
 * flushes them after every test regardless of which test registered one —
 * that's what makes testMacroableOverrideBuiltIn (which overrides every
 * built-in tag) safe to run in any position rather than needing to be last.
 */
trait SupportsMacros
{
    /**
     * Flushes all macros registered on BlockConverter after every test, so
     * a macro registered by one test (including one that overrides every
     * built-in tag) can never leak into another regardless of test order.
     */
    protected function tearDown(): void
    {
        BlockConverter::flushMacros();

        parent::tearDown();
    }

    /**
     * Data provider of every built-in tag name that BlockConverter natively
     * handles.
     *
     * @return array<string, array{0: string}> Each item is [ $tag ] matching
     *                                         testMacroableOverrideBuiltIn()'s
     *                                         parameter.
     */
    public static function macroableDataprovider(): array
    {
        $tags = [
            'ul',
            'ol',
            'img',
            'blockquote',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'p',
            'a',
            'abbr',
            'b',
            'code',
            'em',
            'i',
            'strong',
            'sub',
            'sup',
            'span',
            'u',
            'figure',
            'br',
            'cite',
            'source',
            'hr',
        ];

        return array_combine($tags, array_map(fn (string $tag) => [$tag], $tags));
    }

    /**
     * Tests that a macro registered for a custom, non-built-in tag is invoked
     * during conversion and its returned Block is used as-is.
     */
    public function testMacroable(): void
    {
        BlockConverter::macro(
            'special-tag',
            function (Node $node) {
                return new Block('paragraph', ['attribute' => '123'], BlockConverter::getNodeHtml($node));
            },
        );

        $block = (new BlockConverter('<special-tag>content here</special-tag>'))->convert();

        $this->assertEquals(
            expected: <<<'HTML'
<!-- wp:paragraph {"attribute":"123"} -->
<special-tag>content here</special-tag>
<!-- /wp:paragraph -->
HTML,
            actual: $block,
        );
    }

    /**
     * Tests that a registered macro can be called both as an instance method
     * (via __call()) and as a static method (via __callStatic()) on
     * BlockConverter.
     */
    public function testMacroableMagicCall(): void
    {
        BlockConverter::macro(
            'shout',
            fn (string $text) => strtoupper($text),
        );

        $converter = new BlockConverter('<p>content</p>');

        $this->assertSame('HELLO', $converter->shout('hello'));
        $this->assertSame('HELLO', BlockConverter::shout('hello'));
    }

    /**
     * Tests that calling a method that isn't a real method and isn't a
     * registered macro throws BadMethodCallException.
     */
    public function testMacroableMagicCallThrowsForUnregisteredMacro(): void
    {
        $converter = new BlockConverter('<p>content</p>');

        $this->expectException(BadMethodCallException::class);

        $converter->notARegisteredMacro(); // @phpstan-ignore-line method.notFound
    }

    /**
     * Tests that registering a macro under the name of a built-in tag (from
     * macroableDataprovider()) overrides the built-in handler for that tag,
     * for every tag the converter natively supports.
     *
     * @param  string  $tag  The HTML tag name whose built-in handler is overridden.
     */
    #[DataProvider('macroableDataprovider')]
    public function testMacroableOverrideBuiltIn(string $tag): void
    {
        $isSingleTag = in_array($tag, ['img', 'br', 'hr', 'source'], true);

        BlockConverter::macro(
            $tag,
            fn (Node $node) => new Block('core/paragraph', [], $isSingleTag ? strtolower($node->nodeName) : ($node->textContent ?? '')),
        );

        $block = (new BlockConverter($isSingleTag ? "<$tag />" : "<$tag>content here</$tag>"))->convert();

        if ($isSingleTag) {
            $this->assertEquals(
                expected: <<<HTML
<!-- wp:paragraph -->
$tag
<!-- /wp:paragraph -->
HTML,
                actual: $block,
            );
        } else {
            $this->assertEquals(
                expected: <<<'HTML'
<!-- wp:paragraph -->
content here
<!-- /wp:paragraph -->
HTML,
                actual: $block,
            );
        }
    }
}
