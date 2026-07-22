<?php
/**
 * Class BlockConverterTest
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter\Tests\WordPress\Feature;

use Alley\WP\Block_Converter\Block_Converter;
use Alley\WP\Block_Converter\Tests\Shared\Concerns\Converts_Representative_Html;
use Alley\WP\Block_Converter\Tests\Shared\Concerns\Converts_Urls_To_Embeds;
use Alley\WP\Block_Converter\Tests\Shared\Concerns\Exercises_Constructor_Callbacks;
use Alley\WP\Block_Converter\Tests\Shared\Concerns\Supports_Macros;
use Alley\WP\Block_Converter\Tests\WordPress\TestCase;
use Alley\WP\Block_Converter\WordPress_Image_Uploader;
use Mantle\Testing\Concerns\Prevent_Remote_Requests;
use Mantle\Testing\Concerns\Refresh_Database;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * WordPress-specific coverage only: real image sideloading into the media
 * library via WordPress_Image_Uploader, which needs WordPress loaded and so
 * can't run in the standalone suite.
 *
 * Everything WordPress-agnostic lives in the shared Concerns traits (see
 * tests/shared/Concerns) and is reused here, proving this suite produces
 * identical output to the standalone suite under real WordPress rather than
 * maintaining a second copy of the same assertions.
 */
class BlockConverterTest extends TestCase {
	use Prevent_Remote_Requests, Refresh_Database;
	use Converts_Representative_Html;
	use Converts_Urls_To_Embeds;
	use Exercises_Constructor_Callbacks;
	use Supports_Macros;

	protected function setUp(): void {
		parent::setUp();

		$this->fake_request( 'https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png' )
			->with_file( __DIR__ . '/../../shared/Fixtures/image.png' );

		// Delete all uploaded files between tests.
		$dir = wp_upload_dir();

		shell_exec( "rm -rf {$dir['path']}/*" );
	}

	#[DataProvider( 'image_dataprovider' )]
	public function test_image( string $html, string $expected ) {
		$converter = new Block_Converter(
			html: $html,
			uploader: new WordPress_Image_Uploader(),
		);
		$block     = $converter->convert();

		$this->assertCount(
			expectedCount: 1,
			haystack: $converter->get_created_attachment_ids(),
		);

		$attachment_id = $converter->get_created_attachment_ids()[0];

		$expected = str_replace(
			search: [ '{{IMAGE_ID}}', '{{IMAGE_SRC}}' ],
			replace: [ $attachment_id, wp_get_attachment_url( $attachment_id ) ],
			subject: $expected,
		);

		$this->assertEquals(
			expected: $expected,
			actual: $block,
		);
		$this->assertRequestSent(
			url_or_callback: 'https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png',
			expected_times: 1,
		);
	}

	public static function image_dataprovider(): array {
		return [
			'image wrapped with figure/a' => [
				<<<HTML
<figure>
	<a href="https://alley.com/"><img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"></a>
</figure>
HTML,
				<<<HTML
<!-- wp:image {"lightbox":{"enabled":false},"id":{{IMAGE_ID}},"sizeSlug":"full","linkDestination":"custom"} -->
<figure class="wp-block-image size-full"><a href="https://alley.com/"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></a></figure>
<!-- /wp:image -->
HTML,
			],
			'image wrapped with figure/a with caption' => [
				<<<HTML
<figure>
	<a href="https://alley.com/"><img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"></a>
	<figcaption>Image caption</figcaption>
</figure>
HTML,
				<<<HTML
<!-- wp:image {"lightbox":{"enabled":false},"id":{{IMAGE_ID}},"sizeSlug":"full","linkDestination":"custom"} -->
<figure class="wp-block-image size-full"><a href="https://alley.com/"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></a><figcaption class="wp-element-caption">Image caption</figcaption></figure>
<!-- /wp:image -->
HTML,
			],
			'image wrapped with anchor' => [
				<<<HTML
<a href="https://alley.com/"><img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"></a>
HTML,
				<<<HTML
<!-- wp:image {"lightbox":{"enabled":false},"id":{{IMAGE_ID}},"sizeSlug":"full","linkDestination":"custom"} -->
<figure class="wp-block-image size-full"><a href="https://alley.com/"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></a></figure>
<!-- /wp:image -->
HTML,
			],
			'image wrapped with paragraph' => [
				<<<HTML
<p>Content before image. <img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"> Content after image.</p>
HTML,
				<<<HTML
<!-- wp:paragraph -->
<p>Content before image.</p>
<!-- /wp:paragraph -->

<!-- wp:image {"id":{{IMAGE_ID}},"sizeSlug":"full","linkDestination":"none","align":"right"} -->
<figure class="wp-block-image alignright size-full"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Content after image.</p>
<!-- /wp:paragraph -->
HTML,
			],
			'image wrapped with paragraph and anchor' => [
				<<<HTML
<p>Content before image. <a href="https://alley.com/"><img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"></a> Content after image.</p>
HTML,
				<<<HTML
<!-- wp:paragraph -->
<p>Content before image.</p>
<!-- /wp:paragraph -->

<!-- wp:image {"lightbox":{"enabled":false},"id":{{IMAGE_ID}},"sizeSlug":"full","linkDestination":"custom","align":"right"} -->
<figure class="wp-block-image alignright size-full"><a href="https://alley.com/"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></a></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Content after image.</p>
<!-- /wp:paragraph -->
HTML,
			],
			'image not wrapped' => [
				<<<HTML
<img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text">
HTML,
				<<<HTML
<!-- wp:image {"id":{{IMAGE_ID}},"sizeSlug":"full"} -->
<figure class="wp-block-image size-full"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></figure>
<!-- /wp:image -->
HTML,
			],
			'image with srcset and sizes attributes' => [
				<<<HTML
<img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" srcset="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png 300w" sizes="100vw" alt="Sample alt text">
HTML,
				<<<HTML
<!-- wp:image {"id":{{IMAGE_ID}},"sizeSlug":"full"} -->
<figure class="wp-block-image size-full"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></figure>
<!-- /wp:image -->
HTML,
			],
		];
	}
}
