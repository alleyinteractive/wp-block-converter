<?php
/**
 * Trait Supports_Macros
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter\Tests\Shared\Concerns;

use Alley\WP\Block_Converter\Block;
use Alley\WP\Block_Converter\Block_Converter;
use BadMethodCallException;
use Dom\Node;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Macro registration/override coverage (Illuminate's Macroable, mixed into
 * Block_Converter) — pure PHP, nothing WordPress specific, so it's shared
 * between the WordPress and standalone suites.
 *
 * Macros are registered on the static Block_Converter class, so tearDown()
 * flushes them after every test regardless of which test registered one —
 * that's what makes test_macroable_override_built_in (which overrides every
 * built-in tag) safe to run in any position rather than needing to be last.
 */
trait Supports_Macros {
    /**
     * Data provider of every built-in tag name that Block_Converter natively
     * handles.
     *
     * @return array<string, array{0: string}> Each item is [ $tag ] matching
     *                                          test_macroable_override_built_in()'s
     *                                          parameter.
     */
    public static function macroable_dataprovider(): array {
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

        return array_combine( $tags, array_map( fn ( string $tag ) => [ $tag ], $tags ) );
    }

    /**
     * Tests that a macro registered for a custom, non-built-in tag is invoked
     * during conversion and its returned Block is used as-is.
     */
    public function test_macroable(): void {
        Block_Converter::macro(
            'special-tag',
            function ( Node $node ) {
                return new Block( 'paragraph', [ 'attribute' => '123' ], Block_Converter::get_node_html( $node ) );
            },
        );

        $block = ( new Block_Converter( '<special-tag>content here</special-tag>' ) )->convert();

        $this->assertEquals(
            expected: <<<HTML
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
     * Block_Converter.
     */
    public function test_macroable_magic_call(): void {
        Block_Converter::macro(
            'shout',
            fn ( string $text ) => strtoupper( $text ),
        );

        $converter = new Block_Converter( '<p>content</p>' );

        $this->assertSame( 'HELLO', $converter->shout( 'hello' ) );
        $this->assertSame( 'HELLO', Block_Converter::shout( 'hello' ) );
    }

    /**
     * Tests that calling a method that isn't a real method and isn't a
     * registered macro throws BadMethodCallException.
     */
    public function test_macroable_magic_call_throws_for_unregistered_macro(): void {
        $converter = new Block_Converter( '<p>content</p>' );

        $this->expectException( BadMethodCallException::class );

        $converter->not_a_registered_macro(); // @phpstan-ignore-line method.notFound
    }

    /**
     * Tests that registering a macro under the name of a built-in tag (from
     * macroable_dataprovider()) overrides the built-in handler for that tag,
     * for every tag the converter natively supports.
     *
     * @param string $tag The HTML tag name whose built-in handler is overridden.
     */
    #[DataProvider( 'macroable_dataprovider' )]
    public function test_macroable_override_built_in( string $tag ): void {
        $is_single_tag = in_array( $tag, [ 'img', 'br', 'hr', 'source' ], true );

        Block_Converter::macro(
            $tag,
            fn ( Node $node ) => new Block( 'core/paragraph', [], $is_single_tag ? strtolower( $node->nodeName ) : ( $node->textContent ?? '' ) ),
        );

        $block = ( new Block_Converter( $is_single_tag ? "<$tag />" : "<$tag>content here</$tag>" ) )->convert();

        if ( $is_single_tag ) {
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
                expected: <<<HTML
<!-- wp:paragraph -->
content here
<!-- /wp:paragraph -->
HTML,
                actual: $block,
            );
        }
    }

    /**
     * Flushes all macros registered on Block_Converter after every test, so
     * a macro registered by one test (including one that overrides every
     * built-in tag) can never leak into another regardless of test order.
     */
    protected function tearDown(): void {
        Block_Converter::flushMacros();

        parent::tearDown();
    }
}
