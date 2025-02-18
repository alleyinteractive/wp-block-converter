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
use Exception;
use Mantle\Support\Traits\Macroable;
use RuntimeException;
use Throwable;

/**
 * Converts a DOMDocument to Gutenberg block HTML.
 *
 * Mirrors the `htmlToBlocks()`/`rawHandler()` from the `@wordpress/blocks` package.
 *
 * @todo Improve logging to not silently fail when importing images.
 */
class Block_Converter {
	use Concerns\Listens_For_Attachments, Macroable {
		__call as macro_call;
	}

	/**
	 * Setup the class.
	 *
	 * @throws RuntimeException If WordPress is not loaded.
	 *
	 * @param string $html The HTML to parse.
	 */
	public function __construct( public string $html ) {
		if ( ! function_exists( 'do_action' ) ) {
			throw new RuntimeException( 'WordPress must be loaded to use the Block_Converter class.' );
		}
	}

	/**
	 * Convert HTML to Gutenberg blocks.
	 *
	 * @return string The HTML.
	 */
	public function convert(): string {
		$this->listen_for_attachment_creation();

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

			// Merge the block into the HTML collection.
			$html[] = $this->minify_block( (string) $this->convert_node( $node ) );
		}

		$html = implode( "\n\n", $html );

		// Remove empty blocks.
		$html = $this->remove_empty_blocks( $html );

		/**
		 * Content converted into blocks.
		 *
		 * @since 1.0.0
		 *
		 * @param string      $html    HTML converted into Gutenberg blocks.
		 * @param DOMNodeList $content The original DOMNodeList.
		 */
		$html = trim( (string) apply_filters( 'wp_block_converter_document_html', $html, $content ) );

		$this->detach_attachment_creation_listener();

		return $html;
	}

	/**
	 * Convert a node to a block.
	 *
	 * @param DOMNode $node The node to convert.
	 * @return Block|null
	 */
	public function convert_node( DOMNode $node ): ?Block {
		if ( '#text' === $node->nodeName ) {
			return null;
		}

		if ( static::has_macro( $node->nodeName ) ) {
			$block = static::macro_call( $node->nodeName, [ $node ] );
		} else {
			$block = match ( strtolower( $node->nodeName ) ) {
				'ul' => $this->ul( $node ),
				'ol' => $this->ol( $node ),
				'img' => $this->img( $node ),
				'blockquote' => $this->blockquote( $node ),
				'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $this->h( $node ),
				'p', 'a', 'abbr', 'b', 'code', 'em', 'i', 'strong', 'sub', 'sup', 'span', 'u' => $this->p( $node ),
				'figure' => $this->figure( $node ),
				'br', 'cite', 'source' => null,
				'hr' => $this->separator(),
				default => $this->html( $node ),
			};
		}

		/**
		 * Hook to allow output customizations.
		 *
		 * @since 1.0.0
		 *
		 * @param Block|null $block The generated block object.
		 * @param DOMNode    $node  The node being converted.
		 */
		$block = apply_filters( 'wp_block_converter_block', $block, $node );

		if ( ! $block || ! $block instanceof Block ) {
			return null;
		}

		return $block;
	}

	/**
	 * Sideload any child images of a DOMNode and replace the src with the new URL.
	 *
	 * @param DOMNode $node The node.
	 * @return DOMNode
	 */
	protected function sideload_child_images( DOMNode $node ): void {
		$children = $node->childNodes;

		if ( ! $children->length ) {
			return;
		}

		foreach ( $children as $child_node ) {
			// Skip if the node is not an image or is not an instance of DOMElement.
			if ( 'img' !== $child_node->nodeName || ! $child_node instanceof DOMElement ) {
				// Recursively sideload images in child nodes.
				if ( $child_node->hasChildNodes() ) {
					$this->sideload_child_images( $child_node );
				}

				continue;
			}

			$src = $child_node->getAttribute( 'src' );

			if ( empty( $src ) ) {
				continue;
			}

			try {
				$previous_src = $src;
				$src          = $this->upload_image( $src, $child_node->getAttribute( 'alt' ) );

				if ( $src ) {
					$child_node->setAttribute( 'src', $src );

					// Remove any srcset attributes.
					if ( $child_node->hasAttribute( 'srcset' ) ) {
						$child_node->removeAttribute( 'srcset' );
					}

					// Update the parent node with the new link if the parent
					// node is an anchor.
					if ( 'a' === $node->nodeName && $previous_src === $node->getAttribute( 'href' ) ) {
						$node->setAttribute( 'href', $src );
					}

					/**
					 * Fires after a child image has been sideloaded.
					 *
					 * @since 1.5.0
					 *
					 * @param string  $src        The image source URL.
					 * @param DOMNode $child_node The child node.
					 */
					do_action( 'wp_block_converter_sideloaded_image', $src, $child_node );
				}
			} catch ( Throwable ) { // phpcs:ignore Squiz.Commenting.EmptyCatchComment.Missing, Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Do nothing.
			}
		}
	}

	/**
	 * Convert the children of a node to blocks.
	 *
	 * @param DOMNode $node The node.
	 * @return string The children as blocks.
	 */
	public function convert_with_children( DOMNode $node ): string {
		$children = '';

		// Recursively convert the children of the node.
		foreach ( $node->childNodes as $child ) {
			if ( '#text' === $child->nodeName ) {
				$children .= $child->nodeValue;

				continue;
			}

			// Ensure that the cite tag is not converted to a block.
			if ( 'cite' === strtolower( $child->nodeName ) ) {
				$children .= trim( static::get_node_html( $child ) );
			}

			$child_block = $this->convert_node( $child );

			if ( ! empty( $child_block ) ) {
				$children .= $this->minify_block( (string) $child_block );
			}
		}

		$node->nodeValue = '__CHILDREN__';

		$content = static::get_node_html( $node );

		// Replace the placeholder with the children.
		$content = str_replace( '__CHILDREN__', $children, $content );

		return $content;
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
		// Set the class on the node equal to wp-block-quote.
		if ( $node instanceof DOMElement && empty( $node->getAttribute( 'class' ) ) ) {
			$node->setAttribute( 'class', 'wp-block-quote' );
		}

		$content = $this->convert_with_children( $node );

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
		if ( $this->is_anchor_wrapped_image( $node ) ) {
			return $this->img( $node );
		}

		$this->sideload_child_images( $node );

		$content = static::get_node_html( $node );

		// TODO: Account for Twitter/Facebook embeds being inline links in
		// content and not full embeds.
		if ( ! empty( filter_var( $node->textContent, FILTER_VALIDATE_URL ) ) ) {
			if ( \str_contains( $node->textContent, '//x.com' ) || \str_contains( $node->textContent, '//www.x.com' ) ) {
				$node->textContent = str_replace( 'x.com', 'twitter.com', $node->textContent );
			}

			// Instagram and Facebook embeds require an api key to retrieve oEmbed data.
			if ( \str_contains( $node->textContent, 'instagram.com' ) ) {
				return $this->instagram_embed( $node->textContent );
			}

			if ( \str_contains( $node->textContent, 'facebook.com' ) ) {
				return $this->facebook_embed( $node->textContent );
			}

			// Check if the URL is an oEmbed URL and return the oEmbed block if it is.
			if ( false !== wp_oembed_get( $node->textContent ) ) {
				return $this->oembed( $node->textContent );
			}
		}

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
	 * Create figure blocks.
	 *
	 * This method only supports converting a <figure> block that has either a
	 * <img>, <a> or <figcaption> child. If the <figure> block has other children
	 * the block will be converted to a HTML block.
	 *
	 * @param DOMNode $node The node.
	 * @return Block|null
	 */
	public function figure( DOMNode $node ): ?Block {
		if ( $this->is_supported_figure( $node ) ) {
			$this->sideload_child_images( $node );

			// Ensure it has the "wp-block-image" class.
			if ( $node instanceof DOMElement ) {
				$node->setAttribute( 'class', 'wp-block-image' );
			}

			return new Block(
				block_name: 'image',
				content: static::get_node_html( $node ),
			);
		}

		return $this->html( $node );
	}

	/**
	 * Check if the figure node is supported for conversion.
	 *
	 * @param DOMNode $node The node.
	 * @return bool
	 */
	protected function is_supported_figure( DOMNode $node ): bool {
		$children = $node->childNodes;

		if ( ! $children->length ) {
			return false;
		}

		if ( $children->length > 2 ) {
			return false;
		}

		if ( 2 === $children->length ) {
			if ( 'figcaption' !== $children->item( 1 )->nodeName ) {
				return false;
			}
		}

		// Check if the first child is an <img> or an <a> with an <img> child.
		if ( 'img' === $children->item( 0 )->nodeName || $this->is_anchor_wrapped_image( $children->item( 0 ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the figure node is an anchor wrapped image.
	 *
	 * @param DOMNode $node The node.
	 * @return bool
	 */
	protected function is_anchor_wrapped_image( DOMNode $node ): bool {
		$children = $node->childNodes;

		if ( ! $children->length ) {
			return false;
		}

		if ( 1 === $children->length && 'img' === $children->item( 0 )->nodeName ) {
			return true;
		}

		return false;
	}

	/**
	 * Create ul blocks.
	 *
	 * @param DOMNode $node The node.
	 * @return Block
	 */
	protected function ul( DOMNode $node ): Block {
		$this->sideload_child_images( $node );

		return new Block(
			block_name: 'list',
			content: static::get_node_html( $node ),
		);
	}

	/**
	 * Create img block.
	 *
	 * Supports being passed a element that is a <img> or a parent element that
	 * contains an <img>. If it is passed a parent element that contains an
	 * <img> tag, the resulting block will preserve the parent element and wrap
	 * it in a <figure> tag.
	 *
	 * @param DOMElement|DOMNode $element The node.
	 * @return Block|null
	 */
	protected function img( DOMElement|DOMNode $element ): ?Block {
		if ( ! $element instanceof DOMElement ) {
			return null;
		}

		// If the element passed isn't an <img> attempt to find it from the children.
		if ( 'img' !== $element->nodeName ) {
			$image_node = $element->getElementsByTagName( 'img' )->item( 0 );

			// Bail early if the image node is not found.
			if ( ! $image_node || ! $image_node instanceof DOMElement ) {
				return null;
			}
		} else {
			$image_node = $element;
		}

		$image_src = $image_node->getAttribute( 'data-srcset' );
		$alt       = $image_node->getAttribute( 'alt' );

		if ( empty( $image_src ) && ! empty( $image_node->getAttribute( 'src' ) ) ) {
			$image_src = $image_node->getAttribute( 'src' );
		}

		try {
			$image_src = $this->upload_image( $image_src, $alt );

			// Update the image src attribute.
			$image_node->setAttribute( 'src', $image_src );

			// Remove any srcset attributes.
			if ( $image_node->hasAttribute( 'srcset' ) ) {
				$image_node->removeAttribute( 'srcset' );
			}
		} catch ( Exception ) {
			return null;
		}

		if ( empty( $image_src ) ) {
			return null;
		}

		return new Block(
			block_name: 'image',
			content: sprintf(
				'<figure class="wp-block-image">%s</figure>',
				static::get_node_html( $element ),
			),
		);
	}

	/**
	 * Create ol blocks.
	 *
	 * @param DOMNode $node The node.
	 * @return Block
	 */
	protected function ol( DOMNode $node ): Block {
		$this->sideload_child_images( $node );

		return new Block(
			block_name: 'list',
			attributes: [
				'ordered' => true,
			],
			content: static::get_node_html( $node ),
		);
	}

	/**
	 * Create embed blocks.
	 *
	 * @param string $url The URL.
	 * @return Block
	 */
	protected function oembed( string $url ): Block {
		// This would probably be better as an internal request to /wp-json/oembed/1.0/proxy?url=...
		$data = _wp_oembed_get_object()->get_data( $url, [] );

		$aspect_ratio = '';
		if ( ! empty( $data->height ) && ! empty( $data->width ) && is_numeric( $data->height ) && is_numeric( $data->width ) ) {
			if ( 1.78 === round( $data->width / $data->height, 2 ) ) {
				$aspect_ratio = '16-9';
			}
			if ( 1.33 === round( $data->width / $data->height, 2 ) ) {
				$aspect_ratio = '4-3';
			}
		}

		$atts = [
			'url'              => $url,
			'type'             => $data->type,
			'providerNameSlug' => sanitize_title( $data->provider_name ),
			'responsive'       => true,
		];

		if ( ! empty( $aspect_ratio ) ) {
			$aspect_ratio      = sprintf( 'wp-embed-aspect-%s wp-has-aspect-ratio', $aspect_ratio );
			$atts['className'] = $aspect_ratio;
		}

		return new Block(
			block_name: 'embed',
			attributes: $atts,
			content: sprintf(
				'<figure class="wp-block-embed is-type-%s is-provider-%s wp-block-embed-%s%s"><div class="wp-block-embed__wrapper">
				%s
				</div></figure>',
				$data->type,
				sanitize_title( $data->provider_name ),
				sanitize_title( $data->provider_name ),
				$aspect_ratio ? ' ' . $aspect_ratio : '',
				$url
			),
		);
	}

	/**
	 * Create Instagram embed blocks.
	 *
	 * @param string $url The URL.
	 * @return Block
	 */
	protected function instagram_embed( string $url ): Block {
		$atts = [
			'url'              => $url,
			'type'             => 'rich',
			'providerNameSlug' => 'instagram',
			'responsive'       => true,
		];

		return new Block(
			block_name: 'embed',
			attributes: $atts,
			content: sprintf(
				'<figure class="wp-block-embed is-type-rich is-provider-instagram wp-block-embed-instagram"><div class="wp-block-embed__wrapper">
				%s
				</div></figure>',
				$url
			),
		);
	}

	/**
	 * Create Instagram embed blocks.
	 *
	 * @param string $url The URL.
	 * @return Block
	 */
	protected function facebook_embed( string $url ): Block {
		$atts = [
			'url'              => $url,
			'type'             => 'rich',
			'providerNameSlug' => 'embed-handler',
			'responsive'       => true,
			'previewable'      => false,
		];

		return new Block(
			block_name: 'embed',
			attributes: $atts,
			content: sprintf(
				'<figure class="wp-block-embed is-type-rich is-provider-embed-handler wp-block-embed-embed-handler"><div class="wp-block-embed__wrapper">
				%s
				</div></figure>',
				$url
			),
		);
	}

	/**
	 * Create separator blocks.
	 *
	 * @return Block
	 */
	protected function separator(): Block {
		return new Block(
			block_name: 'separator',
			content: '<hr class="wp-block-separator has-alpha-channel-opacity"/>'
		);
	}

	/**
	 * Create HTML blocks.
	 *
	 * @param DOMNode $node The node.
	 * @return Block|null
	 */
	protected function html( DOMNode $node ): ?Block {
		$this->sideload_child_images( $node );

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
	 * **Note:** This method converts the node to HTML and then gets the nodes.
	 * It cannot be use for DOMNode object modification.
	 *
	 * @deprecated Not used by the library. Will be removed in a future release.
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
		if ( \str_contains( $block, 'wp-block-embed' ) ) {
			$pattern = '/(\h){2,}/s';
		} else {
			$pattern = '/(\s){2,}/s';
		}
		if ( preg_match( $pattern, $block ) === 1 ) {
			return preg_replace( $pattern, '', $block );
		}

		return $block;
	}

	/**
	 * Quick way to remove all URL arguments.
	 *
	 * @param string $url URL.
	 *
	 * @return string A reconstructed image URL containing only the scheme, host, port, and path.
	 */
	public function remove_image_args( $url ): string {
		$url_parts = wp_parse_url( $url );
		$scheme    = $url_parts['scheme'] ?? 'https';
		$host      = $url_parts['host'] ?? '';
		$port      = ! empty( $url_parts['port'] ) ? ':' . $url_parts['port'] : '';
		$path      = $url_parts['path'] ?? '';

		// Ensure we have enough parts to construct a valid URL.
		$sanitized_url = '';
		if ( ! empty( $scheme ) && ! empty( $host ) && ! empty( $path ) ) {
			$sanitized_url = sprintf( '%s://%s%s%s', $scheme, $host, $port, $path );
		}

		/**
		 * Allow the reconstructed URL to be filtered before being returned.
		 *
		 * @param string $sanitized_url The reconstructed URL.
		 * @param string $original_url  The original URL before sanitization was applied.
		 */
		return apply_filters( 'wp_block_converter_sanitized_image_url', $sanitized_url, $url );
	}

	/**
	 * Upload image.
	 *
	 * @param string $src Image url.
	 * @param string $alt Image alt.
	 *
	 * @throws Exception If the image was not able to be created.
	 *
	 * @return string The WordPress image URL.
	 */
	public function upload_image( string $src, string $alt ): string {
		// Remove all image arguments.
		$src = $this->remove_image_args( $src );

		return (string) wp_get_attachment_url( create_or_get_attachment_from_url( $src, [ 'alt' => $alt ] ) );
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
