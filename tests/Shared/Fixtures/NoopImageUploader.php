<?php
namespace Alley\WP\Block_Converter\Tests\Shared\Fixtures;

use Alley\WP\Block_Converter\Image_Uploader;

/**
 * A trivial Image_Uploader: it doesn't sideload anything or talk to any
 * store, it just marks a URL as "uploaded" and records what it was asked to
 * upload. Used by Concerns\Exercises_Constructor_Callbacks to prove the
 * Image_Uploader extension point works end-to-end independent of
 * WordPress_Image_Uploader — shared between the WordPress and standalone
 * suites since neither this fixture nor the behavior it exercises is
 * WordPress-specific.
 */
class Noop_Image_Uploader implements Image_Uploader {
    /**
     * Images passed to upload(), in call order.
     *
     * @var array<int, array{src: string, alt: string}>
     */
    public array $uploaded = [];

    /**
     * {@inheritDoc}
     */
    public function upload( string $src, string $alt ): string {
        $this->uploaded[] = [
            'src' => $src,
            'alt' => $alt,
        ];

        return $src . '#uploaded';
    }

    /**
     * {@inheritDoc}
     */
    public function attachment_id_for( string $url ): ?int {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function get_created_attachment_ids(): array {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function assign_parent_to_attachments( int $parent_post_id ): void {
        // No-op: this test double has no "attachment" concept of its own.
    }
}
