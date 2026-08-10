<?php

/**
 * ImageUploader interface file
 *
 * @package wp-block-converter
 */

namespace Alley\WP\BlockConverter;

use Exception;

/**
 * Contract for sideloading images referenced in converted HTML.
 *
 * BlockConverter has no built-in sideloading of its own and leaves images
 * untouched by default; passing an implementation of this interface to its
 * constructor (e.g. WordPressImageUploader to sideload into the WordPress
 * media library) is what turns sideloading on. Implementers with no
 * "attachment" concept of their own (e.g. writing directly to a
 * non-WordPress store) can leave getCreatedAttachmentIds() and
 * assignParentToAttachments() as no-ops.
 */
interface ImageUploader
{

    /**
     * Assign a parent post ID to the attachments created during the conversion.
     *
     * @param int $parentPostId Parent post ID.
     */
    public function assignParentToAttachments(int $parentPostId): void;

    /**
     * Resolve the attachment ID for a previously uploaded image, if known.
     *
     * @param string $url Image URL.
     * @return int|null
     */
    public function attachmentIdFor(string $url): ?int;

    /**
     * Retrieve the attachment IDs created during the conversion.
     *
     * @return array<int>
     */
    public function getCreatedAttachmentIds(): array;
    /**
     * Upload (or otherwise resolve) an image, returning its final URL.
     *
     * @throws Exception If the image was not able to be uploaded.
     *
     * @param string $src Image URL.
     * @param string $alt Image alt text.
     * @return string The final image URL.
     */
    public function upload(string $src, string $alt): string;
}
