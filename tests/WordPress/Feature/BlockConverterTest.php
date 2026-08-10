<?php

/**
 * Class BlockConverterTest
 */

namespace Alley\WP\BlockConverter\Tests\WordPress\Feature;

use Alley\WP\BlockConverter\BlockConverter;
use Alley\WP\BlockConverter\Tests\Shared\Concerns\ConvertsRepresentativeHtml;
use Alley\WP\BlockConverter\Tests\Shared\Concerns\ConvertsUrlsToEmbeds;
use Alley\WP\BlockConverter\Tests\Shared\Concerns\ExercisesConstructorCallbacks;
use Alley\WP\BlockConverter\Tests\Shared\Concerns\SupportsMacros;
use Alley\WP\BlockConverter\Tests\WordPress\TestCase;
use Alley\WP\BlockConverter\WordPressImageUploader;
use Mantle\Testing\Concerns\Prevent_Remote_Requests;
use Mantle\Testing\Concerns\Refresh_Database;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * WordPress-specific coverage only: real image sideloading into the media
 * library via WordPressImageUploader, which needs WordPress loaded and so
 * can't run in the standalone suite.
 *
 * Everything WordPress-agnostic lives in the shared Concerns traits (see
 * tests/Shared/Concerns) and is reused here, proving this suite produces
 * identical output to the standalone suite under real WordPress rather than
 * maintaining a second copy of the same assertions.
 */
class BlockConverterTest extends TestCase
{
    use ConvertsRepresentativeHtml;
    use ConvertsUrlsToEmbeds;
    use ExercisesConstructorCallbacks;
    use Prevent_Remote_Requests;
    use Refresh_Database;
    use SupportsMacros;

    /**
     * Fakes the remote request for the test image so sideloading it never hits
     * the network, and clears the uploads directory before each test so
     * attachment IDs and filenames don't leak between tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->fake_request('https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png')
            ->with_file(__DIR__.'/../../Shared/Fixtures/image.png');

        // Delete all uploaded files between tests.
        $dir = wp_upload_dir();

        shell_exec("rm -rf {$dir['path']}/*");
    }

    /**
     * Data provider of images in different surrounding markup (bare, wrapped
     * in a figure/anchor, with a caption, inline within a paragraph) and
     * their expected sideloaded image block markup.
     *
     * @return array<string, array{0: string, 1: string}> Each item is
     *                                                    [ $html, $expected ]
     *                                                    matching
     *                                                    testImage()'s parameters.
     */
    public static function imageDataprovider(): array
    {
        return [
            'image wrapped with figure/a' => [
                <<<'HTML'
<figure>
	<a href="https://alley.com/"><img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"></a>
</figure>
HTML,
                <<<'HTML'
<!-- wp:image {"lightbox":{"enabled":false},"id":{{IMAGE_ID}},"sizeSlug":"full","linkDestination":"custom"} -->
<figure class="wp-block-image size-full"><a href="https://alley.com/"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></a></figure>
<!-- /wp:image -->
HTML,
            ],
            'image wrapped with figure/a with caption' => [
                <<<'HTML'
<figure>
	<a href="https://alley.com/"><img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"></a>
	<figcaption>Image caption</figcaption>
</figure>
HTML,
                <<<'HTML'
<!-- wp:image {"lightbox":{"enabled":false},"id":{{IMAGE_ID}},"sizeSlug":"full","linkDestination":"custom"} -->
<figure class="wp-block-image size-full"><a href="https://alley.com/"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></a><figcaption class="wp-element-caption">Image caption</figcaption></figure>
<!-- /wp:image -->
HTML,
            ],
            'image wrapped with anchor' => [
                <<<'HTML'
<a href="https://alley.com/"><img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"></a>
HTML,
                <<<'HTML'
<!-- wp:image {"lightbox":{"enabled":false},"id":{{IMAGE_ID}},"sizeSlug":"full","linkDestination":"custom"} -->
<figure class="wp-block-image size-full"><a href="https://alley.com/"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></a></figure>
<!-- /wp:image -->
HTML,
            ],
            'image wrapped with paragraph' => [
                <<<'HTML'
<p>Content before image. <img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"> Content after image.</p>
HTML,
                <<<'HTML'
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
                <<<'HTML'
<p>Content before image. <a href="https://alley.com/"><img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"></a> Content after image.</p>
HTML,
                <<<'HTML'
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
                <<<'HTML'
<img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text">
HTML,
                <<<'HTML'
<!-- wp:image {"id":{{IMAGE_ID}},"sizeSlug":"full"} -->
<figure class="wp-block-image size-full"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></figure>
<!-- /wp:image -->
HTML,
            ],
            'image with srcset and sizes attributes' => [
                <<<'HTML'
<img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" srcset="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png 300w" sizes="100vw" alt="Sample alt text">
HTML,
                <<<'HTML'
<!-- wp:image {"id":{{IMAGE_ID}},"sizeSlug":"full"} -->
<figure class="wp-block-image size-full"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></figure>
<!-- /wp:image -->
HTML,
            ],
        ];
    }

    /**
     * Tests that images from imageDataprovider() are sideloaded into the
     * media library via WordPressImageUploader, that exactly one attachment
     * is created, and that the resulting block markup matches the expected
     * output once the real attachment ID and URL are substituted in.
     *
     * @param  string  $html  The source HTML to convert.
     * @param  string  $expected  The expected converted block markup, with
     *                            {{IMAGE_ID}}/{{IMAGE_SRC}} placeholders for the
     *                            real attachment ID/URL.
     */
    #[DataProvider('imageDataprovider')]
    public function test_image(string $html, string $expected)
    {
        $converter = new BlockConverter(
            html: $html,
            uploader: new WordPressImageUploader,
        );
        $block = $converter->convert();

        $this->assertCount(
            expectedCount: 1,
            haystack: $converter->getCreatedAttachmentIds(),
        );

        $attachmentId = $converter->getCreatedAttachmentIds()[0];

        $expected = str_replace(
            search: ['{{IMAGE_ID}}', '{{IMAGE_SRC}}'],
            replace: [$attachmentId, wp_get_attachment_url($attachmentId)],
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
}
