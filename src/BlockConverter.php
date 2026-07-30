<?php
/**
 * Block_Converter class file
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter;

use Closure;
use Dom\Element;
use Dom\HTMLCollection;
use Dom\HTMLDocument;
use Dom\Node;
use Exception;
use Illuminate\Support\Traits\Macroable;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Converts a Dom\HTMLDocument to Gutenberg block HTML.
 *
 * Mirrors the `htmlToBlocks()`/`rawHandler()` from the `@wordpress/blocks` package.
 */
class Block_Converter {
    use Concerns\Microsoft_Word_Content;
    use Macroable;

    /**
     * Known oEmbed provider matchers, in priority order.
     *
     * Reshapes a subset of WordPress core's built-in oEmbed provider list
     * (`WP_oEmbed::$providers` in `wp-includes/class-wp-oembed.php`) into
     * the block editor's embed variation shape, hardcoded with no HTTP
     * request involved — the same approach this library already used for
     * Twitter/Instagram/Facebook. Parameters that can vary per URL and
     * would normally only be known from a live oEmbed response (e.g. the
     * exact aspect ratio of a given video) are approximated with a fixed
     * default per provider rather than fetched.
     *
     * @var array<int, array{pattern: string, slug: string, type: string, aspect_ratio?: string, extra_attributes?: array<string, mixed>}>
     */
    private const OEMBED_PROVIDERS = [
        [
            // YouTube Shorts are consistently vertical, unlike standard
            // YouTube videos, so this must be matched before the general
            // youtube.com pattern below.
            'pattern'      => '#https?://(www\.)?youtube\.com/shorts#i',
            'slug'         => 'youtube',
            'type'         => 'video',
            'aspect_ratio' => '9-16',
        ],
        [
            'pattern'      => '#https?://(www\.)?youtube\.com/(watch|playlist)#i',
            'slug'         => 'youtube',
            'type'         => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern'      => '#https?://youtu\.be/#i',
            'slug'         => 'youtube',
            'type'         => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern'      => '#https?://(www\.|player\.)?vimeo\.com/#i',
            'slug'         => 'vimeo',
            'type'         => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern'      => '#https?://(www\.)?dailymotion\.com/#i',
            'slug'         => 'dailymotion',
            'type'         => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern'      => '#https?://dai\.ly/#i',
            'slug'         => 'dailymotion',
            'type'         => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern' => '#https?://(www\.|vm\.|vt\.)?tiktok\.com/#i',
            'slug'    => 'tiktok',
            'type'    => 'video',
        ],
        [
            'pattern'      => '#https?://wordpress\.tv/#i',
            'slug'         => 'wordpress-tv',
            'type'         => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern'      => '#https?://videopress\.com/v/#i',
            'slug'         => 'videopress',
            'type'         => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern' => '#https?://(www\.)?flickr\.com/#i',
            'slug'    => 'flickr',
            'type'    => 'rich',
        ],
        [
            'pattern' => '#https?://flic\.kr/#i',
            'slug'    => 'flickr',
            'type'    => 'rich',
        ],
        [
            'pattern' => '#https?://((m|www)\.)?soundcloud\.com/#i',
            'slug'    => 'soundcloud',
            'type'    => 'rich',
        ],
        [
            'pattern' => '#https?://(open|play)\.spotify\.com/#i',
            'slug'    => 'spotify',
            'type'    => 'rich',
        ],
        [
            'pattern' => '#https?://(www\.)?slideshare\.net/#i',
            'slug'    => 'slideshare',
            'type'    => 'rich',
        ],
        [
            'pattern' => '#https?://(www\.)?scribd\.com/#i',
            'slug'    => 'scribd',
            'type'    => 'rich',
        ],
        [
            'pattern' => '#https?://(www\.)?reddit\.com/r/[^/]+/comments/#i',
            'slug'    => 'reddit',
            'type'    => 'rich',
        ],
        [
            'pattern' => '#https?://(www\.)?imgur\.com/#i',
            'slug'    => 'imgur',
            'type'    => 'rich',
        ],
        [
            'pattern' => '#https?://(www\.)?twitter\.com/\w{1,15}/status(es)?/#i',
            'slug'    => 'x',
            'type'    => 'rich',
        ],
        [
            'pattern' => '#https?://(www\.)?instagram\.com/#i',
            'slug'    => 'instagram',
            'type'    => 'rich',
        ],
        [
            'pattern'          => '#https?://(www\.)?facebook\.com/#i',
            'slug'             => 'facebook',
            'type'             => 'rich',
            'extra_attributes' => [ 'previewable' => false ],
        ],
    ];

    /**
     * Setup the class.
     *
     * @param string               $html                     The HTML to parse.
     * @param LoggerInterface|null $logger            The logger to use.
     * @param Closure|null         $on_skip_minify_block     Called with ( bool $skip_minify_block, string $block, Node $node ): bool
     *                                                        to decide whether a block should skip minification.
     * @param Closure|null         $on_document_html         Called with ( string $html, HTMLCollection $content ): string
     *                                                        to filter the final converted HTML for the whole document.
     * @param Closure|null         $on_block                 Called with ( ?Block $block, Node $node ): ?Block to filter
     *                                                        each generated block.
     * @param Closure|null         $on_pre_sideload_image    Called with ( bool $pre, string $src, Node $child_node,
     *                                                        Block_Converter $converter ): bool to decide whether a given
     *                                                        image should be sideloaded.
     * @param Closure|null         $on_sideloaded_image      Called with ( string $src, Node $child_node ): void after an
     *                                                        image has been sideloaded.
     * @param Closure|null         $on_sanitized_image_url   Called with ( string $sanitized_url, string $url ): string to
     *                                                        filter the reconstructed image URL used for sideloading.
     * @param Image_Uploader|null  $uploader                 The image uploader to use for sideloading images. Images are
     *                                                        left untouched (no sideloading) unless an uploader is
     *                                                        supplied here — e.g. `new WordPress_Image_Uploader()` to
     *                                                        sideload into the WordPress media library.
     */
    public function __construct(
        public string $html,
        protected ?LoggerInterface $logger = null,
        protected ?Closure $on_skip_minify_block = null,
        protected ?Closure $on_document_html = null,
        protected ?Closure $on_block = null,
        protected ?Closure $on_pre_sideload_image = null,
        protected ?Closure $on_sideloaded_image = null,
        protected ?Closure $on_sanitized_image_url = null,
        protected ?Image_Uploader $uploader = null,
    ) {
    }

    /**
     * Magic function to convert to a string.
     */
    public function __toString(): string {
        return $this->convert();
    }

    /**
     * Get nodes from a specific tag.
     *
     * **Note:** This method converts the node to HTML and then gets the nodes.
     * It cannot be use for Node object modification.
     *
     * @deprecated Not used by the library. Will be removed in a future release.
     *
     * @param Node   $node The current Node.
     * @param string $tag The tag to search for.
     * @return HTMLCollection<Element> The raw HTML.
     */
    public static function get_nodes( Node $node, $tag ) {
        return static::get_node_tag_from_html(
            static::get_node_html( $node ),
            $tag
        );
    }

    /**
     * Get the raw HTML from a Node node.
     *
     * @param Node $node The current Node.
     * @return string The raw HTML.
     */
    public static function get_node_html( Node $node ): string {
        // Remove HTML comment nodes from the children.
        if ( $node->hasChildNodes() ) {
            foreach ( iterator_to_array( $node->childNodes ) as $child ) {
                if ( $child->nodeType === XML_COMMENT_NODE ) {
                    $node->removeChild( $child );
                    continue;
                }

                // Remove any newline text nodes.
                if ( "\\n" === trim( (string) $child->nodeValue ) ) {
                    $node->removeChild( $child );
                    continue;
                }
            }
        }

        // Clear out any empty paragraph tags.
        if ( 'p' === strtolower( $node->nodeName ) && empty( trim( (string) $node->textContent ) ) ) {
            return '';
        }

        $owner_document = $node->ownerDocument;

        return $owner_document instanceof HTMLDocument ? $owner_document->saveHtml( $node ) : '';
    }

    /**
     * Get the HTML content.
     *
     * @param string $html The HTML content.
     * @param string $tag The tag to search for.
     * @return HTMLCollection<Element> The list of Elements.
     */
    public static function get_node_tag_from_html( $html, $tag = 'body' ) {
        $dom = HTMLDocument::createFromString( $html, LIBXML_NOERROR, 'UTF-8' );

        return $dom->getElementsByTagName( $tag );
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
            if ( '#text' === $node->nodeName || $node->nodeType === XML_COMMENT_NODE ) {
                continue;
            }

            $block = (string) $this->convert_node( $node );

            $skip_minify_block = false;

            if ( 'pre' === strtolower( $node->nodeName ) ) {
                $skip_minify_block = true;
            }

            // Allow the caller to decide whether this block should skip minification.
            $skip_minify_block = (bool) $this->apply( $this->on_skip_minify_block, $skip_minify_block, $block, $node );

            if ( ! $skip_minify_block ) {
                $block = $this->minify_block( $block );
            }

            $html[] = $block;
        }

        $html = implode( "\n\n", $html );

        // Remove empty blocks.
        $html = $this->remove_empty_blocks( $html );

        // Allow the caller to filter the fully converted HTML for the whole document.
        $filtered_html = $this->apply( $this->on_document_html, $html, $content );
        $html          = trim( is_string( $filtered_html ) ? $filtered_html : $html );

        return $html;
    }

    /**
     * Retrieve the attachment IDs created while sideloading images during the
     * conversion, if any. Empty if sideloading was never enabled.
     *
     * @return array<int>
     */
    public function get_created_attachment_ids(): array {
        return $this->uploader?->get_created_attachment_ids() ?? [];
    }

    /**
     * Assign a parent post ID to the attachments created during the
     * conversion. No-op if sideloading was never enabled.
     *
     * @param int $parent_post_id Parent post ID.
     */
    public function assign_parent_to_attachments( int $parent_post_id ): void {
        $this->uploader?->assign_parent_to_attachments( $parent_post_id );
    }

    /**
     * Convert a node to a block.
     *
     * @throws RuntimeException If the block is not an instance of Block or null.
     *
     * @param Node $node The node to convert.
     * @return Block|null
     */
    public function convert_node( Node $node ): ?Block {
        if ( '#text' === $node->nodeName ) {
            return null;
        }

        if ( $this->convert_ms_word_content && $this->is_ms_word_content( $node ) ) {
            $this->clean_ms_word_node( $node );
        }

        if ( static::hasMacro( strtolower( $node->nodeName ) ) ) {
            // Registered tag macros may be invoked by an arbitrary string
            // name (e.g. a hyphenated custom element like <special-tag>),
            // which isn't valid PHP method call syntax and can never trigger
            // __call() automatically — so call it directly instead.
            $block = $this->__call( strtolower( $node->nodeName ), [ $node ] );
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
                'pre' => $this->preformatted( $node ),
                default => $this->html( $node ),
            };
        }

        return $this->finalize_block( $block, $node );
    }

    /**
     * Convert the children of a node to blocks.
     *
     * @param Node $node The node.
     * @return string The children as blocks.
     */
    public function convert_with_children( Node $node ): string {
        $children           = '';
        $previous_was_block = false;

        // Recursively convert the children of the node.
        foreach ( $node->childNodes as $child ) {
            if ( '#text' === $child->nodeName ) {
                if ( '' === trim( (string) $child->nodeValue ) ) {
                    continue;
                }

                $children          .= $child->nodeValue;
                $previous_was_block = false;

                continue;
            }

            // Ensure that the cite tag is not converted to a block.
            if ( 'cite' === strtolower( $child->nodeName ) ) {
                $children .= trim( static::get_node_html( $child ) );
            }

            $child_block = $this->convert_node( $child );

            if ( ! empty( $child_block ) ) {
                // Separate consecutive block-level children with a blank
                // line, matching the top-level join in convert(). Non-block
                // content (plain text, <cite>) attaches directly with no gap.
                if ( $previous_was_block ) {
                    $children .= "\n\n";
                }

                $children          .= $this->minify_block( (string) $child_block );
                $previous_was_block = true;
            } else {
                $previous_was_block = false;
            }
        }

        $node->textContent = '__CHILDREN__';

        $content = static::get_node_html( $node );

        // Replace the placeholder with the children.
        $content = str_replace( '__CHILDREN__', $children, $content );

        return $content;
    }

    /**
     * Create figure blocks.
     *
     * This method only supports converting a <figure> block that has either a
     * <img>, <a> or <figcaption> child. If the <figure> block has other children
     * the block will be converted to a HTML block.
     *
     * @param Node $node The node.
     * @return Block|null
     */
    public function figure( Node $node ): ?Block {
        if ( $this->is_supported_figure( $node ) ) {
            return $this->img( $node );
        }

        return $this->html( $node );
    }

    /**
     * Quick way to remove all URL arguments.
     *
     * @param string $url URL.
     *
     * @return string A reconstructed image URL containing only the scheme, host, port, and path.
     */
    public function remove_image_args( $url ): string {
        $url_parts = parse_url( $url );
        $scheme    = $url_parts['scheme'] ?? 'https';
        $host      = $url_parts['host'] ?? '';
        $port      = ! empty( $url_parts['port'] ) ? ':' . $url_parts['port'] : '';
        $path      = $url_parts['path'] ?? '';

        // Ensure we have enough parts to construct a valid URL.
        $sanitized_url = '';
        if ( ! empty( $scheme ) && ! empty( $host ) && ! empty( $path ) ) {
            $sanitized_url = sprintf( '%s://%s%s%s', $scheme, $host, $port, $path );
        }

        // Allow the caller to filter the reconstructed URL before it's returned.
        $filtered_url = $this->apply( $this->on_sanitized_image_url, $sanitized_url, $url );

        return is_string( $filtered_url ) ? $filtered_url : $sanitized_url;
    }

    /**
     * Upload an image via the configured Image_Uploader.
     *
     * @param string $src Image url.
     * @param string $alt Image alt.
     *
     * @throws Exception If the image was not able to be uploaded.
     *
     * @return string The uploaded image URL.
     */
    public function upload_image( string $src, string $alt ): string {
        $src = $this->remove_image_args( $src );

        return $this->uploader?->upload( $src, $alt ) ?? $src;
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
        return \preg_replace( '/(\<\!\-\- wp\:paragraph \-\-\>[\s\n\r]*?\<p\>[\s\n\r]*?\<\/p\>[\s\n\r]*?\<\!\-\- \/wp\:paragraph \-\-\>)/', '', $html ) ?: $html;
    }

    /**
     * Get a node's child nodes, ignoring whitespace-only text nodes (e.g. the
     * indentation/newlines between tags in pretty-printed source HTML), so
     * child-counting checks only see meaningfully different markup.
     *
     * @param Node $node The node.
     * @return Node[]
     */
    protected static function significant_child_nodes( Node $node ): array {
        $children = [];

        foreach ( $node->childNodes as $child ) {
            if ( '#text' === $child->nodeName && '' === trim( (string) $child->nodeValue ) ) {
                continue;
            }

            $children[] = $child;
        }

        return $children;
    }

    /**
     * Remove whitespace-only text node children from an element in place,
     * e.g. the indentation/newlines between tags in pretty-printed source
     * HTML that the block editor's own markup doesn't have.
     *
     * @param Element $element The element.
     * @return void
     */
    protected static function remove_whitespace_only_child_text_nodes( Element $element ): void {
        foreach ( iterator_to_array( $element->childNodes ) as $child ) {
            if ( '#text' === $child->nodeName && '' === trim( (string) $child->nodeValue ) ) {
                $element->removeChild( $child );
            }
        }
    }

    /**
     * Restore the self-closing "/>" syntax on void elements.
     *
     * WordPress intentionally self-closes void elements in its block output
     * (e.g. the image block's `<img .../>`), with no space before the `/>`.
     * `Dom\HTMLDocument::saveHtml()` doesn't do this — it always serializes
     * void elements per the HTML5 spec (e.g. `<embed ...>`, no trailing
     * slash) — so restore that syntax here to match.
     *
     * @param string $html The HTML to restore self-closing syntax in.
     * @return string
     */
    protected static function self_close_void_elements( string $html ): string {
        $void_elements = [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' ];

        return preg_replace_callback(
            '/<(' . implode( '|', $void_elements ) . ')\b[^>]*>/i',
            static function ( array $matches ): string {
                $tag = rtrim( trim( $matches[0], '>' ) );

                return str_ends_with( $tag, '/' ) ? $matches[0] : $tag . '/>';
            },
            $html
        ) ?? $html;
    }

    /**
     * Collapse runs of whitespace in a node's descendant text nodes down to a
     * single space, matching how a browser (and the block editor's rich text
     * fields) render collapsible whitespace.
     *
     * @param Node $node The node to collapse whitespace within.
     * @return void
     */
    protected static function collapse_whitespace( Node $node ): void {
        foreach ( $node->childNodes as $child ) {
            if ( '#text' === $child->nodeName ) {
                $child->nodeValue = preg_replace( '/\s+/', ' ', (string) $child->nodeValue );

                continue;
            }

            if ( $child->hasChildNodes() ) {
                static::collapse_whitespace( $child );
            }
        }
    }

    /**
     * Trim leading whitespace from a node's first descendant text node and
     * trailing whitespace from its last, matching how a browser trims edge
     * whitespace when rendering `white-space: normal` content.
     *
     * @param Node $node The node to trim edge whitespace within.
     * @return void
     */
    protected static function trim_edge_whitespace( Node $node ): void {
        $text_nodes = [];

        static::collect_text_nodes( $node, $text_nodes );

        if ( empty( $text_nodes ) ) {
            return;
        }

        $first            = reset( $text_nodes );
        $first->nodeValue = ltrim( (string) $first->nodeValue );

        $last            = end( $text_nodes );
        $last->nodeValue = rtrim( (string) $last->nodeValue );
    }

    /**
     * Collect a node's descendant text nodes, in document order.
     *
     * @param Node   $node       The node to collect text nodes from.
     * @param Node[] $text_nodes The collected text nodes, passed by reference.
     * @return void
     */
    protected static function collect_text_nodes( Node $node, array &$text_nodes ): void {
        foreach ( $node->childNodes as $child ) {
            if ( '#text' === $child->nodeName ) {
                $text_nodes[] = $child;

                continue;
            }

            if ( $child->hasChildNodes() ) {
                static::collect_text_nodes( $child, $text_nodes );
            }
        }
    }

    /**
     * Invoke an optional hook callback, returning $value unchanged if none is set.
     *
     * Replaces the `apply_filters()` call sites this library used to have,
     * since callers now supply these as constructor callbacks instead of
     * registering WordPress filters.
     *
     * @param Closure|null $callback The optional callback.
     * @param mixed        $value    The value to pass as the callback's first argument.
     * @param mixed        ...$args  Additional arguments to pass to the callback.
     * @return mixed
     */
    protected function apply( ?Closure $callback, mixed $value, mixed ...$args ): mixed {
        return $callback ? $callback( $value, ...$args ) : $value;
    }

    /**
     * Run the `on_block` callback against a generated block.
     *
     * Shared by `convert_node()` and any method that builds `Block` instances
     * outside of the normal per-node dispatch (e.g. splitting a single node
     * into multiple sibling blocks), so every generated block passes through
     * the same customization hook.
     *
     * @throws RuntimeException If the block is not an instance of Block or null.
     *
     * @param mixed $block The generated block, if any.
     * @param Node  $node  The node the block was generated from.
     * @return Block|null
     */
    protected function finalize_block( mixed $block, Node $node ): ?Block {
        if ( null !== $block && ! $block instanceof Block ) {
            throw new RuntimeException( 'Returned block must be an instance of Block or null.' );
        }

        $block = $this->apply( $this->on_block, $block, $node );

        return $block instanceof Block ? $block : null;
    }

    /**
     * Sideload any child images of a Node and replace the src with the new URL.
     *
     * @param Node $node The node.
     * @return void
     */
    protected function sideload_child_images( Node $node ): void {
        if ( ! $this->uploader ) {
            return;
        }

        $children = $node->childNodes;

        if ( ! $children->length ) {
            return;
        }

        foreach ( $children as $child_node ) {
            // Skip if the node is not an image or is not an instance of Element.
            if ( 'img' !== strtolower( $child_node->nodeName ) || ! $child_node instanceof Element ) {
                // Recursively sideload images in child nodes.
                if ( $child_node->hasChildNodes() ) {
                    $this->sideload_child_images( $child_node );
                }

                continue;
            }

            // Allow the caller to decide whether this image should be sideloaded.
            $pre = (bool) $this->apply( $this->on_pre_sideload_image, true, $child_node->getAttribute( 'src' ) ?? '', $child_node, $this );

            // Re-read the src attribute in case it was modified by the callback.
            $src = $child_node->getAttribute( 'src' ) ?? '';

            if ( ! $pre || empty( $src ) ) {
                continue;
            }

            try {
                $previous_src = $src;
                $src          = $this->upload_image( $src, $child_node->getAttribute( 'alt' ) ?? '' );

                if ( $src ) {
                    $child_node->setAttribute( 'src', $src );

                    // Remove any srcset and sizes attributes.
                    if ( $child_node->hasAttribute( 'srcset' ) ) {
                        $child_node->removeAttribute( 'srcset' );
                    }
                    if ( $child_node->hasAttribute( 'sizes' ) ) {
                        $child_node->removeAttribute( 'sizes' );
                    }

                    // Update the parent node with the new link if the parent
                    // node is an anchor.
                    if ( $node instanceof Element && 'a' === strtolower( $node->nodeName ) && $previous_src === $node->getAttribute( 'href' ) ) {
                        $node->setAttribute( 'href', $src );
                    }

                    // Notify the caller that a child image has been sideloaded.
                    if ( $this->on_sideloaded_image ) {
                        ( $this->on_sideloaded_image )( $src, $child_node );
                    }
                }
            } catch ( Throwable $e ) { // phpcs:ignore Squiz.Commenting.EmptyCatchComment.Missing, Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                $this->logger?->error(
                    "Error sideloading image: {$e->getMessage()}",
                    [
                        'exception' => $e,
                        'node'      => $child_node,
                    ]
                );
            }
        }
    }

    /**
     * Create heading blocks.
     *
     * @param Node $node The node.
     * @return Block|null
     */
    protected function h( Node $node ): ?Block {
        if ( $node instanceof Element ) {
            $node->setAttribute( 'class', 'wp-block-heading' );
        }

        $content = static::get_node_html( $node );

        if ( empty( $content ) ) {
            return null;
        }

        return new Block(
            block_name: 'heading',
            attributes: [
                'level' => (int) str_replace( 'h', '', strtolower( $node->nodeName ) ),
            ],
            content: $content,
        );
    }

    /**
     * Create blockquote block.
     *
     * @param Node $node The node.
     * @return Block|null
     */
    protected function blockquote( Node $node ): ?Block {
        // Set the class on the node equal to wp-block-quote.
        if ( $node instanceof Element && empty( $node->getAttribute( 'class' ) ) ) {
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
     * @param Node $node The node.
     * @return Block|null
     */
    protected function p( Node $node ): ?Block {
        if ( $this->is_anchor_wrapped_image( $node ) ) {
            return $this->img( $node );
        }

        if ( 'p' === strtolower( $node->nodeName ) && $this->paragraph_has_inline_image( $node ) ) {
            return $this->split_paragraph_with_inline_images( $node );
        }

        $this->sideload_child_images( $node );
        static::collapse_whitespace( $node );
        static::trim_edge_whitespace( $node );

        $content = static::get_node_html( $node );

        if ( empty( $content ) ) {
            return null;
        }

        $text_content = $node->textContent ?? '';

        // TODO: Account for Twitter/Facebook embeds being inline links in
        // content and not full embeds.
        if ( ! empty( filter_var( $text_content, FILTER_VALIDATE_URL ) ) ) {
            if ( \str_contains( $text_content, '//x.com/' ) || \str_contains( $text_content, '//www.x.com/' ) ) {
                $text_content      = str_replace( [ '//x.com/', '//www.x.com/' ], '//twitter.com/', $text_content );
                $node->textContent = $text_content;
            }

            $embed = $this->embed_for_url( $text_content );

            if ( $embed ) {
                return $embed;
            }
        }

        return new Block(
            block_name: 'paragraph',
            attributes: [],
            content: $content,
        );
    }

    /**
     * Check if a <p> has an <img> (bare or anchor-wrapped) as one of several
     * direct children, i.e. an image sitting inline in running text rather
     * than being the paragraph's sole content.
     *
     * @param Node $node The node.
     * @return bool
     */
    protected function paragraph_has_inline_image( Node $node ): bool {
        foreach ( static::significant_child_nodes( $node ) as $child ) {
            if ( 'img' === strtolower( $child->nodeName ) || $this->is_anchor_wrapped_image( $child ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Split a <p> containing one or more inline images into separate
     * paragraph and image blocks, matching how the block editor splits a
     * pasted inline image out of its surrounding text into its own image
     * block (floated right of the remaining text).
     *
     * @param Node $node The node.
     * @return Block|null
     */
    protected function split_paragraph_with_inline_images( Node $node ): ?Block {
        // Each inline image is sideloaded individually by img() below — not
        // pre-sideloaded here, which would overwrite its src with a local
        // URL before img() ever sees the original remote one.
        static::collapse_whitespace( $node );

        $blocks = [];
        $buffer = '';

        foreach ( $node->childNodes as $child ) {
            if ( 'img' === strtolower( $child->nodeName ) || $this->is_anchor_wrapped_image( $child ) ) {
                $text = trim( $buffer );

                if ( '' !== $text ) {
                    $blocks[] = $this->finalize_block(
                        new Block( block_name: 'paragraph', content: sprintf( '<p>%s</p>', $text ) ),
                        $child,
                    );
                }

                $blocks[] = $this->finalize_block( $this->img( $child, split_from_paragraph: true ), $child );

                $buffer = '';

                continue;
            }

            $buffer .= '#text' === $child->nodeName ? (string) $child->nodeValue : static::get_node_html( $child );
        }

        $text = trim( $buffer );

        if ( '' !== $text ) {
            $blocks[] = $this->finalize_block(
                new Block( block_name: 'paragraph', content: sprintf( '<p>%s</p>', $text ) ),
                $node,
            );
        }

        $blocks = array_filter( $blocks );

        if ( empty( $blocks ) ) {
            return null;
        }

        return new Block(
            block_name: '',
            content: implode(
                "\n\n",
                array_map( fn ( Block $block ) => $this->minify_block( (string) $block ), $blocks )
            ),
        );
    }

    /**
     * Check if the figure node is supported for conversion.
     *
     * @param Node $node The node.
     * @return bool
     */
    protected function is_supported_figure( Node $node ): bool {
        $children = static::significant_child_nodes( $node );

        if ( empty( $children ) || count( $children ) > 2 ) {
            return false;
        }

        if ( 2 === count( $children ) && 'figcaption' !== strtolower( $children[1]->nodeName ) ) {
            return false;
        }

        $first_child = $children[0];

        // Check if the first child is an <img> or an <a> with an <img> child.
        return 'img' === strtolower( $first_child->nodeName ) || $this->is_anchor_wrapped_image( $first_child );
    }

    /**
     * Check if the node's only meaningful child is an <img>, e.g. an <a>
     * wrapping a single image.
     *
     * @param Node|null $node The node.
     * @return bool
     */
    protected function is_anchor_wrapped_image( ?Node $node ): bool {
        if ( ! $node ) {
            return false;
        }

        $children = static::significant_child_nodes( $node );

        return 1 === count( $children ) && 'img' === strtolower( $children[0]->nodeName );
    }

    /**
     * Create ul blocks.
     *
     * @param Node $node The node.
     * @return Block
     */
    protected function ul( Node $node ): Block {
        return $this->list( $node, false );
    }

    /**
     * Create img block.
     *
     * Supports being passed a element that is a <img> or a parent element that
     * contains an <img>. If the parent element is itself a <figure>, its
     * markup is preserved and reused as the block's outer wrapper; for any
     * other parent (e.g. an <a>), the resulting block wraps it in a new
     * <figure> tag.
     *
     * @param Element|Node $element              The node.
     * @param bool         $split_from_paragraph Whether this image was split out from inline
     *                                           paragraph text, in which case the block editor
     *                                           floats it right of the remaining text.
     * @return Block|null
     */
    protected function img( Element|Node $element, bool $split_from_paragraph = false ): ?Block {
        if ( ! $element instanceof Element ) {
            return null;
        }

        // If the element passed isn't an <img> attempt to find it from the children.
        if ( 'img' !== strtolower( $element->nodeName ) ) {
            $image_node = $element->getElementsByTagName( 'img' )->item( 0 );

            // Bail early if the image node is not found.
            if ( ! $image_node ) {
                return null;
            }
        } else {
            $image_node = $element;
        }

        $image_src = $image_node->getAttribute( 'data-srcset' );
        $alt       = $image_node->getAttribute( 'alt' ) ?? '';

        if ( empty( $image_src ) && ! empty( $image_node->getAttribute( 'src' ) ) ) {
            $image_src = $image_node->getAttribute( 'src' );
        }

        if ( empty( $image_src ) ) {
            return null;
        }

        // The block editor's image block always derives responsive sizes
        // from the attachment itself, so a copied-in srcset/sizes pair is
        // never valid on a converted image regardless of sideloading.
        if ( $image_node->hasAttribute( 'srcset' ) ) {
            $image_node->removeAttribute( 'srcset' );
        }

        if ( $image_node->hasAttribute( 'sizes' ) ) {
            $image_node->removeAttribute( 'sizes' );
        }

        $attributes        = [];
        $wrapped_in_anchor = $image_node->parentNode instanceof Element && 'a' === strtolower( $image_node->parentNode->nodeName );

        if ( $this->uploader ) {
            try {
                $image_src = $this->upload_image( $image_src, $alt );

                $image_node->setAttribute( 'src', $image_src );
            } catch ( Exception ) {
                return null;
            }

            $attachment_id = $this->uploader->attachment_id_for( $image_src );

            if ( $attachment_id ) {
                $image_node->setAttribute( 'class', 'wp-image-' . $attachment_id );
            }

            // The block editor's exact attribute shape for a sideloaded
            // image, confirmed against a live WP 7.0 install: a link
            // disables the lightbox and points the image at its custom
            // href, while a bare image split out of paragraph text keeps
            // its (default) "none" link destination explicit and floats
            // right of the remaining text.
            if ( $wrapped_in_anchor ) {
                $attributes['lightbox'] = [ 'enabled' => false ];
            }

            if ( null !== $attachment_id ) {
                $attributes['id'] = $attachment_id;
            }

            $attributes['sizeSlug'] = 'full';

            if ( $wrapped_in_anchor ) {
                $attributes['linkDestination'] = 'custom';
            } elseif ( $split_from_paragraph ) {
                $attributes['linkDestination'] = 'none';
            }

            if ( $split_from_paragraph ) {
                $attributes['align'] = 'right';
            }
        } elseif ( ! ( $this->convert_ms_word_content && $this->is_ms_word_html( $this->html ) ) ) {
            // Without a known attachment, default to the block editor's
            // "Large" image size option — unless this is a Microsoft Word
            // paste, which the editor handles as a distinct import path
            // that doesn't assign a size slug.
            $attributes['sizeSlug'] = 'large';
        }

        if ( empty( $image_src ) ) {
            return null;
        }

        $class = 'wp-block-image'
            . ( $split_from_paragraph ? ' alignright' : '' )
            . ( isset( $attributes['sizeSlug'] ) ? ' size-' . $attributes['sizeSlug'] : '' );

        if ( 'figure' === strtolower( $element->nodeName ) ) {
            $element->setAttribute( 'class', $class );

            $figcaption = $element->getElementsByTagName( 'figcaption' )->item( 0 );

            if ( $figcaption instanceof Element ) {
                $figcaption->setAttribute( 'class', 'wp-element-caption' );
            }

            // Drop the indentation/newline text nodes a pretty-printed
            // <figure> has between its children — the block editor's own
            // figure markup has no whitespace between its child elements.
            static::remove_whitespace_only_child_text_nodes( $element );

            $content = static::get_node_html( $element );
        } else {
            $content = sprintf( '<figure class="%s">%s</figure>', $class, static::get_node_html( $element ) );
        }

        return new Block(
            block_name: 'image',
            attributes: $attributes,
            content: static::self_close_void_elements( $content ),
        );
    }

    /**
     * Create ol blocks.
     *
     * @param Node $node The node.
     * @return Block
     */
    protected function ol( Node $node ): Block {
        return $this->list( $node, true );
    }

    /**
     * Create list blocks, wrapping each <li> child in a nested "list-item"
     * block to match the block editor's markup.
     *
     * @param Node $node    The node.
     * @param bool $ordered Whether the list is ordered (<ol>).
     * @return Block
     */
    protected function list( Node $node, bool $ordered ): Block {
        $this->sideload_child_images( $node );

        if ( $node instanceof Element ) {
            $node->setAttribute( 'class', 'wp-block-list' );
        }

        $items = [];

        foreach ( $node->childNodes as $child ) {
            if ( 'li' !== strtolower( $child->nodeName ) ) {
                continue;
            }

            $items[] = (string) $this->list_item( $child );
        }

        $node->textContent = '__CHILDREN__';

        $content = str_replace( '__CHILDREN__', implode( "\n\n", $items ), static::get_node_html( $node ) );

        return new Block(
            block_name: 'list',
            attributes: $ordered ? [ 'ordered' => true ] : [],
            content: $content,
        );
    }

    /**
     * Create list-item blocks for a <li>.
     *
     * @param Node $node The node.
     * @return Block
     */
    protected function list_item( Node $node ): Block {
        return new Block(
            block_name: 'list-item',
            content: $this->convert_with_children( $node ),
        );
    }

    /**
     * Create an embed block for a URL matching a known oEmbed provider.
     *
     * @param string $url The URL.
     * @return Block|null
     */
    protected function embed_for_url( string $url ): ?Block {
        foreach ( self::OEMBED_PROVIDERS as $provider ) {
            if ( ! preg_match( $provider['pattern'], $url ) ) {
                continue;
            }

            $attributes = [
                'url'              => $url,
                'type'             => $provider['type'],
                'providerNameSlug' => $provider['slug'],
                'responsive'       => true,
            ];

            foreach ( $provider['extra_attributes'] ?? [] as $key => $value ) {
                $attributes[ $key ] = $value;
            }

            $class_name = '';

            if ( ! empty( $provider['aspect_ratio'] ) ) {
                $class_name              = sprintf( 'wp-embed-aspect-%s wp-has-aspect-ratio', $provider['aspect_ratio'] );
                $attributes['className'] = $class_name;
            }

            return new Block(
                block_name: 'embed',
                attributes: $attributes,
                content: sprintf(
                    '<figure class="wp-block-embed is-type-%s is-provider-%s wp-block-embed-%s%s"><div class="wp-block-embed__wrapper">
                    %s
                    </div></figure>',
                    $provider['type'],
                    $provider['slug'],
                    $provider['slug'],
                    $class_name ? ' ' . $class_name : '',
                    $url
                ),
            );
        }

        return null;
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
     * Create preformatted blocks.
     *
     * @param Node $node The node.
     * @return Block|null
     */
    protected function preformatted( Node $node ): ?Block {
        $content = trim( (string) $node->textContent );

        if ( empty( $content ) ) {
            return null;
        }

        return new Block(
            block_name: 'preformatted',
            content: sprintf(
                '<pre class="wp-block-preformatted">%s</pre>',
                str_replace( "\n", '<br>', htmlspecialchars( $content, ENT_NOQUOTES ) ),
            ),
        );
    }

    /**
     * Create HTML blocks.
     *
     * @param Node $node The node.
     * @return Block|null
     */
    protected function html( Node $node ): ?Block {
        $this->sideload_child_images( $node );

        // Get the raw HTML.
        $html = static::get_node_html( $node );

        if ( empty( $html ) ) {
            return null;
        }

        return new Block(
            block_name: 'html',
            content: static::self_close_void_elements( $html ),
        );
    }

    /**
     * Removing whitespace between blocks
     *
     * @param string $block Gutenberg blocks.
     * @return string
     */
    protected function minify_block( string $block ): string {
        if ( \str_contains( $block, 'wp-block-embed' ) ) {
            if ( preg_match( '/(\h){2,}/s', $block ) === 1 ) {
                return preg_replace( '/(\h){2,}/s', '', $block ) ?: '';
            }

            return $block;
        }

        return trim( $block );
    }
}
