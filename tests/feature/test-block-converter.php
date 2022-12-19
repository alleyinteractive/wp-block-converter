<?php
/**
 * Class Block Block_Converter
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Block_Converter\Tests\Feature;

use Alley\WP\Block_Converter\Block_Converter;
use Alley\WP\Block_Converter\Tests\Test_Case;

/**
 * Test case for Block Block_Converter Module.
 *
 * @group block
 */
class Test_Block_Block_Converter extends Test_Case {
    public function test_convert_content_to_blocks() {
        $html      = '<p>Content to migrate</p><h1>Heading 01</h1>';
        $converter = new Block_Converter( $html );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
            $block,
'<!-- wp:paragraph -->
<p>Content to migrate</p>
<!-- /wp:paragraph --><!-- wp:heading {"level":1} -->
<h1>Heading 01</h1>
<!-- /wp:heading -->'
        );
    }

    public function test_convert_heading_h1_to_block() {
        $html = '<h1>Another content</h1>';
        $converter = new Block_Converter( $html );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
            $block,
'<!-- wp:heading {"level":1} -->
' . $html . '
<!-- /wp:heading -->'
        );
    }

    public function test_convert_heading_h2_to_block() {
        $html = '<h2>Another content</h2>';
        $converter = new Block_Converter( $html );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
            $block,
'<!-- wp:heading {"level":2} -->
' . $html . '
<!-- /wp:heading -->'
        );
    }

    public function test_convert_ol_to_block() {
        $html = '<ol><li>Random content</li><li>Another random content</li></ol>';
        $converter = new Block_Converter( $html );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
            $block,
'<!-- wp:list {"ordered":true} -->
' . $html . '
<!-- /wp:list -->'
        );
    }

    public function test_convert_ul_to_block() {
        $html = '<ul><li>Random content</li><li>Another random content</li></ul>';
        $converter = new Block_Converter( $html );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
            $block,
"<!-- wp:list -->
{$html}
<!-- /wp:list -->"
        );
    }

    public function test_convert_paragraphs_to_block() {
        $converter = new Block_Converter( '<p>bar</p>' );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
            $block,
'<!-- wp:paragraph -->
<p>bar</p>
<!-- /wp:paragraph -->'
        );
    }

    public function test_convert_with_empty_paragraphs_to_block() {
        $converter = new Block_Converter( '<p>bar</p><p></p>' );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
            $block,
'<!-- wp:paragraph -->
<p>bar</p>
<!-- /wp:paragraph -->'
        );
    }

    public function test_convert_with_empty_paragraphs_of_arbitrary_length_to_block() {
        $arbitraryNewLines = str_repeat( "\n\r", mt_rand( 1, 1000) );
        $arbitrarySpaces = str_repeat( " ", mt_rand( 1, 1000 ) );

        $converter = new Block_Converter( '<p>bar</p><p></p><p>' . $arbitrarySpaces . $arbitraryNewLines . '</p>' );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
'<!-- wp:paragraph -->
<p>bar</p>
<!-- /wp:paragraph -->',
            $block
        );
    }

	public function test_convert_with_filter_override_single_tag() {
		$this->expectApplied( 'wp_block_converter_html_content' )->once();

		$html = '<p>Content to migrate</p><h1>Heading 01</h1>';

		add_filter(
			'wp_block_converter_html_tag',
			function () {
				remove_all_filters( 'wp_block_converter_html_tag' );

				return '<p>The overridden paragraph</p>';
			}
		);


		$converter = new Block_Converter( $html );
		$block     = $converter->convert();


		$this->assertSame(
'<p>The overridden paragraph</p><!-- wp:heading {"level":1} -->
<h1>Heading 01</h1>
<!-- /wp:heading -->',
			$block
		);
	}

	public function test_convert_with_filter_override_entire_content() {
		$this->expectApplied( 'wp_block_converter_html_tag' )->twice();
		$this->expectApplied( 'wp_block_converter_html_content' )->once();

		$html = '<p>Content to migrate</p><h1>Heading 01</h1>';

		add_filter( 'wp_block_converter_html_content', fn () => 'Override' );

		$converter = new Block_Converter( $html );
		$block     = $converter->convert();

		$this->assertSame( 'Override', $block );
	}
}
