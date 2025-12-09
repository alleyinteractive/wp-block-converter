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
		if ( ! class_exists( 'WP_CLI' ) ) {
			$this->markTestSkipped( 'WP-CLI is not available' );
		}

		// Create a test post with HTML content.
		$post_id = static::factory()->post->create( [
			'post_content' => '<p>This is a test paragraph.</p><h2>Test Heading</h2>',
			'post_status'  => 'publish',
		] );

		// Run the command with dry-run to avoid actual changes during test setup.
		$result = $this->artisan( 'block-converter convert-to-blocks', [
			'post-id'  => $post_id,
			'dry-run'  => true,
		] );

		// Verify the post was processed.
		$this->assertSame( 0, $result );
	}

	public function test_skip_posts_with_blocks() {
		if ( ! class_exists( 'WP_CLI' ) ) {
			$this->markTestSkipped( 'WP-CLI is not available' );
		}

		// Create a test post with block content.
		$post_id = static::factory()->post->create( [
			'post_content' => '<!-- wp:paragraph --><p>This is already a block.</p><!-- /wp:paragraph -->',
			'post_status'  => 'publish',
		] );

		// Run the command with dry-run.
		$result = $this->artisan( 'block-converter convert-to-blocks', [
			'post-id'  => $post_id,
			'dry-run'  => true,
		] );

		// Verify the command completed successfully.
		$this->assertSame( 0, $result );
	}

	public function test_skip_empty_posts() {
		if ( ! class_exists( 'WP_CLI' ) ) {
			$this->markTestSkipped( 'WP-CLI is not available' );
		}

		// Create a test post with empty content.
		$post_id = static::factory()->post->create( [
			'post_content' => '',
			'post_status'  => 'publish',
		] );

		// Run the command with dry-run.
		$result = $this->artisan( 'block-converter convert-to-blocks', [
			'post-id'  => $post_id,
			'dry-run'  => true,
		] );

		// Verify the command completed successfully.
		$this->assertSame( 0, $result );
	}

	public function test_convert_by_post_type() {
		if ( ! class_exists( 'WP_CLI' ) ) {
			$this->markTestSkipped( 'WP-CLI is not available' );
		}

		// Create test posts of different types.
		static::factory()->post->create( [
			'post_content' => '<p>Test post content.</p>',
			'post_type'    => 'post',
			'post_status'  => 'publish',
		] );

		static::factory()->post->create( [
			'post_content' => '<p>Test page content.</p>',
			'post_type'    => 'page',
			'post_status'  => 'publish',
		] );

		// Run the command for pages only with dry-run.
		$result = $this->artisan( 'block-converter convert-to-blocks', [
			'post-type' => 'page',
			'dry-run'   => true,
		] );

		// Verify the command completed successfully.
		$this->assertSame( 0, $result );
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
