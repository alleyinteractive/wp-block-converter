<?php

/**
 * Trait ExercisesConstructorCallbacks
 *
 * @package wp-block-converter
 */

namespace Alley\WP\BlockConverter\Tests\Shared\Concerns;

use Alley\WP\BlockConverter\Block;
use Alley\WP\BlockConverter\BlockConverter;
use Alley\WP\BlockConverter\Tests\Shared\Fixtures\NoopImageUploader;
use Dom\Node;

/**
 * Exercises the constructor-callback hooks (onBlock, onDocumentHtml,
 * onSkipMinifyBlock, onSanitizedImageUrl, onPreSideloadImage,
 * onSideloadedImage) and the ImageUploader contract directly, using a
 * trivial NoopImageUploader rather than WordPressImageUploader — none
 * of this mechanism is WordPress specific, so it's shared between the
 * WordPress and standalone suites rather than duplicated.
 */
trait ExercisesConstructorCallbacks
{
    /**
     * Tests that the onBlock callback can rewrite the content of a single
     * generated block (here, only paragraph blocks) while leaving others
     * (the heading block) untouched.
     */
    public function testOnBlockCanModifyASingleBlock(): void
    {
        $html = <<<HTML
<p>Content to migrate</p>
<h1>Heading 01</h1>
HTML;

        $converter = new BlockConverter(
            html: $html,
            onBlock: function (?Block $block, Node $node) {
                if ($block instanceof Block && 'p' === strtolower($node->nodeName)) {
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
     * Tests that the onDocumentHtml callback can override the entire
     * converted output for the document, while onBlock is still invoked once
     * per top-level node before that override is applied.
     */
    public function testOnDocumentHtmlCanOverrideTheWholeOutput(): void
    {
        $onBlockCalls = 0;

        $converter = new BlockConverter(
            html: '<p>Content to migrate</p><h1>Heading 01</h1>',
            onBlock: function (?Block $block, Node $node) use (&$onBlockCalls) {
                $onBlockCalls++;

                return $block;
            },
            onDocumentHtml: fn () => 'Override',
        );

        $this->assertSame('Override', $converter->convert());
        $this->assertSame(2, $onBlockCalls);
    }

    /**
     * Tests that the onSkipMinifyBlock callback is invoked once per
     * top-level node with the tentative skip-minify flag, the block's HTML,
     * and the source node, and that its return value is honored.
     */
    public function testOnSkipMinifyBlockIsInvokedPerTopLevelNode(): void
    {
        $calls = [];

        $converter = new BlockConverter(
            html: '<p>First</p><p>Second</p>',
            onSkipMinifyBlock: function (bool $skipMinifyBlock, string $block, Node $node) use (&$calls) {
                $calls[] = [ $skipMinifyBlock, $block, $node ];

                return $skipMinifyBlock;
            },
        );

        $converter->convert();

        $this->assertCount(2, $calls);

        foreach ($calls as [ $skipMinifyBlock, $block, $node ]) {
            $this->assertFalse($skipMinifyBlock);
            $this->assertStringContainsString('<!-- wp:paragraph -->', $block);
            $this->assertInstanceOf(Node::class, $node);
        }
    }

    /**
     * Tests that the onSanitizedImageUrl callback can append to the
     * sanitized image URL produced by removeImageArgs().
     */
    public function testOnSanitizedImageUrlFiltersTheReconstructedUrl(): void
    {
        $converter = new BlockConverter(
            html: '<p>Unused</p>',
            onSanitizedImageUrl: fn (string $sanitizedUrl, string $url) => $sanitizedUrl . '?cachebust=1',
        );

        $this->assertSame(
            expected: 'https://example.org/image.jpg?cachebust=1',
            actual: $converter->removeImageArgs('https://example.org/image.jpg?utm_source=foo'),
        );
    }

    /**
     * Tests that onPreSideloadImage is invoked for every child image (and
     * can veto sideloading a specific one), and that onSideloadedImage then
     * fires only for the images that were actually sideloaded, with the
     * vetoed image left untouched in the output.
     */
    public function testOnPreSideloadImageAndOnSideloadedImageAreInvokedForChildImages(): void
    {
        $uploader            = new NoopImageUploader();
        $preSideloadSources  = [];
        $sideloadedSources   = [];

        $converter = new BlockConverter(
            html: <<<HTML
<div>
	<img src="https://example.org/a.jpg" alt="A" />
	<img src="https://example.org/b.jpg" alt="B" />
</div>
HTML,
            onPreSideloadImage: function (bool $pre, string $src, Node $childNode, BlockConverter $converter) use (&$preSideloadSources) {
                $preSideloadSources[] = $src;

                // Skip sideloading the second image only.
                return ! str_contains($src, '/b.jpg');
            },
            onSideloadedImage: function (string $src, Node $childNode) use (&$sideloadedSources) {
                $sideloadedSources[] = $src;
            },
            uploader: $uploader,
        );

        $result = $converter->convert();

        $this->assertSame(
            expected: [ 'https://example.org/a.jpg', 'https://example.org/b.jpg' ],
            actual: $preSideloadSources,
        );
        $this->assertSame(
            expected: [ 'https://example.org/a.jpg#uploaded' ],
            actual: $sideloadedSources,
        );
        $this->assertCount(1, $uploader->uploaded);
        $this->assertStringContainsString('https://example.org/a.jpg#uploaded', $result);
        $this->assertStringContainsString('https://example.org/b.jpg', $result);
        $this->assertStringNotContainsString('https://example.org/b.jpg#uploaded', $result);
    }

    /**
     * Tests that supplying a custom ImageUploader causes an image to be
     * sideloaded through it end-to-end, with the uploader's rewritten source
     * reflected in the resulting image block.
     */
    public function testACustomUploaderSideloadsImagesEndToEnd(): void
    {
        $uploader = new NoopImageUploader();

        $converter = new BlockConverter(
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
     * Tests that getCreatedAttachmentIds() and assignParentToAttachments()
     * proxy through to the configured uploader rather than assuming a
     * WordPress-shaped uploader, using an uploader with no attachment concept
     * of its own to prove neither call throws or requires one.
     */
    public function testGetCreatedAttachmentIdsAndAssignParentProxyToTheUploader(): void
    {
        $uploader = new NoopImageUploader();

        $converter = new BlockConverter(
            html: '<img src="https://example.org/image.jpg" alt="Sample alt text" />',
            uploader: $uploader,
        );
        $converter->convert();

        // NoopImageUploader tracks no attachment IDs of its own — proves
        // these calls proxy through rather than assuming a WordPress-shaped
        // uploader.
        $this->assertSame([], $converter->getCreatedAttachmentIds());

        // Assigning a parent must not throw even though this uploader has no
        // "attachment" concept to assign a parent to.
        $converter->assignParentToAttachments(123);

        $this->addToAssertionCount(1);
    }
}
