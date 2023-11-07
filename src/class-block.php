<?php
/**
 * Block class file
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter;

/**
 * Block Transfer Object
 */
class Block {
	/**
	 * Constructor.
	 *
	 * @param string|null $block_name The block name.
	 * @param array       $attributes The block attributes.
	 * @param string|null $content    The block content.
	 */
	public function __construct( public ?string $block_name, public array $attributes = [], public ?string $content = null ) {
	}

	/**
	 * Render the block.
	 */
	public function render(): string {
		return get_comment_delimited_block_content( $this->block_name, $this->attributes, $this->content );
	}

	/**
	 * Convert the block to HTML.
	 */
	public function __toString() {
		return $this->render();
	}
}
