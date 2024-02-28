<?php
/**
 * Class Block Block_Converter
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Block_Converter\Tests\Feature;

use Alley\WP\Block_Converter\Block;
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
        $this->assertEquals(
'<!-- wp:paragraph --><p>Content to migrate</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":1} --><h1>Heading 01</h1><!-- /wp:heading -->',
			$block,
        );
    }

    public function test_convert_heading_h1_to_block() {
        $html = '<h1>Another content</h1>';
        $converter = new Block_Converter( $html );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
			'<!-- wp:heading {"level":1} -->' . $html . '<!-- /wp:heading -->',
            $block,
        );
    }

    public function test_convert_heading_h2_to_block() {
        $html = '<h2>Another content</h2>';
        $converter = new Block_Converter( $html );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
			'<!-- wp:heading {"level":2} -->' . $html . '<!-- /wp:heading -->',
			$block,
        );
    }

    public function test_convert_ol_to_block() {
        $html = '<ol><li>Random content</li><li>Another random content</li></ol>';
        $converter = new Block_Converter( $html );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
			'<!-- wp:list {"ordered":true} -->' . $html . '<!-- /wp:list -->',
			$block,
		);
    }

    public function test_convert_ul_to_block() {
        $html = '<ul><li>Random content</li><li>Another random content</li></ul>';
        $converter = new Block_Converter( $html );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
			"<!-- wp:list -->{$html}<!-- /wp:list -->",
			$block,
        );
    }

    public function test_convert_paragraphs_to_block() {
        $converter = new Block_Converter( '<p>bar</p>' );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
			'<!-- wp:paragraph --><p>bar</p><!-- /wp:paragraph -->',
			$block,
        );
    }

    public function test_convert_with_empty_paragraphs_to_block() {
        $converter = new Block_Converter( '<p>bar</p><p></p>' );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
            '<!-- wp:paragraph --><p>bar</p><!-- /wp:paragraph -->',
			$block,
        );
    }

    public function test_convert_with_empty_paragraphs_of_arbitrary_length_to_block() {
        $arbitraryNewLines = str_repeat( "\n\r", mt_rand( 1, 1000) );
        $arbitrarySpaces = str_repeat( " ", mt_rand( 1, 1000 ) );

        $converter = new Block_Converter( '<p>bar</p><p></p><p>' . $arbitrarySpaces . $arbitraryNewLines . '</p>' );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
			$block,
			'<!-- wp:paragraph --><p>bar</p><!-- /wp:paragraph -->',
        );
    }

	public function test_convert_with_filter_override_single_tag() {
		$this->expectApplied( 'wp_block_converter_document_html' )->once();

		$html = '<p>Content to migrate</p><h1>Heading 01</h1>';

		add_filter(
			'wp_block_converter_block',
			function ( Block $block ) {
				remove_all_filters( 'wp_block_converter_block' );

				$block->content = 'Override content';

				return $block;
			}
		);


		$converter = new Block_Converter( $html );
		$block     = $converter->convert();


		$this->assertSame(
			'<!-- wp:paragraph -->Override content<!-- /wp:paragraph -->

<!-- wp:heading {"level":1} --><h1>Heading 01</h1><!-- /wp:heading -->',
			$block,
		);
	}

	public function test_convert_with_filter_override_entire_content() {
		$this->expectApplied( 'wp_block_converter_block' )->twice();
		$this->expectApplied( 'wp_block_converter_document_html' )->once();

		$html = '<p>Content to migrate</p><h1>Heading 01</h1>';

		add_filter( 'wp_block_converter_document_html', fn () => 'Override' );

		$converter = new Block_Converter( $html );
		$block     = $converter->convert();

		$this->assertSame( 'Override', $block );
	}

    public function test_youtube_url_to_embed() {
        $this->fake_request( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' )
            ->with_response_code( 200 )
            ->with_body( 'test body' );
        $this->fake_request( 'https://www.youtube.com/oembed?maxwidth=500&maxheight=750&url=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3DdQw4w9WgXcQ&dnt=1&format=json' )
            ->with_response_code( 200 )
            ->with_body( '{"title":"Rick Astley - Never Gonna Give You Up (Official Music Video)","author_name":"Rick Astley","author_url":"https://www.youtube.com/@RickAstleyYT","type":"video","height":281,"width":500,"version":"1.0","provider_name":"YouTube","provider_url":"https://www.youtube.com/","thumbnail_height":360,"thumbnail_width":480,"thumbnail_url":"https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg","html":"\u003ciframe width=\u0022500\u0022 height=\u0022281\u0022 src=\u0022https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed\u0022 frameborder=\u00220\u0022 allow=\u0022accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share\u0022 allowfullscreen title=\u0022Rick Astley - Never Gonna Give You Up (Official Music Video)\u0022\u003e\u003c/iframe\u003e"}' );

        $converter = new Block_Converter( '<p>https://www.youtube.com/watch?v=dQw4w9WgXcQ</p>' );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
			'<!-- wp:embed {"url":"https://www.youtube.com/watch?v=dQw4w9WgXcQ","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} --><figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">https://www.youtube.com/watch?v=dQw4w9WgXcQ</div></figure><!-- /wp:embed -->',
			$block,
        );
    }

    public function test_x_url_to_embed() {
        $this->fake_request( 'https://twitter.com/HOT97/status/1762553251893764560' )
            ->with_response_code( 200 )
            ->with_body( 'test body' );
        $this->fake_request( 'https://publish.twitter.com/oembed?url=https%3A%2F%2Ftwitter.com%2FHOT97%2Fstatus%2F1762553251893764560' )
            ->with_response_code( 200 )
            ->with_body( '{"url":"https:\/\/twitter.com\/HOT97\/status\/1762553251893764560","author_name":"HOT 97","author_url":"https:\/\/twitter.com\/HOT97","html":"\u003Cblockquote class=\"twitter-tweet\"\u003E\u003Cp lang=\"en\" dir=\"ltr\"\u003EMore proof that \u003Ca href=\"https:\/\/twitter.com\/hashtag\/Beyonc%C3%A9?src=hash&amp;ref_src=twsrc%5Etfw\"\u003E#Beyoncé\u003C\/a\u003E and \u003Ca href=\"https:\/\/twitter.com\/hashtag\/MariahCarey?src=hash&amp;ref_src=twsrc%5Etfw\"\u003E#MariahCarey\u003C\/a\u003E continue to endure, no matter the era! \uD83D\uDD25 \uD83D\uDCC8 \u003Ca href=\"https:\/\/t.co\/aS2IIExjry\"\u003Epic.twitter.com\/aS2IIExjry\u003C\/a\u003E\u003C\/p\u003E&mdash; HOT 97 (@HOT97) \u003Ca href=\"https:\/\/twitter.com\/HOT97\/status\/1762553251893764560?ref_src=twsrc%5Etfw\"\u003EFebruary 27, 2024\u003C\/a\u003E\u003C\/blockquote\u003E\n\u003Cscript async src=\"https:\/\/platform.twitter.com\/widgets.js\" charset=\"utf-8\"\u003E\u003C\/script\u003E\n\n","width":550,"height":null,"type":"rich","cache_age":"3153600000","provider_name":"Twitter","provider_url":"https:\/\/twitter.com","version":"1.0"}' );

        $converter = new Block_Converter( '<p>https://twitter.com/HOT97/status/1762553251893764560</p>' );
        $block     = $converter->convert();

        $this->assertNotEmpty( $block );
        $this->assertSame(
            '<!-- wp:embed {"url":"https://twitter.com/HOT97/status/1762553251893764560","type":"rich","providerNameSlug":"twitter","responsive":true} --><figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter"><div class="wp-block-embed__wrapper">https://twitter.com/HOT97/status/1762553251893764560</div></figure><!-- /wp:embed -->',
            $block,
        );
    }

}
