<?php
/**
 * Block_Converter class file
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter;

use DOMElement;
use DOMNode;

/**
 * Converts a DOMDocument to Gutenberg block HTML.
 */
class Block_Converter {
	/**
	 * Setup the class.
	 *
	 * @param string $html The HTML to parse.
	 */
	public function __construct( public string $html ) {
	}

	/**
	 * Convert HTML to Gutenberg blocks.
	 *
	 * @return string The HTML.
	 */
	public function convert(): string {
		// Get tags from the html.
		$content = static::get_node_tag_from_html( $this->html );

		// Bail early if is empty.
		if ( empty( $content->item( 0 )->childNodes ) ) {
			return '';
		}

		$html = [];

		foreach ( $content->item( 0 )->childNodes as $node ) {
			if ( '#text' === $node->nodeName ) {
				continue;
			}

			/**
			 * Hook to allow output customizations.
			 *
			 * @since 1.0.0
			 *
			 * @param Block|null $block The generated block object.
			 * @param DOMNode   $node  The node being converted.
			 */
			$tag_block = apply_filters( 'wp_block_converter_block', $this->{$node->nodeName}( $node ), $node );

			// Bail early if is empty.
			if ( empty( $tag_block ) ) {
				continue;
			}

			// Merge the block into the HTML collection.
			$html[] = $this->minify_block( (string) $tag_block );
		}

		$html = implode( "\n\n", $html );

		// Remove empty blocks.
		$html = $this->remove_empty_blocks( $html );

		/**
		 * Content converted into blocks.
		 *
		 * @since 1.0.0
		 *
		 * @param string        $html    HTML converted into Gutenberg blocks.
		 * @param DOMNodeList $content The original DOMNodeList.
		 */
		return trim( (string) apply_filters( 'wp_block_converter_document_html', $html, $content ) );
	}

	/**
	 * Magic function to call parsers for specific HTML tags.
	 *
	 * @param string $name The tag name.
	 * @param array  $arguments The DOMNode.
	 * @return Block|null
	 */
	public function __call( $name, $arguments ): ?Block {
		return match ( $name ) {
			'ul' => $this->ul( $arguments[0] ),
			'ol' => $this->ol( $arguments[0] ),
			'img' => $this->img( $arguments[0] ),
			'blockquote' => $this->blockquote( $arguments[0] ),
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $this->h( $arguments[0] ),
			'p', 'a', 'abbr', 'b', 'code', 'em', 'i', 'strong', 'sub', 'sup', 'span', 'u' => $this->p( $arguments[0] ),
			'br', 'cite', 'source' => null,
			default => $this->html( $arguments[0] ),
		};
	}

	/**
	 * Magic function to convert to a string.
	 */
	public function __toString(): string {
		return $this->convert();
	}

	/**
	 * Create heading blocks.
	 *
	 * @param DOMNode $node The node.
	 * @return Block|null
	 */
	protected function h( DOMNode $node ): ?Block {
		$content = static::get_node_html( $node );

		if ( empty( $content ) ) {
			return null;
		}

		return new Block(
			block_name: 'heading',
			attributes: [
				'level' => absint( str_replace( 'h', '', $node->nodeName ) ),
			],
			content: $content,
		);
	}

	/**
	 * Create blockquote block.
	 *
	 * @param DOMNode $node The node.
	 * @return Block|null
	 */
	protected function blockquote( DOMNode $node ): ?Block {
		$content = static::get_node_html( $node );

		if ( empty( $content ) ) {
			return null;
		}

		return new Block(
			block_name: 'quote',
			attributes: [],
			content: $content,
		);
	}

	/**
	 * Create paragraph blocks.
	 *
	 * @param DOMNode $node The node.
	 * @return Block|null
	 */
	protected function p( DOMNode $node ): ?Block {
		$content = static::get_node_html( $node );

		if ( empty( $content ) ) {
			return null;
		}

		return new Block(
			block_name: 'paragraph',
			attributes: [],
			content: $content,
		);
	}

	/**
	 * Create ul blocks.
	 *
	 * @param DOMNode $node The node.
	 * @return Block
	 */
	protected function ul( DOMNode $node ): Block {
		return new Block(
			block_name: 'list',
			content: static::get_node_html( $node ),
		);
	}

	/**
	 * Create img blocks.
	 *
	 * @param DOMElement|DOMNode $element The node.
	 * @return Block|null
	 */
	protected function img( DOMElement|DOMNode $element ): ?Block {
		if ( ! $element instanceof DOMElement ) {
			return null;
		}

		$image_src = $element->getAttribute( 'data-srcset' );
		$alt       = $element->getAttribute( 'alt' );

		if ( empty( $image_src ) && ! empty( $element->getAttribute( 'src' ) ) ) {
			$image_src = $element->getAttribute( 'src' );
		}

		$image_src = $this->upload_image( $image_src, $alt ?? '' );

		return new Block(
			block_name: 'image',
			content: sprintf(
				'<figure class="wp-block-image"><img src="%s" alt="%s"/></figure>',
				esc_url( $image_src ?? '' ),
				esc_attr( $alt ?? '' ),
			),
		);
	}

	/**
	 * Create ol blocks.
	 *
	 * @param DOMNode $node The node.
	 * @return block
	 */
	protected function ol( DOMNode $node ): Block {
		return new Block(
			block_name: 'list',
			attributes: [
				'ordered' => true,
			],
			content: static::get_node_html( $node ),
		);
	}

	/**
	 * Create HTML blocks.
	 *
	 * @param DOMNode $node The node.
	 * @return Block|null
	 */
	protected function html( DOMNode $node ): ?Block {
		// Get the raw HTML.
		$html = static::get_node_html( $node );

		if ( empty( $html ) ) {
			return null;
		}

		return new Block(
			block_name: 'html',
			content: $html,
		);
	}

	/**
	 * Get nodes from a specific tag.
	 *
	 * @param DOMNode $node The current DOMNode.
	 * @param string  $tag The tag to search for.
	 * @return DOMNodeList The raw HTML.
	 */
	public static function get_nodes( DOMNode $node, $tag ) {
		return static::get_node_tag_from_html(
			static::get_node_html( $node ),
			$tag
		);
	}

	/**
	 * Get the raw HTML from a DOMNode node.
	 *
	 * @param DOMNode $node The current DOMNode.
	 * @return string The raw HTML.
	 */
	public static function get_node_html( DOMNode $node ): string {
		return $node->ownerDocument->saveHTML( $node );
	}

	/**
	 * Get the HTML content.
	 *
	 * @param string $html The HTML content.
	 * @param string $tag The tag to search for.
	 * @return DOMNodeList The list of DOMNodes.
	 */
	public static function get_node_tag_from_html( $html, $tag = 'body' ) {
		$dom = new \DOMDocument();

		$errors = libxml_use_internal_errors( true );

		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );

		libxml_use_internal_errors( $errors );

		return $dom->getElementsByTagName( $tag );
	}

	/**
	 * Removing whitespace between blocks
	 *
	 * @param string $block Gutenberg blocks.
	 * @return string
	 */
	protected function minify_block( $block ) {
		if ( preg_match( '/(\s){2,}/s', $block ) === 1 ) {
			return preg_replace( '/(\s){2,}/s', '', $block );
		}

		return $block;
	}

	/**
	 * Quick way to remove all URL arguments.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public function remove_image_args( $url ): string {
		// Split url.
		$url_parts = wp_parse_url( $url );

		return $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];
	}

	/**
	 * Upload image.
	 *
	 * @param string $src Image url.
	 * @param string $alt Image alt.
	 * @return string
	 */
	public function upload_image( string $src, string $alt ): string {
		// Remove all image arguments.
		$src = $this->remove_image_args( $src );

		return create_or_get_attachment_from_url( $src, [ 'alt' => $alt ] );
	}

	/**
	 * Remove any empty blocks.
	 *
	 * @param string $html The current HTML.
	 * @return string $html The new HTML.
	 */
	public function remove_empty_blocks( string $html ): string {
		$html = str_replace(
			[
// phpcs:disable
'<!-- wp:html -->
<div></div>
<!-- /wp:html -->',
'<!-- wp:paragraph -->
<div> </div>
<!-- /wp:paragraph -->',
'<!-- wp:html -->
<div> </div>
<!-- /wp:html -->',
'<!-- wp:paragraph -->
<div>  </div>
<!-- /wp:paragraph -->',
'<!-- wp:paragraph --><p><br></p><!-- /wp:paragraph -->',
'<!-- wp:paragraph --><p><br><br><br></p><!-- /wp:paragraph -->',
'<!-- wp:paragraph -->
<p><br></p>
<!-- /wp:paragraph -->',
'<!-- wp:html -->
<div> </div>
<!-- /wp:html -->',
'<!-- wp:heading {"level":3} -->
<h3>
                                                        </h3>
<!-- /wp:heading -->',
// phpcs:enable
			],
			'',
			$html
		);

		return $this->remove_empty_p_blocks( $html );
	}

	/**
	 * Remove any empty p blocks.
	 *
	 * @param string $html The current HTML.
	 * @return string $html The new HTML.
	 */
	public function remove_empty_p_blocks( string $html ): string {
		return \preg_replace( '/(\<\!\-\- wp\:paragraph \-\-\>[\s\n\r]*?\<p\>[\s\n\r]*?\<\/p\>[\s\n\r]*?\<\!\-\- \/wp\:paragraph \-\-\>)/', '', $html );
	}
}
