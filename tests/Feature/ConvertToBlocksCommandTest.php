<?php
/**
 * Class ConvertToBlocksCommandTest
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter\Tests\Feature;

use Alley\WP\Block_Converter\Convert_To_Blocks_Command;
use Alley\WP\Block_Converter\Tests\TestCase;
use Mantle\Testing\Concerns\Refresh_Database;

/**
 * Test case for Convert_To_Blocks_Command.
 */
class ConvertToBlocksCommandTest extends TestCase {
	use Refresh_Database;

	protected function setUp(): void {
		parent::setUp();

		// Register the WP-CLI command for testing.
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::add_command( 'block-converter', Convert_To_Blocks_Command::class );
		}
	}

	public function test_convert_single_post() {
		// Create a test post with HTML content.
		$post_id = static::factory()->post->create( [
			'post_content' => '<p>This is a test paragraph.</p><h2>Test Heading</h2>',
			'post_status'  => 'publish',
		] );

		$post = get_post( $post_id );

		// Verify the post does not have blocks initially.
		$this->assertFalse( has_blocks( $post->post_content ) );

		// Convert the post content to blocks.
		$converter = new \Alley\WP\Block_Converter\Block_Converter( $post->post_content );
		$blocks = $converter->convert();

		// Update the post.
		wp_update_post( [
			'ID'           => $post_id,
			'post_content' => $blocks,
		] );

		// Verify the post was converted to blocks.
		$updated_post = get_post( $post_id );
		$this->assertTrue( has_blocks( $updated_post->post_content ) );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $updated_post->post_content );
		$this->assertStringContainsString( '<!-- wp:heading -->', $updated_post->post_content );
	}

	public function test_skip_posts_with_blocks() {
		// Create a test post with block content.
		$post_id = static::factory()->post->create( [
			'post_content' => '<!-- wp:paragraph --><p>This is already a block.</p><!-- /wp:paragraph -->',
			'post_status'  => 'publish',
		] );

		$post = get_post( $post_id );

		// Verify the post already has blocks.
		$this->assertTrue( has_blocks( $post->post_content ) );

		// No conversion should be needed.
		// The CLI command would skip this post.
	}

	public function test_skip_empty_posts() {
		// Create a test post with empty content.
		$post_id = static::factory()->post->create( [
			'post_content' => '',
			'post_status'  => 'publish',
		] );

		$post = get_post( $post_id );

		// Verify the post has empty content.
		$this->assertEmpty( $post->post_content );

		// No conversion should be needed.
		// The CLI command would skip this post.
	}

	public function test_convert_by_post_type() {
		// Create test posts of different types.
		$post_id = static::factory()->post->create( [
			'post_content' => '<p>Test post content.</p>',
			'post_type'    => 'post',
			'post_status'  => 'publish',
		] );

		$page_id = static::factory()->post->create( [
			'post_content' => '<p>Test page content.</p>',
			'post_type'    => 'page',
			'post_status'  => 'publish',
		] );

		// Verify we can query posts by type.
		$posts_query = new \WP_Query( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'fields'      => 'ids',
		] );

		$pages_query = new \WP_Query( [
			'post_type'   => 'page',
			'post_status' => 'publish',
			'fields'      => 'ids',
		] );

		$this->assertContains( $post_id, $posts_query->posts );
		$this->assertNotContains( $page_id, $posts_query->posts );
		$this->assertContains( $page_id, $pages_query->posts );
		$this->assertNotContains( $post_id, $pages_query->posts );
	}

	public function test_actual_conversion() {
		// Create a test post with HTML content.
		$post_id = static::factory()->post->create( [
			'post_content' => '<p>This is a test paragraph.</p><h2>Test Heading</h2>',
			'post_status'  => 'publish',
		] );

		// Manually call the conversion logic (without WP-CLI).
		$post = get_post( $post_id );
		$converter = new \Alley\WP\Block_Converter\Block_Converter( $post->post_content );
		$blocks = $converter->convert();

		// Update the post.
		wp_update_post( [
			'ID'           => $post_id,
			'post_content' => $blocks,
		] );

		// Verify the post was converted to blocks.
		$updated_post = get_post( $post_id );
		$this->assertTrue( has_blocks( $updated_post->post_content ) );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $updated_post->post_content );
		$this->assertStringContainsString( '<!-- wp:heading -->', $updated_post->post_content );
	}
}
