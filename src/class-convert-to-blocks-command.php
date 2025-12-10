<?php
/**
 * Convert_To_Blocks_Command class file
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter;

use Alley\WP_Bulk_Task\Bulk_Task;
use Alley\WP_Bulk_Task\Bulk_Task_Side_Effects;
use Alley\WP_Bulk_Task\Cursor\Memory_Cursor;
use Alley\WP_Bulk_Task\Progress\PHP_CLI_Progress_Bar;
use WP_CLI;
use WP_CLI_Command;

/**
 * WP-CLI command to convert posts to Gutenberg blocks.
 */
class Convert_To_Blocks_Command extends WP_CLI_Command {
	use Bulk_Task_Side_Effects;

	/**
	 * Convert post content from HTML to Gutenberg blocks.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<post-type>]
	 * : The post type to convert. Default: post
	 * ---
	 * default: post
	 * ---
	 *
	 * [--post-status=<post-status>]
	 * : The post status to filter by. Default: publish
	 * ---
	 * default: publish
	 * ---
	 *
	 * [--post-id=<post-id>]
	 * : Comma-separated list of post IDs to convert. If provided, only these posts will be processed.
	 *
	 * [--dry-run]
	 * : If present, no updates will be made. Shows what would be changed.
	 *
	 * [--rewind]
	 * : Resets the cursor so the next time the command is run it will start from the beginning.
	 *
	 * [--sideload-images]
	 * : If present, images will be sideloaded and attached to the post.
	 *
	 * ## EXAMPLES
	 *
	 *     # Convert all published posts to blocks
	 *     $ wp block-converter
	 *
	 *     # Dry run to see what would be converted
	 *     $ wp block-converter --dry-run
	 *
	 *     # Convert a specific post
	 *     $ wp block-converter --post-id=123
	 *
	 *     # Convert custom post type with image sideloading
	 *     $ wp block-converter --post-type=page --sideload-images
	 *
	 *     # Reset the cursor to start from the beginning
	 *     $ wp block-converter --rewind
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$bulk_task = new Bulk_Task(
			'convert_to_blocks',
			new PHP_CLI_Progress_Bar(
				__( 'Converting posts to blocks', 'wp-block-converter' )
			)
		);

		$this->pause_side_effects();

		$default = [
			'post-type'   => 'post',
			'post-status' => 'publish',
		];

		// Confirm if no arguments are provided.
		if ( empty( $assoc_args ) || ( count( $assoc_args ) === count( $default ) && ! array_diff_key( $assoc_args, $default ) ) ) {
			WP_CLI::confirm( __( 'You have not provided any arguments. This will process all published posts. Do you want to continue?', 'wp-block-converter' ) );
		}

		$dry_run         = ! empty( $assoc_args['dry-run'] );
		$sideload_images = ! empty( $assoc_args['sideload-images'] );
		$post_type       = $assoc_args['post-type'] ?? 'post';
		$post_status     = $assoc_args['post-status'] ?? 'publish';

		// Use in-memory cursor for dry run to avoid saving progress.
		if ( $dry_run ) {
			$bulk_task->cursor = new Memory_Cursor();
		} elseif ( ! empty( $assoc_args['rewind'] ) ) {
			$bulk_task->cursor->reset();
			WP_CLI::success( __( 'Rewound the cursor. Run again without the --rewind flag to process posts.', 'wp-block-converter' ) );
			return;
		}

		// Build query arguments.
		$query_args = [
			'post_type'   => $post_type,
			'post_status' => $post_status,
		];

		// If specific post IDs are provided, only process those posts.
		if ( ! empty( $assoc_args['post-id'] ) ) {
			$post_ids               = array_map( 'intval', explode( ',', $assoc_args['post-id'] ) );
			$query_args['post__in'] = $post_ids;
		}

		// Track statistics.
		$stats = [
			'processed' => 0,
			'converted' => 0,
			'skipped'   => 0,
			'errors'    => 0,
		];

		// Run the bulk task.
		$bulk_task->run(
			$query_args,
			function ( $post ) use ( $dry_run, $sideload_images, &$stats ) {
				$stats['processed']++;

				try {
					// Skip if post content is empty.
					if ( empty( $post->post_content ) ) {
						$stats['skipped']++;
						WP_CLI::debug( sprintf( 'Post %d has empty content, skipping.', $post->ID ) );
						return;
					}

					// Skip if content already contains block markers.
					if ( has_blocks( $post->post_content ) ) {
						$stats['skipped']++;
						WP_CLI::debug( sprintf( 'Post %d already has blocks, skipping.', $post->ID ) );
						return;
					}

					// Convert the post content to blocks.
					$converter = new Block_Converter( $post->post_content, $sideload_images );
					$blocks    = $converter->convert();

					if ( $dry_run ) {
						WP_CLI::log( sprintf( 'Would convert post %d (%s)', $post->ID, $post->post_title ) );
						WP_CLI::log( 'Original content length: ' . strlen( $post->post_content ) );
						WP_CLI::log( 'Converted content length: ' . strlen( $blocks ) );

						// Show a preview of the blocks (first 200 characters).
						if ( strlen( $blocks ) > 0 ) {
							WP_CLI::log( 'Preview: ' . substr( $blocks, 0, 200 ) . '...' );
						}
					} else {
						// Update the post content.
						$result = wp_update_post(
							[
								'ID'           => $post->ID,
								'post_content' => $blocks,
							],
							true
						);

						if ( is_wp_error( $result ) ) {
							$stats['errors']++;
							WP_CLI::warning( sprintf( 'Failed to update post %d: %s', $post->ID, $result->get_error_message() ) );
							return;
						}

						// Assign parent to attachments if images were sideloaded.
						if ( $sideload_images ) {
							$converter->assign_parent_to_attachments( $post->ID );
						}

						WP_CLI::debug( sprintf( 'Converted post %d (%s)', $post->ID, $post->post_title ) );
					}

					$stats['converted']++;
				} catch ( \Exception $e ) {
					$stats['errors']++;
					WP_CLI::warning( sprintf( 'Error converting post %d: %s', $post->ID, $e->getMessage() ) );
				}
			}
		);

		$this->resume_side_effects();

		// Display summary.
		WP_CLI::line( '' );
		WP_CLI::success( sprintf(
			'%s %d posts. Converted: %d, Skipped: %d, Errors: %d',
			$dry_run ? 'Would process' : 'Processed',
			$stats['processed'],
			$stats['converted'],
			$stats['skipped'],
			$stats['errors']
		) );

		if ( $dry_run ) {
			WP_CLI::line( 'This was a dry run. Run without --dry-run to make actual changes.' );
		} else {
			$bulk_task->cursor->reset();
			WP_CLI::line( 'Cursor has been reset. Run the command again to reprocess posts if needed.' );
		}
	}
}
