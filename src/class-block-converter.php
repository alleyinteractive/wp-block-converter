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

/**
 * Converts a DOMDocument to Gutenberg block HTML.
 */
class Block_Converter {
	use Macroable {
		__call as macro_call;
	}

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
		if ( static::has_macro( $name ) ) {
			return static::macro_call( $name, $arguments );
		}

		return match ( $name ) {
			'ul' => $this->ul( $arguments[0] ),
			'ol' => $this->ol( $arguments[0] ),
			'img' => $this->img( $arguments[0] ),
			'blockquote' => $this->blockquote( $arguments[0] ),
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $this->h( $arguments[0] ),
			'p', 'a', 'abbr', 'b', 'code', 'em', 'i', 'strong', 'sub', 'sup', 'span', 'u' => $this->p( $arguments[0] ),
			'br', 'cite', 'source' => null,
			'hr' => $this->separator(),
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
			if ( false !== wp_oembed_get( $node->textContent ) ) {
				return $this->embed( $node->textContent );
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

		try {
			$image_src = $this->upload_image( $image_src, $alt );
		} catch ( Exception $e ) {
			return null;
		}

		if ( empty( $image_src ) ) {
			return null;
		}

		return new Block(
			block_name: 'image',
			content: sprintf(
				'<figure class="wp-block-image"><img src="%s" alt="%s"/></figure>',
				esc_url( $image_src ),
				esc_attr( $alt ),
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
	protected function embed( string $url ): Block {
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
	 * @return Blockx
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
