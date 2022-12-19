<?php
/**
 * Block_Converter class file
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter;

/**
 * Converts a DOMDocument to Gutenberg block HTML.
 */
class Block_Converter {
	/**
	 * The HTML to parse.
	 *
	 * @var string
	 */
	public $html = '';

	/**
	 * Setup the class.
	 *
	 * @param string $html The HTML to parse.
	 */
	public function __construct( string $html ) {
		$this->html = $html;
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

		$html = '';

		foreach ( $content->item( 0 )->childNodes as $node ) {
			if ( '#text' === $node->nodeName ) {
				continue;
			}

			/**
			 * Hook to allow output customizations.
			 *
			 * @since 1.0.0
			 *
			 * @param string   $tag_block The HTML tag block.
			 * @param \DOMNode $node      The node.
			 */
			$tag_block = (string) apply_filters( 'wp_block_converter_html_tag', $this->{$node->nodeName}( $node ), $node );

			// Assign to the others.
			$html .= $tag_block;
		}

		// Remove empty blocks.
		$html = $this->remove_empty_blocks( $html );

		// Remove white space first.
		$html = $this->minify_block( $html );

		/**
		 * Content converted into blocks.
		 *
		 * @since 1.0.0
		 *
		 * @param mixed        $html    HTML converted into Gutenberg blocks.
		 * @param \DomNodeList $content The original DOMNodeList.
		 */
		return (string) apply_filters( 'wp_block_converter_html_content', $html, $content );
	}

	/**
	 * Magic function to call parsers for specific HTML tags.
	 *
	 * @param string $name The tag name.
	 * @param array  $arguments The DOMNode.
	 * @return string The HTML.
	 */
	public function __call( $name, $arguments ) {
		switch ( $name ) {
			case 'ul':
				$html = $this->ul( $arguments[0] );
				break;
			case 'ol':
				$html = $this->ol( $arguments[0] );
				break;
			case 'img':
				$html = $this->img( $arguments[0] );
				break;
			case 'blockquote':
				$html = $this->blockquote( $arguments[0] );
				break;
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$html = $this->h( $arguments[0] );
				break;
			case 'p':
			case 'a':
			case 'abbr':
			case 'b':
			case 'code':
			case 'em':
			case 'i':
			case 'strong':
			case 'sub':
			case 'sup':
			case 'span':
			case 'u':
				$html = $this->p( $arguments[0] );
				break;
			case 'br':
			case 'cite':
			case 'source':
				$html = null;
				break;
			default:
				$html = $this->html( $arguments[0] );
				break;
		}

		/**
		 * Specific Gutenberg block.
		 *
		 * @since 1.0.0
		 *
		 * @param mixed $html Specific Gutenberg block.
		 */
		return apply_filters( 'wp_block_converter_html_block', $html );
	}

	/**
	 * Create heading blocks.
	 *
	 * @param \DOMNode $node The node.
	 * @return string The HTML.
	 */
	protected function h( \DOMNode $node ): string {
		$heading_number = str_replace( 'h', '', $node->nodeName );
		return '<!-- wp:heading {"level":' . absint( $heading_number ) . '} -->' . PHP_EOL .
			static::get_node_html( $node ) . PHP_EOL .
			'<!-- /wp:heading -->';
	}

	/**
	 * Create blockquote block.
	 *
	 * @param \DOMNode $node The node.
	 * @return string The HTML.
	 */
	protected function blockquote( \DOMNode $node ): string {
		return '<!-- wp:quote -->' . static::get_node_html( $node ) . '<!-- /wp:quote -->';
	}

	/**
	 * Create paragraph blocks.
	 *
	 * @param \DOMNode $node The node.
	 * @return string The HTML.
	 */
	protected function p( \DOMNode $node ): string {

		// Store the raw HTML.
		$html = static::get_node_html( $node );

		if ( empty( $html ) ) {
			return '';
		}

		$html = '<!-- wp:paragraph -->' . PHP_EOL
		. $html . PHP_EOL
		. '<!-- /wp:paragraph -->';

		// Remove empty paragraph tags.
		return $this->remove_empty_p_blocks( $html );
	}

	/**
	 * Create ul blocks.
	 *
	 * @param \DOMNode $node The node.
	 * @return string The HTML.
	 */
	protected function ul( \DOMNode $node ): string {
		return '<!-- wp:list -->' . PHP_EOL
			. static::get_node_html( $node ) . PHP_EOL
			. '<!-- /wp:list -->';
	}

	/**
	 * Create img blocks.
	 *
	 * @param \DOMNode $node The node.
	 * @return string The HTML.
	 */
	protected function img( \DOMNode $node ): string {
		$image_src = $node->getAttribute( 'data-srcset' );
		$alt       = $node->getAttribute( 'alt' );

		if ( empty( $image_src ) && ! empty( $node->getAttribute( 'src' ) ) ) {
			$image_src = $node->getAttribute( 'src' );
		}

		$image_src = $this->upload_image( $image_src, $alt ?? '' );

		return '<!-- wp:image -->' . PHP_EOL .
			'<figure class="wp-block-image">' . PHP_EOL .
				'<img src="' . esc_url( $image_src ?? '' ) . '" alt="' . esc_attr( $alt ?? '' ) . '"/>' . PHP_EOL .
			'</figure>' . PHP_EOL .
			'<!-- /wp:image -->';
	}

	/**
	 * Create ol blocks.
	 *
	 * @param \DOMNode $node The node.
	 * @return string The HTML.
	 */
	protected function ol( \DOMNode $node ): string {
		return '<!-- wp:list {"ordered":true} -->' . PHP_EOL
			. static::get_node_html( $node ) . PHP_EOL
			. '<!-- /wp:list -->';
	}

	/**
	 * Create HTML blocks.
	 *
	 * @param \DOMNode $node The node.
	 * @return string The HTML.
	 */
	protected function html( \DOMNode $node ): string {

		// Get the raw HTML.
		$html = static::get_node_html( $node );

		if ( empty( $html ) ) {
			return '';
		}

		return '<!-- wp:html -->' . PHP_EOL .
			$html . PHP_EOL .
			'<!-- /wp:html -->';
	}

	/**
	 * Get nodes from a specific tag.
	 *
	 * @param \DOMNode $node The current DOMNode.
	 * @param string   $tag The tag to search for.
	 * @return \DOMNodeList The raw HTML.
	 */
	public static function get_nodes( \DOMNode $node, $tag ) {
		return static::get_node_tag_from_html(
			static::get_node_html( $node ),
			$tag
		);
	}

	/**
	 * Get the raw HTML from a DOMNode node.
	 *
	 * @param \DOMNode $node The current DOMNode.
	 * @return string The raw HTML.
	 */
	public static function get_node_html( \DOMNode $node ): string {
		return $node->ownerDocument->saveHTML( $node );
	}

	/**
	 * Get the HTML content.
	 *
	 * @param string $html The HTML content.
	 * @param string $tag The tag to search for.
	 * @return \DOMNodeList The list of DOMNodes.
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
