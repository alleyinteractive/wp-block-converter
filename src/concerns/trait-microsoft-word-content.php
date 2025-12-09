<?php
/**
 * Microsoft_Word_Content trait file
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 *
 * @package wp-block-converter
 */

declare(strict_types=1);

namespace Alley\WP\Block_Converter\Concerns;

/**
 * Trait for handling Microsoft Word content detection.
 */
trait Microsoft_Word_Content {
	/**
	 * Flag to enable or disable Microsoft Word content conversion.
	 *
	 * @var bool
	 */
	protected bool $convert_ms_word_content = true;

	/**
	 * Enable or disable Microsoft Word content conversion.
	 *
	 * @param bool $convert Whether to convert Microsoft Word content.
	 * @return static
	 */
	public function should_convert_ms_word_content( bool $convert = true ): static {
		$this->convert_ms_word_content = $convert;

		return $this;
	}
	/**
	 * Determines if the given node contains Microsoft Word content.
	 *
	 * @param \DOMNode $node The DOM node to check.
	 * @return bool True if the node contains Microsoft Word content, false otherwise.
	 */
	protected function is_ms_word_content( \DOMNode $node ): bool {
		if ( $node->nodeType !== XML_ELEMENT_NODE || ! $node instanceof \DOMElement ) {
			return false;
		}

		$html = (string) $node->ownerDocument?->saveHTML( $node );

		// Patterns based on TinyMCE Word filter detection.
		$patterns = [
			'/<font face="Times New Roman"/',
			'/class="?Mso/',
			'/style="[^"]*\bmso-/',
			'/style=\'[^\']*\bmso-/',
			'/w:WordDocument/',
			'/class="OutlineElement"/',
			'/id="?docs-internal-guid-/',
			'/mso-border-alt/',
			'/MsoNormal/',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern . 'i', $html ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Clean MS Word specific styles from a style string.
	 *
	 * @param string $style_string The style attribute value.
	 * @return string The cleaned style string.
	 */
	protected function clean_ms_word_styles( string $style_string ): string {
		// Parse style string into individual properties.
		$styles      = [];
		$style_pairs = explode( ';', $style_string );

		foreach ( $style_pairs as $pair ) {
			$pair = trim( $pair );
			if ( empty( $pair ) ) {
				continue;
			}

			if ( strpos( $pair, ':' ) !== false ) {
				list( $property, $value ) = explode( ':', $pair, 2 );
				$property                 = trim( $property );
				$value                    = trim( $value );

				// Skip MS Word specific properties.
				if ( strpos( $property, 'mso-' ) === 0 ) {
					continue;
				}

				// Skip problematic Word styles.
				$skip_properties = [
					'font-family', // Often contains non-web fonts.
					'font-size', // Usually incorrect from Word.
					'margin',
					'padding',
					'line-height', // Word line heights are often problematic.
					'text-indent',
				];

				if ( in_array( $property, $skip_properties, true ) ) {
					continue;
				}

				// Keep only essential properties.
				$allowed_properties = [
					'color',
					'background-color',
					'font-weight',
					'font-style',
					'text-decoration',
					'text-align',
				];

				if ( in_array( $property, $allowed_properties, true ) ) {
					$styles[ $property ] = $value;
				}
			}
		}

		// Rebuild style string.
		$cleaned_styles = [];
		foreach ( $styles as $property => $value ) {
			$cleaned_styles[] = $property . ': ' . $value;
		}

		return implode( '; ', $cleaned_styles );
	}

	/**
	 * Clean up Microsoft Word formatting from a DOM node.
	 *
	 * @param \DOMNode $node The DOM node to clean up.
	 */
	protected function clean_ms_word_node( \DOMNode $node ): void {
		if ( $node->nodeType !== XML_ELEMENT_NODE || ! $node instanceof \DOMElement ) {
			return;
		}

		// Remove MsoNormal class from class attribute.
		if ( $node->hasAttribute( 'class' ) ) {
			$classes = $node->getAttribute( 'class' );
			$classes = preg_replace( '/\bMsoNormal\b/', '', $classes ) ?? '';
			$classes = trim( preg_replace( '/\s+/', ' ', $classes ) ?? '' );

			if ( empty( $classes ) ) {
				$node->removeAttribute( 'class' );
			} else {
				$node->setAttribute( 'class', $classes );
			}
		}

		// Remove style attribute from ALL elements (as requested - complete removal).
		if ( $node->hasAttribute( 'style' ) ) {
			$node->removeAttribute( 'style' );
		}

		// Remove MS Word specific attributes.
		$ms_word_attributes = [
			'border',
			'mso-border-alt',
			'face', // Often "Times New Roman" from Word.
			'size', // Font size attributes.
			'color', // Color attributes that should be in CSS.
		];
		foreach ( $ms_word_attributes as $attr ) {
			if ( $node->hasAttribute( $attr ) ) {
				$node->removeAttribute( $attr );
			}
		}

		// Remove attributes with MS Word specific values.
		if ( $node->hasAttribute( 'face' ) && $node->getAttribute( 'face' ) === 'Times New Roman' ) {
			$node->removeAttribute( 'face' );
		}

		// Remove MS Word comment and tracking attributes.
		$attributes_to_check = [ 'class', 'id', 'name' ];
		foreach ( $attributes_to_check as $attr ) {
			if ( $node->hasAttribute( $attr ) ) {
				$value = $node->getAttribute( $attr );
				// Remove comment references, tracking changes, and internal GUIDs.
				if ( preg_match( '/^(MsoCommentReference|MsoCommentText|msoDel|docs-internal-guid-)/', $value ) ) {
					$node->removeAttribute( $attr );
				}
			}
		}

		// Convert <i> tags to <em> tags.
		if ( $node->nodeName === 'i' && $node->ownerDocument !== null ) {
			$em = $node->ownerDocument->createElement( 'em' );

			// Copy all attributes except ones we're cleaning.
			if ( $node->hasAttributes() ) {
				foreach ( $node->attributes as $attr ) {
					if ( $attr->nodeName !== 'class' && $attr->nodeName !== 'style' && $attr->nodeValue !== null ) {
						$em->setAttribute( $attr->nodeName, $attr->nodeValue );
					}
				}
			}

			// Move all child nodes to the new em element.
			while ( $node->firstChild ) {
				$em->appendChild( $node->firstChild );
			}

			// Replace the i element with em element.
			if ( $node->parentNode !== null ) {
				$node->parentNode->replaceChild( $em, $node );
				$node = $em;
			}
		}

		// Convert <b> tags to <strong> tags.
		if ( $node->nodeName === 'b' && $node->ownerDocument !== null ) {
			$strong = $node->ownerDocument->createElement( 'strong' );

			// Copy all attributes except ones we're cleaning.
			if ( $node->hasAttributes() ) {
				foreach ( $node->attributes as $attr ) {
					if ( $attr->nodeName !== 'class' && $attr->nodeName !== 'style' && $attr->nodeValue !== null ) {
						$strong->setAttribute( $attr->nodeName, $attr->nodeValue );
					}
				}
			}

			// Move all child nodes to the new strong element.
			while ( $node->firstChild ) {
				$strong->appendChild( $node->firstChild );
			}

			// Replace the b element with strong element.
			if ( $node->parentNode !== null ) {
				$node->parentNode->replaceChild( $strong, $node );
				$node = $strong;
			}
		}

		// Remove Word tracking and comment elements completely.
		if ( in_array( $node->nodeName, [ 'del', 'ins' ], true ) ) {
			// For tracking changes, remove del elements but keep ins content.
			if ( $node->nodeName === 'del' ) {
				$node->parentNode?->removeChild( $node );
				return;
			} else {
				// Unwrap ins elements but keep content.
				while ( $node->firstChild ) {
					$node->parentNode?->insertBefore( $node->firstChild, $node );
				}
				$node->parentNode?->removeChild( $node );
				return;
			}
		}

		// Clean up font tags by removing MS Word specific attributes.
		if ( $node->nodeName === 'font' ) {
			// Remove common Word font attributes but keep the element.
			$font_attrs = [ 'face', 'size', 'color' ];
			foreach ( $font_attrs as $attr ) {
				if ( $node->hasAttribute( $attr ) ) {
					$node->removeAttribute( $attr );
				}
			}
			// If no attributes left, unwrap the font tag.
			if ( ! $node->hasAttributes() ) {
				while ( $node->firstChild ) {
					$node->parentNode?->insertBefore( $node->firstChild, $node );
				}
				$node->parentNode?->removeChild( $node );
				return;
			}
		}

		// Remove <span> tags by unwrapping their content.
		if ( $node->nodeName === 'span' ) {
			// First, recursively clean the children before moving them.
			$children = [];
			foreach ( $node->childNodes as $child ) {
				$children[] = $child;
			}

			foreach ( $children as $child ) {
				$this->clean_ms_word_node( $child );
			}

			// Move all child nodes before the span element.
			while ( $node->firstChild ) {
				$node->parentNode?->insertBefore( $node->firstChild, $node );
			}

			// Remove the empty span element.
			$node->parentNode?->removeChild( $node );
			return; // No need to process children again as they've been processed and moved.
		}

		// Recursively clean child nodes to ensure all nested elements are processed.
		$children = [];
		foreach ( $node->childNodes as $child ) {
			$children[] = $child;
		}

		foreach ( $children as $child ) {
			$this->clean_ms_word_node( $child );
		}
	}
}
