<?php
/**
 * Listens_For_Attachments trait file
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter\Concerns;

/**
 * Listens for the creation of attachments.
 */
trait Listens_For_Attachments {
	/**
	 * The attachment IDs created during the conversion.
	 *
	 * @var array<int>
	 */
	protected array $created_attachment_ids = [];

	/**
	 * Retrieve the attachment IDs created during the conversion.
	 *
	 * @return array<int>
	 */
	public function get_created_attachment_ids(): array {
		return $this->created_attachment_ids;
	}

	/**
	 * Assign a parent post ID to the created attachments.
	 *
	 * @param int $parent_post_id Parent post ID.
	 */
	public function assign_parent_to_attachments( int $parent_post_id ): void {
		foreach ( $this->get_created_attachment_ids() as $attachment_id ) {
			wp_update_post( [
				'ID'          => $attachment_id,
				'post_parent' => $parent_post_id,
			] );
		}
	}

	/**
	 * Listen for the creation of attachments.
	 */
	public function listen_for_attachment_creation(): void {
		$this->created_attachment_ids = [];

		add_action( 'add_attachment', [ $this, 'track_attachment_creation' ] );
	}

	/**
	 * Detach the attachment creation listener.
	 */
	public function detach_attachment_creation_listener(): void {
		remove_action( 'add_attachment', [ $this, 'track_attachment_creation' ] );
	}

	/**
	 * Track the creation of an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function track_attachment_creation( int $attachment_id ): void {
		$this->created_attachment_ids[] = $attachment_id;
	}
}
