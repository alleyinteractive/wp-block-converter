<?php
/**
 * WP Block Converter Helpers
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter;

use WP_Error;

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
 * @param string $meta_key Meta key to store the original URL.
 * @return int|WP_Error Attachment URL/ID, WP_Error otherwise.
 */
function create_or_get_attachment_from_url( string $src, array $args = [], string $meta_key = 'original_url' ): int|WP_Error {
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
		return $attachment_id;
	}

	// Store the original URL for future reference.
	update_post_meta( $attachment_id, $meta_key, $src );

	$postarr = [
		'post_content' => $args['description'] ?? null,
		'post_excerpt' => $args['caption'] ?? null,
		'post_title'   => $args['title'] ?? null,
		'meta_input'   => array_merge(
			(array) ( $args['meta'] ?? [] ),
			[
				'_wp_attachment_image_alt' => $args['alt'] ?? null,
			],
		),
	];

	// Update the rest of the arguments if they were passed.
	if ( ! empty( array_filter( $postarr ) ) ) {
		$postarr['ID'] = $attachment_id;

		\wp_update_post( $postarr );
	}

	return $attachment_id;
}
