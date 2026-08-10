<?php

namespace Alley\WP\BlockConverter\Tests\Shared\Fixtures;

use Alley\WP\BlockConverter\ImageUploader;

/**
 * A trivial ImageUploader: it doesn't sideload anything or talk to any
 * store, it just marks a URL as "uploaded" and records what it was asked to
 * upload. Used by Concerns\ExercisesConstructorCallbacks to prove the
 * ImageUploader extension point works end-to-end independent of
 * WordPressImageUploader — shared between the WordPress and standalone
 * suites since neither this fixture nor the behavior it exercises is
 * WordPress-specific.
 */
class NoopImageUploader implements ImageUploader
{
    /**
     * Images passed to upload(), in call order.
     *
     * @var array<int, array{src: string, alt: string}>
     */
    public array $uploaded = [];

    /**
     * {@inheritDoc}
     */
    public function assignParentToAttachments(int $parentPostId): void
    {
        // No-op: this test double has no "attachment" concept of its own.
    }

    /**
     * {@inheritDoc}
     */
    public function attachmentIdFor(string $url): ?int
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getCreatedAttachmentIds(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function upload(string $src, string $alt): string
    {
        $this->uploaded[] = [
            'src' => $src,
            'alt' => $alt,
        ];

        return $src . '#uploaded';
    }
}
