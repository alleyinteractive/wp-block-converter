<?php
/**
 * WordPress_Image_Uploader class file
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter;

use Exception;
use RuntimeException;

/**
 * Image_Uploader implementation that sideloads images into the WordPress
 * media library.
 *
 * This is the only WordPress-specific code in this library — everything
 * else runs without WordPress loaded. It is not applied automatically;
 * pass an instance to Block_Converter's constructor to enable sideloading
 * via WordPress.
 */
class WordPress_Image_Uploader implements Image_Uploader {
	/**
	 * The attachment IDs created during the conversion.
	 *
	 * @var array<int>
	 */
	protected array $created_attachment_ids = [];

	/**
	 * Setup the class.
	 *
	 * @throws RuntimeException If WordPress is not loaded.
	 */
	public function __construct() {
		if ( ! function_exists( 'do_action' ) ) {
			throw new RuntimeException( 'WordPress must be loaded to use the WordPress_Image_Uploader class.' );
		}
	}

	/**
	 * Upload an image into the WordPress media library, or reuse a
	 * previously sideloaded attachment for the same source URL.
	 *
	 * @throws Exception If the image was not able to be uploaded.
	 *
	 * @param string $src Image URL.
	 * @param string $alt Image alt text.
	 * @return string The WordPress attachment URL.
	 */
	public function upload( string $src, string $alt ): string {
		return (string) wp_get_attachment_url( $this->create_or_get_attachment_from_url( $src, [ 'alt' => $alt ] ) );
	}

	/**
	 * Resolve the attachment ID for a previously sideloaded image URL.
	 *
	 * @param string $url Image URL.
	 * @return int|null
	 */
	public function attachment_id_for( string $url ): ?int {
		$attachment_id = attachment_url_to_postid( $url ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.attachment_url_to_postid_attachment_url_to_postid

		return is_int( $attachment_id ) ? $attachment_id : null;
	}

	/**
	 * Retrieve the attachment IDs created during the conversion.
	 *
	 * @return array<int>
	 */
	public function get_created_attachment_ids(): array {
		return $this->created_attachment_ids;
	}

	/**
	 * Assign a parent post ID to the attachments created during the conversion.
	 *
	 * @param int $parent_post_id Parent post ID.
	 */
	public function assign_parent_to_attachments( int $parent_post_id ): void {
		foreach ( $this->created_attachment_ids as $attachment_id ) {
			wp_update_post(
				[
					'ID'          => $attachment_id,
					'post_parent' => $parent_post_id,
				]
			);
		}
	}

	/**
	 * Create or get an already saved attachment from an external URL.
	 *
	 * @param string $src Image URL.
	 * @param array  $args {
	 *        Arguments for the attachment, optional. Default empty array.
	 *
	 *        @type string      $alt            Alt text.
	 *        @type string      $caption        Caption text.
	 *        @type string      $description    Description text.
	 *        @type array       $meta           Associate array of meta to set.
	 *                                          The value of alt text will
	 *                                          automatically be mapped into
	 *                                          this value and will be
	 *                                          overridden by the alt explicitly
	 *                                          passed into this array.
	 *        @type null|int    $parent_post_id Parent post id.
	 *        @type null|string $title          Title text. Null defaults to the
	 *                                          sanitized filename.
	 * }
	 * @phpstan-param array{
	 *   alt?: string,
	 *   caption?: string,
	 *   description?: string,
	 *   meta?: array<string, string>,
	 *   parent_post_id?: null|int,
	 *   title?: null|string,
	 * } $args
	 * @param string $meta_key Meta key to store the original URL.
	 *
	 * @throws Exception If the image was not able to be uploaded.
	 *
	 * @return int Attachment ID.
	 */
	protected function create_or_get_attachment_from_url( string $src, array $args = [], string $meta_key = 'original_url' ): int {
		$attachment_ids = get_posts( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts
			[
				'fields'           => 'ids',
				'meta_key'         => $meta_key,
				'meta_value'       => $src, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'post_status'      => 'any',
				'post_type'        => 'attachment',
				'posts_per_page'   => 1,
				'suppress_filters' => false,
			]
		);

		if ( ! empty( $attachment_ids ) ) {
			return array_shift( $attachment_ids );
		}

		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$attachment_id = media_sideload_image( $src, $args['parent_post_id'] ?? 0, $args['description'] ?? '', 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			// translators: 1: URL, 2: Error message.
			$message = sprintf( __( 'media_sideload_image failed for URL %1$s; error message: %2$s', 'wp-block-converter' ), $src, $attachment_id->get_error_message() );
			throw new Exception( esc_html( $message ) );
		} elseif ( ! is_int( $attachment_id ) ) {
			// translators: 1: URL.
			$message = sprintf( __( 'media_sideload_image failed for URL %1$s; returned value was not an integer', 'wp-block-converter' ), $src );
			throw new Exception( esc_html( $message ) );
		}

		// Store the original URL for future reference.
		update_post_meta( $attachment_id, $meta_key, $src );

		$postarr = [
			'post_content' => $args['description'] ?? '',
			'post_excerpt' => $args['caption'] ?? '',
			'post_title'   => $args['title'] ?? '',
			'meta_input'   => array_filter(
				array_merge(
					(array) ( $args['meta'] ?? [] ),
					[
						'_wp_attachment_image_alt' => $args['alt'] ?? null,
					],
				),
			),
		];

		// Update the rest of the arguments if they were passed.
		if ( ! empty( array_filter( $postarr ) ) ) {
			$postarr['ID'] = $attachment_id;

			wp_update_post( wp_slash( $postarr ) ); // @phpstan-ignore-line argument.type
		}

		$this->created_attachment_ids[] = $attachment_id;

		return $attachment_id;
	}
}
