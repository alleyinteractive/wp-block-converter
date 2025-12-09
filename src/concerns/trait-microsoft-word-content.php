<?php
/**
 * Microsoft_Word_Content trait file
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
		// Check for MsoNormal class in the node's class attribute.
		return $node->nodeType === XML_ELEMENT_NODE
			&& $node->hasAttributes()
			&& $node->attributes->getNamedItem( 'class' ) !== null
			&& strpos( $node->attributes->getNamedItem( 'class' )->nodeValue, 'MsoNormal' ) !== false;
	}

	/**
	 * Clean up Microsoft Word formatting from a DOM node.
	 *
	 * @param \DOMNode $node The DOM node to clean up.
	 * @return void
	 */
	protected function clean_ms_word_node( \DOMNode $node ): void {
		if ( $node->nodeType !== XML_ELEMENT_NODE ) {
			return;
		}

		/** @var \DOMElement $element */
		$element = $node;

		// Remove MsoNormal class from class attribute.
		if ( $element->hasAttribute( 'class' ) ) {
			$classes = $element->getAttribute( 'class' );
			$classes = preg_replace( '/\bMsoNormal\b/', '', $classes );
			$classes = trim( preg_replace( '/\s+/', ' ', $classes ) );

			if ( empty( $classes ) ) {
				$element->removeAttribute( 'class' );
			} else {
				$element->setAttribute( 'class', $classes );
			}
		}

		// Remove style attribute.
		if ( $element->hasAttribute( 'style' ) ) {
			$element->removeAttribute( 'style' );
		}

		// Convert <b> tags to <strong> tags.
		if ( $element->nodeName === 'b' ) {
			$strong = $element->ownerDocument->createElement( 'strong' );

			// Copy all attributes except ones we're cleaning.
			if ( $element->hasAttributes() ) {
				foreach ( $element->attributes as $attr ) {
					if ( $attr->nodeName !== 'class' && $attr->nodeName !== 'style' ) {
						$strong->setAttribute( $attr->nodeName, $attr->nodeValue );
					}
				}
			}

			// Move all child nodes to the new strong element.
			while ( $element->firstChild ) {
				$strong->appendChild( $element->firstChild );
			}

			// Replace the b element with strong element.
			$element->parentNode->replaceChild( $strong, $element );
			$element = $strong;
		}

		// Remove <span> tags by unwrapping their content.
		if ( $element->nodeName === 'span' ) {
			// Move all child nodes before the span element.
			while ( $element->firstChild ) {
				$element->parentNode->insertBefore( $element->firstChild, $element );
			}

			// Remove the empty span element.
			$element->parentNode->removeChild( $element );
			return; // No need to process children as they've been moved.
		}

		// Recursively clean child nodes.
		$children = [];
		foreach ( $element->childNodes as $child ) {
			$children[] = $child;
		}

		foreach ( $children as $child ) {
			$this->clean_ms_word_node( $child );
		}
	}
}
