<?php
/**
 * Image_Uploader interface file
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter;

use Exception;

/**
 * Contract for sideloading images referenced in converted HTML.
 *
 * Block_Converter has no built-in sideloading of its own and leaves images
 * untouched by default; passing an implementation of this interface to its
 * constructor (e.g. WordPress_Image_Uploader to sideload into the WordPress
 * media library) is what turns sideloading on. Implementers with no
 * "attachment" concept of their own (e.g. writing directly to a
 * non-WordPress store) can leave get_created_attachment_ids() and
 * assign_parent_to_attachments() as no-ops.
 */
interface Image_Uploader {
    /**
     * Upload (or otherwise resolve) an image, returning its final URL.
     *
     * @throws Exception If the image was not able to be uploaded.
     *
     * @param string $src Image URL.
     * @param string $alt Image alt text.
     * @return string The final image URL.
     */
    public function upload( string $src, string $alt ): string;

    /**
     * Resolve the attachment ID for a previously uploaded image, if known.
     *
     * @param string $url Image URL.
     * @return int|null
     */
    public function attachment_id_for( string $url ): ?int;

    /**
     * Retrieve the attachment IDs created during the conversion.
     *
     * @return array<int>
     */
    public function get_created_attachment_ids(): array;

    /**
     * Assign a parent post ID to the attachments created during the conversion.
     *
     * @param int $parent_post_id Parent post ID.
     */
    public function assign_parent_to_attachments( int $parent_post_id ): void;
}
