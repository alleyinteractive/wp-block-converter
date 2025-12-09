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
		$class_attr = $node->attributes?->getNamedItem( 'class' );

		return $node->nodeType === XML_ELEMENT_NODE
			&& $node->hasAttributes()
			&& $class_attr !== null
			&& $class_attr->nodeValue !== null
			&& strpos( $class_attr->nodeValue, 'MsoNormal' ) !== false;
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

		// Remove style attribute from ALL elements.
		if ( $node->hasAttribute( 'style' ) ) {
			$node->removeAttribute( 'style' );
		}

		// Remove other MS Word specific attributes.
		$ms_word_attributes = [ 'border', 'mso-border-alt' ];
		foreach ( $ms_word_attributes as $attr ) {
			if ( $node->hasAttribute( $attr ) ) {
				$node->removeAttribute( $attr );
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
