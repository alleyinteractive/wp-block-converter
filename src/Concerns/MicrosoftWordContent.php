<?php

/**
 * MicrosoftWordContent trait file
 */

declare(strict_types=1);

namespace Alley\WP\BlockConverter\Concerns;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;

/**
 * Trait for handling Microsoft Word content detection.
 */
trait MicrosoftWordContent
{
    /**
     * Patterns based on TinyMCE Word filter detection.
     *
     * @var string[]
     */
    private const MS_WORD_PATTERNS = [
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

    /**
     * Flag to enable or disable Microsoft Word content conversion.
     */
    protected bool $convertMsWordContent = true;

    /**
     * Enable or disable Microsoft Word content conversion.
     *
     * @param  bool  $convert  Whether to convert Microsoft Word content.
     */
    public function shouldConvertMsWordContent(bool $convert = true): static
    {
        $this->convertMsWordContent = $convert;

        return $this;
    }

    /**
     * Clean up Microsoft Word formatting from a DOM node.
     *
     * @param  Node  $node  The DOM node to clean up.
     */
    protected function cleanMsWordNode(Node $node): void
    {
        if ($node->nodeType !== XML_ELEMENT_NODE || ! $node instanceof Element) {
            return;
        }

        // Remove MsoNormal class from class attribute.
        if ($node->hasAttribute('class')) {
            $classes = $node->getAttribute('class') ?? '';
            $classes = preg_replace('/\bMsoNormal\b/', '', $classes) ?? '';
            $classes = trim(preg_replace('/\s+/', ' ', $classes) ?? '');

            if (empty($classes)) {
                $node->removeAttribute('class');
            } else {
                $node->setAttribute('class', $classes);
            }
        }

        // Remove style attribute from ALL elements (as requested - complete removal).
        if ($node->hasAttribute('style')) {
            $node->removeAttribute('style');
        }

        // Remove MS Word specific attributes.
        $msWordAttributes = [
            'border',
            'mso-border-alt',
            'face', // Often "Times New Roman" from Word.
            'size', // Font size attributes.
            'color', // Color attributes that should be in CSS.
            'type', // Presentational list-style attribute Word adds to <ul>/<ol>.
            'width', // Presentational sizing Word adds to <img> and other elements.
        ];
        foreach ($msWordAttributes as $attr) {
            if ($node->hasAttribute($attr)) {
                $node->removeAttribute($attr);
            }
        }

        // Remove attributes with MS Word specific values.
        if ($node->hasAttribute('face') && $node->getAttribute('face') === 'Times New Roman') {
            $node->removeAttribute('face');
        }

        // Remove MS Word comment and tracking attributes.
        $attributesToCheck = ['class', 'id', 'name'];
        foreach ($attributesToCheck as $attr) {
            if ($node->hasAttribute($attr)) {
                $value = $node->getAttribute($attr) ?? '';
                // Remove comment references, tracking changes, and internal GUIDs.
                if (preg_match('/^(MsoCommentReference|MsoCommentText|msoDel|docs-internal-guid-)/', $value)) {
                    $node->removeAttribute($attr);
                }
            }
        }

        // Convert <i> tags to <em> tags.
        if (strtolower($node->nodeName) === 'i' && $node->ownerDocument !== null) {
            $em = $node->ownerDocument->createElement('em');

            // Copy all attributes except ones we're cleaning.
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attr) {
                    if ($attr->nodeName !== 'class' && $attr->nodeName !== 'style' && $attr->nodeValue !== null) {
                        $em->setAttribute($attr->nodeName, $attr->nodeValue);
                    }
                }
            }

            // Move all child nodes to the new em element.
            while ($node->firstChild) {
                $em->appendChild($node->firstChild);
            }

            // Replace the i element with em element.
            if ($node->parentNode !== null) {
                $node->parentNode->replaceChild($em, $node);
                $node = $em;
            }
        }

        // Convert <b> tags to <strong> tags.
        if (strtolower($node->nodeName) === 'b' && $node->ownerDocument !== null) {
            $strong = $node->ownerDocument->createElement('strong');

            // Copy all attributes except ones we're cleaning.
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attr) {
                    if ($attr->nodeName !== 'class' && $attr->nodeName !== 'style' && $attr->nodeValue !== null) {
                        $strong->setAttribute($attr->nodeName, $attr->nodeValue);
                    }
                }
            }

            // Move all child nodes to the new strong element.
            while ($node->firstChild) {
                $strong->appendChild($node->firstChild);
            }

            // Replace the b element with strong element.
            if ($node->parentNode !== null) {
                $node->parentNode->replaceChild($strong, $node);
                $node = $strong;
            }
        }

        // Remove Word tracking and comment elements completely.
        if (in_array(strtolower($node->nodeName), ['del', 'ins'], true)) {
            // For tracking changes, remove del elements but keep ins content.
            if (strtolower($node->nodeName) === 'del') {
                $node->parentNode?->removeChild($node);

                return;
            } else {
                // Unwrap ins elements but keep content.
                while ($node->firstChild) {
                    $node->parentNode?->insertBefore($node->firstChild, $node);
                }
                $node->parentNode?->removeChild($node);

                return;
            }
        }

        // Clean up font tags by removing MS Word specific attributes.
        if (strtolower($node->nodeName) === 'font') {
            // Remove common Word font attributes but keep the element.
            $fontAttrs = ['face', 'size', 'color'];
            foreach ($fontAttrs as $attr) {
                if ($node->hasAttribute($attr)) {
                    $node->removeAttribute($attr);
                }
            }
            // If no attributes left, unwrap the font tag.
            if (! $node->hasAttributes()) {
                while ($node->firstChild) {
                    $node->parentNode?->insertBefore($node->firstChild, $node);
                }
                $node->parentNode?->removeChild($node);

                return;
            }
        }

        // Remove <span> tags by unwrapping their content.
        if (strtolower($node->nodeName) === 'span') {
            // First, recursively clean the children before moving them.
            $children = [];
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }

            foreach ($children as $child) {
                $this->cleanMsWordNode($child);
            }

            // Move all child nodes before the span element.
            while ($node->firstChild) {
                $node->parentNode?->insertBefore($node->firstChild, $node);
            }

            // Remove the empty span element.
            $node->parentNode?->removeChild($node);

            return; // No need to process children again as they've been processed and moved.
        }

        // Recursively clean child nodes to ensure all nested elements are processed.
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            $this->cleanMsWordNode($child);
        }
    }

    /**
     * Clean MS Word specific styles from a style string.
     *
     * @param  string  $styleString  The style attribute value.
     * @return string The cleaned style string.
     */
    protected function cleanMsWordStyles(string $styleString): string
    {
        // Parse style string into individual properties.
        $styles = [];
        $stylePairs = explode(';', $styleString);

        foreach ($stylePairs as $pair) {
            $pair = trim($pair);
            if (empty($pair)) {
                continue;
            }

            if (strpos($pair, ':') !== false) {
                [$property, $value] = explode(':', $pair, 2);
                $property = trim($property);
                $value = trim($value);

                // Skip MS Word specific properties.
                if (strpos($property, 'mso-') === 0) {
                    continue;
                }

                // Skip problematic Word styles.
                $skipProperties = [
                    'font-family', // Often contains non-web fonts.
                    'font-size', // Usually incorrect from Word.
                    'margin',
                    'padding',
                    'line-height', // Word line heights are often problematic.
                    'text-indent',
                ];

                if (in_array($property, $skipProperties, true)) {
                    continue;
                }

                // Keep only essential properties.
                $allowedProperties = [
                    'color',
                    'background-color',
                    'font-weight',
                    'font-style',
                    'text-decoration',
                    'text-align',
                ];

                if (in_array($property, $allowedProperties, true)) {
                    $styles[$property] = $value;
                }
            }
        }

        // Rebuild style string.
        $cleanedStyles = [];
        foreach ($styles as $property => $value) {
            $cleanedStyles[] = $property.': '.$value;
        }

        return implode('; ', $cleanedStyles);
    }

    /**
     * Determines if the given node contains Microsoft Word content.
     *
     * @param  Node  $node  The DOM node to check.
     * @return bool True if the node contains Microsoft Word content, false otherwise.
     */
    protected function isMsWordContent(Node $node): bool
    {
        if ($node->nodeType !== XML_ELEMENT_NODE || ! $node instanceof Element) {
            return false;
        }

        $ownerDocument = $node->ownerDocument;

        $html = $ownerDocument instanceof HTMLDocument ? $ownerDocument->saveHtml($node) : '';

        return $this->isMsWordHtml($html);
    }

    /**
     * Determines if a raw HTML string looks like Microsoft Word content.
     *
     * @param  string  $html  The HTML to check.
     * @return bool True if the HTML contains Microsoft Word markers, false otherwise.
     */
    protected function isMsWordHtml(string $html): bool
    {
        foreach (self::MS_WORD_PATTERNS as $pattern) {
            if (preg_match($pattern.'i', $html)) {
                return true;
            }
        }

        return false;
    }
}
