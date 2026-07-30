<?php
/**
 * Trait Exercises_Constructor_Callbacks
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter\Tests\Shared\Concerns;

use Alley\WP\Block_Converter\Block;
use Alley\WP\Block_Converter\Block_Converter;
use Alley\WP\Block_Converter\Tests\Shared\Fixtures\Noop_Image_Uploader;
use Dom\Node;

/**
 * Exercises the constructor-callback hooks (on_block, on_document_html,
 * on_skip_minify_block, on_sanitized_image_url, on_pre_sideload_image,
 * on_sideloaded_image) and the Image_Uploader contract directly, using a
 * trivial Noop_Image_Uploader rather than WordPress_Image_Uploader — none
 * of this mechanism is WordPress specific, so it's shared between the
 * WordPress and standalone suites rather than duplicated.
 */
trait Exercises_Constructor_Callbacks {
	/**
	 * Tests that the on_block callback can rewrite the content of a single
	 * generated block (here, only paragraph blocks) while leaving others
	 * (the heading block) untouched.
	 */
	public function test_on_block_can_modify_a_single_block(): void {
		$html = <<<HTML
<p>Content to migrate</p>
<h1>Heading 01</h1>
HTML;

		$converter = new Block_Converter(
			html: $html,
			on_block: function ( ?Block $block, Node $node ) {
				if ( $block instanceof Block && 'p' === strtolower( $node->nodeName ) ) {
					$block->content = 'Override content';
				}

				return $block;
			},
		);

		$this->assertSame(
			expected: <<<HTML
<!-- wp:paragraph -->
Override content
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Heading 01</h1>
<!-- /wp:heading -->
HTML,
			actual: $converter->convert(),
		);
	}

	/**
	 * Tests that the on_document_html callback can override the entire
	 * converted output for the document, while on_block is still invoked once
	 * per top-level node before that override is applied.
	 */
	public function test_on_document_html_can_override_the_whole_output(): void {
		$on_block_calls = 0;

		$converter = new Block_Converter(
			html: '<p>Content to migrate</p><h1>Heading 01</h1>',
			on_block: function ( ?Block $block, Node $node ) use ( &$on_block_calls ) {
				$on_block_calls++;

				return $block;
			},
			on_document_html: fn () => 'Override',
		);

		$this->assertSame( 'Override', $converter->convert() );
		$this->assertSame( 2, $on_block_calls );
	}

	/**
	 * Tests that the on_skip_minify_block callback is invoked once per
	 * top-level node with the tentative skip-minify flag, the block's HTML,
	 * and the source node, and that its return value is honored.
	 */
	public function test_on_skip_minify_block_is_invoked_per_top_level_node(): void {
		$calls = [];

		$converter = new Block_Converter(
			html: '<p>First</p><p>Second</p>',
			on_skip_minify_block: function ( bool $skip_minify_block, string $block, Node $node ) use ( &$calls ) {
				$calls[] = [ $skip_minify_block, $block, $node ];

				return $skip_minify_block;
			},
		);

		$converter->convert();

		$this->assertCount( 2, $calls );

		foreach ( $calls as [ $skip_minify_block, $block, $node ] ) {
			$this->assertFalse( $skip_minify_block );
			$this->assertStringContainsString( '<!-- wp:paragraph -->', $block );
			$this->assertInstanceOf( Node::class, $node );
		}
	}

	/**
	 * Tests that the on_sanitized_image_url callback can append to the
	 * sanitized image URL produced by remove_image_args().
	 */
	public function test_on_sanitized_image_url_filters_the_reconstructed_url(): void {
		$converter = new Block_Converter(
			html: '<p>Unused</p>',
			on_sanitized_image_url: fn ( string $sanitized_url, string $url ) => $sanitized_url . '?cachebust=1',
		);

		$this->assertSame(
			expected: 'https://example.org/image.jpg?cachebust=1',
			actual: $converter->remove_image_args( 'https://example.org/image.jpg?utm_source=foo' ),
		);
	}

	/**
	 * Tests that on_pre_sideload_image is invoked for every child image (and
	 * can veto sideloading a specific one), and that on_sideloaded_image then
	 * fires only for the images that were actually sideloaded, with the
	 * vetoed image left untouched in the output.
	 */
	public function test_on_pre_sideload_image_and_on_sideloaded_image_are_invoked_for_child_images(): void {
		$uploader             = new Noop_Image_Uploader();
		$pre_sideload_sources = [];
		$sideloaded_sources   = [];

		$converter = new Block_Converter(
			html: <<<HTML
<div>
	<img src="https://example.org/a.jpg" alt="A" />
	<img src="https://example.org/b.jpg" alt="B" />
</div>
HTML,
			on_pre_sideload_image: function ( bool $pre, string $src, Node $child_node, Block_Converter $converter ) use ( &$pre_sideload_sources ) {
				$pre_sideload_sources[] = $src;

				// Skip sideloading the second image only.
				return ! str_contains( $src, '/b.jpg' );
			},
			on_sideloaded_image: function ( string $src, Node $child_node ) use ( &$sideloaded_sources ) {
				$sideloaded_sources[] = $src;
			},
			uploader: $uploader,
		);

		$result = $converter->convert();

		$this->assertSame(
			expected: [ 'https://example.org/a.jpg', 'https://example.org/b.jpg' ],
			actual: $pre_sideload_sources,
		);
		$this->assertSame(
			expected: [ 'https://example.org/a.jpg#uploaded' ],
			actual: $sideloaded_sources,
		);
		$this->assertCount( 1, $uploader->uploaded );
		$this->assertStringContainsString( 'https://example.org/a.jpg#uploaded', $result );
		$this->assertStringContainsString( 'https://example.org/b.jpg', $result );
		$this->assertStringNotContainsString( 'https://example.org/b.jpg#uploaded', $result );
	}

	/**
	 * Tests that supplying a custom Image_Uploader causes an image to be
	 * sideloaded through it end-to-end, with the uploader's rewritten source
	 * reflected in the resulting image block.
	 */
	public function test_a_custom_uploader_sideloads_images_end_to_end(): void {
		$uploader = new Noop_Image_Uploader();

		$converter = new Block_Converter(
			html: '<img src="https://example.org/image.jpg" alt="Sample alt text" />',
			uploader: $uploader,
		);

		$result = $converter->convert();

		$this->assertSame(
			expected: [
				[
					'src' => 'https://example.org/image.jpg',
					'alt' => 'Sample alt text',
				],
			],
			actual: $uploader->uploaded,
		);
		$this->assertSame(
			expected: <<<HTML
<!-- wp:image {"sizeSlug":"full"} -->
<figure class="wp-block-image size-full"><img src="https://example.org/image.jpg#uploaded" alt="Sample alt text"/></figure>
<!-- /wp:image -->
HTML,
			actual: $result,
		);
	}

	/**
	 * Tests that get_created_attachment_ids() and assign_parent_to_attachments()
	 * proxy through to the configured uploader rather than assuming a
	 * WordPress-shaped uploader, using an uploader with no attachment concept
	 * of its own to prove neither call throws or requires one.
	 */
	public function test_get_created_attachment_ids_and_assign_parent_proxy_to_the_uploader(): void {
		$uploader = new Noop_Image_Uploader();

		$converter = new Block_Converter(
			html: '<img src="https://example.org/image.jpg" alt="Sample alt text" />',
			uploader: $uploader,
		);
		$converter->convert();

		// Noop_Image_Uploader tracks no attachment IDs of its own — proves
		// these calls proxy through rather than assuming a WordPress-shaped
		// uploader.
		$this->assertSame( [], $converter->get_created_attachment_ids() );

		// Assigning a parent must not throw even though this uploader has no
		// "attachment" concept to assign a parent to.
		$converter->assign_parent_to_attachments( 123 );

		$this->addToAssertionCount( 1 );
	}
}
