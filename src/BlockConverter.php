<?php

/**
 * BlockConverter class file
 */

namespace Alley\WP\BlockConverter;

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
class BlockConverter
{
    use Concerns\MicrosoftWordContent;
    use Macroable;

    /**
     * Inline-level tags that only carry running text formatting.
     *
     * When one of these is a top-level node being dispatched by
     * `convertNode()`, it's promoted to its own paragraph block (matching
     * how the block editor treats bare inline content pasted at the top
     * level). But when it's a direct child of a node already being walked
     * by `convertWithChildren()` (e.g. an `<a>` inside a `<li>`), it must
     * stay as plain inline HTML rather than being wrapped in a nested
     * block — see `convertWithChildren()`.
     *
     * @var array<int, string>
     */
    private const INLINE_TAGS = ['a', 'abbr', 'b', 'code', 'em', 'i', 'strong', 'sub', 'sup', 'span', 'u'];

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
     * @var array<int, array{
     *     pattern: string,
     *     slug: string,
     *     type: string,
     *     aspect_ratio?: string,
     *     extra_attributes?: array<string, mixed>,
     * }>
     */
    private const OEMBED_PROVIDERS = [
        [
            // YouTube Shorts are consistently vertical, unlike standard
            // YouTube videos, so this must be matched before the general
            // youtube.com pattern below.
            'pattern' => '#https?://(www\.)?youtube\.com/shorts#i',
            'slug' => 'youtube',
            'type' => 'video',
            'aspect_ratio' => '9-16',
        ],
        [
            'pattern' => '#https?://(www\.)?youtube\.com/(watch|playlist)#i',
            'slug' => 'youtube',
            'type' => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern' => '#https?://youtu\.be/#i',
            'slug' => 'youtube',
            'type' => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern' => '#https?://(www\.|player\.)?vimeo\.com/#i',
            'slug' => 'vimeo',
            'type' => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern' => '#https?://(www\.)?dailymotion\.com/#i',
            'slug' => 'dailymotion',
            'type' => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern' => '#https?://dai\.ly/#i',
            'slug' => 'dailymotion',
            'type' => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern' => '#https?://(www\.|vm\.|vt\.)?tiktok\.com/#i',
            'slug' => 'tiktok',
            'type' => 'video',
        ],
        [
            'pattern' => '#https?://wordpress\.tv/#i',
            'slug' => 'wordpress-tv',
            'type' => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern' => '#https?://videopress\.com/v/#i',
            'slug' => 'videopress',
            'type' => 'video',
            'aspect_ratio' => '16-9',
        ],
        [
            'pattern' => '#https?://(www\.)?flickr\.com/#i',
            'slug' => 'flickr',
            'type' => 'rich',
        ],
        [
            'pattern' => '#https?://flic\.kr/#i',
            'slug' => 'flickr',
            'type' => 'rich',
        ],
        [
            'pattern' => '#https?://((m|www)\.)?soundcloud\.com/#i',
            'slug' => 'soundcloud',
            'type' => 'rich',
        ],
        [
            'pattern' => '#https?://(open|play)\.spotify\.com/#i',
            'slug' => 'spotify',
            'type' => 'rich',
        ],
        [
            'pattern' => '#https?://(www\.)?slideshare\.net/#i',
            'slug' => 'slideshare',
            'type' => 'rich',
        ],
        [
            'pattern' => '#https?://(www\.)?scribd\.com/#i',
            'slug' => 'scribd',
            'type' => 'rich',
        ],
        [
            'pattern' => '#https?://(www\.)?reddit\.com/r/[^/]+/comments/#i',
            'slug' => 'reddit',
            'type' => 'rich',
        ],
        [
            'pattern' => '#https?://(www\.)?imgur\.com/#i',
            'slug' => 'imgur',
            'type' => 'rich',
        ],
        [
            'pattern' => '#https?://(www\.)?twitter\.com/\w{1,15}/status(es)?/#i',
            'slug' => 'x',
            'type' => 'rich',
        ],
        [
            'pattern' => '#https?://(www\.)?instagram\.com/#i',
            'slug' => 'instagram',
            'type' => 'rich',
        ],
        [
            'pattern' => '#https?://(www\.)?facebook\.com/#i',
            'slug' => 'facebook',
            'type' => 'rich',
            'extra_attributes' => ['previewable' => false],
        ],
    ];

    /**
     * Setup the class.
     *
     * @param  string  $html  The HTML to parse.
     * @param  LoggerInterface|null  $logger  The logger to use.
     * @param  Closure|null  $onSkipMinifyBlock  Called with ( bool $skipMinifyBlock, string $block,
     *                                           Node $node ): bool to decide whether a block should skip minification.
     * @param  Closure|null  $onDocumentHtml  Called with ( string $html, HTMLCollection $content ):
     *                                        string to filter the final converted HTML for the whole document.
     * @param  Closure|null  $onBlock  Called with ( ?Block $block, Node $node ): ?Block to filter
     *                                 each generated block.
     * @param  Closure|null  $onPreSideloadImage  Called with ( bool $pre, string $src,
     *                                            Node $childNode, BlockConverter $converter ): bool to decide whether a
     *                                            given image should be sideloaded.
     * @param  Closure|null  $onSideloadedImage  Called with ( string $src, Node $childNode ): void
     *                                           after an image has been sideloaded.
     * @param  Closure|null  $onSanitizedImageUrl  Called with ( string $sanitizedUrl, string $url ):
     *                                             string to filter the reconstructed image URL used for sideloading.
     * @param  ImageUploader|null  $uploader  The image uploader to use for sideloading images.
     *                                        Images are left untouched (no sideloading) unless an uploader is
     *                                        supplied here — e.g. `new WordPressImageUploader()` to sideload into
     *                                        the WordPress media library.
     */
    public function __construct(
        public string $html,
        protected ?LoggerInterface $logger = null,
        protected ?Closure $onSkipMinifyBlock = null,
        protected ?Closure $onDocumentHtml = null,
        protected ?Closure $onBlock = null,
        protected ?Closure $onPreSideloadImage = null,
        protected ?Closure $onSideloadedImage = null,
        protected ?Closure $onSanitizedImageUrl = null,
        protected ?ImageUploader $uploader = null,
    ) {}

    /**
     * Magic function to convert to a string.
     */
    public function __toString(): string
    {
        return $this->convert();
    }

    /**
     * Get the raw HTML from a Node node.
     *
     * @param  Node  $node  The current Node.
     * @return string The raw HTML.
     */
    public static function getNodeHtml(Node $node): string
    {
        // Remove HTML comment nodes from the children.
        if ($node->hasChildNodes()) {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child->nodeType === XML_COMMENT_NODE) {
                    $node->removeChild($child);

                    continue;
                }

                // Remove any newline text nodes.
                if (trim((string) $child->nodeValue) === '\\n') {
                    $node->removeChild($child);

                    continue;
                }
            }
        }

        // Clear out any empty paragraph tags.
        if (strtolower($node->nodeName) === 'p' && empty(trim((string) $node->textContent))) {
            return '';
        }

        $ownerDocument = $node->ownerDocument;

        return $ownerDocument instanceof HTMLDocument ? $ownerDocument->saveHtml($node) : '';
    }

    /**
     * Get nodes from a specific tag.
     *
     * **Note:** This method converts the node to HTML and then gets the nodes.
     * It cannot be use for Node object modification.
     *
     * @deprecated Not used by the library. Will be removed in a future release.
     *
     * @param  Node  $node  The current Node.
     * @param  string  $tag  The tag to search for.
     * @return HTMLCollection<Element> The raw HTML.
     */
    public static function getNodes(Node $node, $tag)
    {
        return static::getNodeTagFromHtml(
            static::getNodeHtml($node),
            $tag
        );
    }

    /**
     * Get the HTML content.
     *
     * @param  string  $html  The HTML content.
     * @param  string  $tag  The tag to search for.
     * @return HTMLCollection<Element> The list of Elements.
     */
    public static function getNodeTagFromHtml($html, $tag = 'body')
    {
        $dom = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');

        return $dom->getElementsByTagName($tag);
    }

    /**
     * Assign a parent post ID to the attachments created during the
     * conversion. No-op if sideloading was never enabled.
     *
     * @param  int  $parentPostId  Parent post ID.
     */
    public function assignParentToAttachments(int $parentPostId): void
    {
        $this->uploader?->assignParentToAttachments($parentPostId);
    }

    /**
     * Convert HTML to Gutenberg blocks.
     *
     * @return string The HTML.
     */
    public function convert(): string
    {
        // Get tags from the html.
        $content = static::getNodeTagFromHtml($this->html);

        // Bail early if is empty.
        if (empty($content->item(0)->childNodes)) {
            return '';
        }

        $html = [];

        foreach ($content->item(0)->childNodes as $node) {
            if ($node->nodeName === '#text' || $node->nodeType === XML_COMMENT_NODE) {
                continue;
            }

            $block = (string) $this->convertNode($node);

            $skipMinifyBlock = false;

            if (strtolower($node->nodeName) === 'pre') {
                $skipMinifyBlock = true;
            }

            // Allow the caller to decide whether this block should skip minification.
            $skipMinifyBlock = (bool) $this->apply($this->onSkipMinifyBlock, $skipMinifyBlock, $block, $node);

            if (! $skipMinifyBlock) {
                $block = $this->minifyBlock($block);
            }

            $html[] = $block;
        }

        $html = implode("\n\n", $html);

        // Remove empty blocks.
        $html = $this->removeEmptyBlocks($html);

        // Allow the caller to filter the fully converted HTML for the whole document.
        $filteredHtml = $this->apply($this->onDocumentHtml, $html, $content);
        $html = trim(is_string($filteredHtml) ? $filteredHtml : $html);

        return $html;
    }

    /**
     * Convert a node to a block.
     *
     *
     * @param  Node  $node  The node to convert.
     *
     * @throws RuntimeException If the block is not an instance of Block or null.
     */
    public function convertNode(Node $node): ?Block
    {
        if ($node->nodeName === '#text') {
            return null;
        }

        if ($this->convertMsWordContent && $this->isMsWordContent($node)) {
            $this->cleanMsWordNode($node);
        }

        $tagName = strtolower($node->nodeName);

        if (static::hasMacro($tagName)) {
            // Registered tag macros may be invoked by an arbitrary string
            // name (e.g. a hyphenated custom element like <special-tag>),
            // which isn't valid PHP method call syntax and can never trigger
            // __call() automatically — so call it directly instead.
            $block = $this->__call($tagName, [$node]);
        } elseif ($tagName === 'p' || in_array($tagName, self::INLINE_TAGS, true)) {
            $block = $this->p($node);
        } else {
            $block = match ($tagName) {
                'ul' => $this->ul($node),
                'ol' => $this->ol($node),
                'img' => $this->img($node),
                'blockquote' => $this->blockquote($node),
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $this->h($node),
                'figure' => $this->figure($node),
                'br', 'cite', 'source' => null,
                'hr' => $this->separator(),
                'pre' => $this->preformatted($node),
                default => $this->html($node),
            };
        }

        return $this->finalizeBlock($block, $node);
    }

    /**
     * Convert the children of a node to blocks.
     *
     * @param  Node  $node  The node.
     * @return string The children as blocks.
     */
    public function convertWithChildren(Node $node): string
    {
        $children = '';
        $previousWasBlock = false;

        // Recursively convert the children of the node.
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === '#text') {
                if (trim((string) $child->nodeValue) === '') {
                    continue;
                }

                $children .= $child->nodeValue;
                $previousWasBlock = false;

                continue;
            }

            // Inline formatting tags (anchors, bold, em, etc.) are running
            // text within this node's content, not their own nested block —
            // only an anchor wrapping a single image still needs full
            // conversion (into an image block).
            if (
                in_array(strtolower($child->nodeName), self::INLINE_TAGS, true)
                && ! $this->isAnchorWrappedImage($child)
            ) {
                $this->sideloadChildImages($child);

                $children .= trim(static::getNodeHtml($child));
                $previousWasBlock = false;

                continue;
            }

            // Ensure that the cite tag is not converted to a block.
            if (strtolower($child->nodeName) === 'cite') {
                $children .= trim(static::getNodeHtml($child));
            }

            $childBlock = $this->convertNode($child);

            if (! empty($childBlock)) {
                // Separate consecutive block-level children with a blank
                // line, matching the top-level join in convert(). Non-block
                // content (plain text, <cite>) attaches directly with no gap.
                if ($previousWasBlock) {
                    $children .= "\n\n";
                }

                $children .= $this->minifyBlock((string) $childBlock);
                $previousWasBlock = true;
            } else {
                $previousWasBlock = false;
            }
        }

        $node->textContent = '__CHILDREN__';

        $content = static::getNodeHtml($node);

        // Replace the placeholder with the children.
        $content = str_replace('__CHILDREN__', $children, $content);

        return $content;
    }

    /**
     * Create figure blocks.
     *
     * This method only supports converting a <figure> block that has either a
     * <img>, <a> or <figcaption> child. If the <figure> block has other children
     * the block will be converted to a HTML block.
     *
     * @param  Node  $node  The node.
     */
    public function figure(Node $node): ?Block
    {
        if ($this->isSupportedFigure($node)) {
            return $this->img($node);
        }

        return $this->html($node);
    }

    /**
     * Retrieve the attachment IDs created while sideloading images during the
     * conversion, if any. Empty if sideloading was never enabled.
     *
     * @return array<int>
     */
    public function getCreatedAttachmentIds(): array
    {
        return $this->uploader?->getCreatedAttachmentIds() ?? [];
    }

    /**
     * Remove any empty blocks.
     *
     * @param  string  $html  The current HTML.
     * @return string $html The new HTML.
     */
    public function removeEmptyBlocks(string $html): string
    {
        $html = str_replace(
            [
                '<!-- wp:html -->
<div></div>
<!-- /wp:html -->',
                '<!-- wp:paragraph -->
<div> </div>
<!-- /wp:paragraph -->',
                '<!-- wp:html -->
<div> </div>
<!-- /wp:html -->',
                '<!-- wp:paragraph -->
<div>  </div>
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
            ],
            '',
            $html
        );

        return $this->removeEmptyPBlocks($html);
    }

    /**
     * Remove any empty p blocks.
     *
     * @param  string  $html  The current HTML.
     * @return string $html The new HTML.
     */
    public function removeEmptyPBlocks(string $html): string
    {
        return \preg_replace(
            '/(\<\!\-\- wp\:paragraph \-\-\>[\s\n\r]*?\<p\>[\s\n\r]*?\<\/p\>[\s\n\r]*?\<\!\-\- \/wp\:paragraph \-\-\>)/',
            '',
            $html
        ) ?: $html;
    }

    /**
     * Quick way to remove all URL arguments.
     *
     * @param  string  $url  URL.
     * @return string A reconstructed image URL containing only the scheme, host, port, and path.
     */
    public function removeImageArgs($url): string
    {
        $urlParts = parse_url($url);
        $scheme = $urlParts['scheme'] ?? 'https';
        $host = $urlParts['host'] ?? '';
        $port = ! empty($urlParts['port']) ? ':'.$urlParts['port'] : '';
        $path = $urlParts['path'] ?? '';

        // Ensure we have enough parts to construct a valid URL.
        $sanitizedUrl = '';
        if (! empty($scheme) && ! empty($host) && ! empty($path)) {
            $sanitizedUrl = sprintf('%s://%s%s%s', $scheme, $host, $port, $path);
        }

        // Allow the caller to filter the reconstructed URL before it's returned.
        $filteredUrl = $this->apply($this->onSanitizedImageUrl, $sanitizedUrl, $url);

        return is_string($filteredUrl) ? $filteredUrl : $sanitizedUrl;
    }

    /**
     * Upload an image via the configured ImageUploader.
     *
     * @param  string  $src  Image url.
     * @param  string  $alt  Image alt.
     * @return string The uploaded image URL.
     *
     * @throws Exception If the image was not able to be uploaded.
     */
    public function uploadImage(string $src, string $alt): string
    {
        $src = $this->removeImageArgs($src);

        return $this->uploader?->upload($src, $alt) ?? $src;
    }

    /**
     * Collapse runs of whitespace in a node's descendant text nodes down to a
     * single space, matching how a browser (and the block editor's rich text
     * fields) render collapsible whitespace.
     *
     * @param  Node  $node  The node to collapse whitespace within.
     */
    protected static function collapseWhitespace(Node $node): void
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === '#text') {
                $child->nodeValue = preg_replace('/\s+/', ' ', (string) $child->nodeValue);

                continue;
            }

            if ($child->hasChildNodes()) {
                static::collapseWhitespace($child);
            }
        }
    }

    /**
     * Collect a node's descendant text nodes, in document order.
     *
     * @param  Node  $node  The node to collect text nodes from.
     * @param  Node[]  $textNodes  The collected text nodes, passed by reference.
     */
    protected static function collectTextNodes(Node $node, array &$textNodes): void
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === '#text') {
                $textNodes[] = $child;

                continue;
            }

            if ($child->hasChildNodes()) {
                static::collectTextNodes($child, $textNodes);
            }
        }
    }

    /**
     * Remove whitespace-only text node children from an element in place,
     * e.g. the indentation/newlines between tags in pretty-printed source
     * HTML that the block editor's own markup doesn't have.
     *
     * @param  Element  $element  The element.
     */
    protected static function removeWhitespaceOnlyChildTextNodes(Element $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child->nodeName === '#text' && trim((string) $child->nodeValue) === '') {
                $element->removeChild($child);
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
     * @param  string  $html  The HTML to restore self-closing syntax in.
     */
    protected static function selfCloseVoidElements(string $html): string
    {
        $voidElements = [
            'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param',
            'source', 'track', 'wbr',
        ];

        return preg_replace_callback(
            '/<('.implode('|', $voidElements).')\b[^>]*>/i',
            static function (array $matches): string {
                $tag = rtrim(trim($matches[0], '>'));

                return str_ends_with($tag, '/') ? $matches[0] : $tag.'/>';
            },
            $html
        ) ?? $html;
    }

    /**
     * Get a node's child nodes, ignoring whitespace-only text nodes (e.g. the
     * indentation/newlines between tags in pretty-printed source HTML), so
     * child-counting checks only see meaningfully different markup.
     *
     * @param  Node  $node  The node.
     * @return Node[]
     */
    protected static function significantChildNodes(Node $node): array
    {
        $children = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeName === '#text' && trim((string) $child->nodeValue) === '') {
                continue;
            }

            $children[] = $child;
        }

        return $children;
    }

    /**
     * Trim leading whitespace from a node's first descendant text node and
     * trailing whitespace from its last, matching how a browser trims edge
     * whitespace when rendering `white-space: normal` content.
     *
     * @param  Node  $node  The node to trim edge whitespace within.
     */
    protected static function trimEdgeWhitespace(Node $node): void
    {
        $textNodes = [];

        static::collectTextNodes($node, $textNodes);

        if (empty($textNodes)) {
            return;
        }

        $first = reset($textNodes);
        $first->nodeValue = ltrim((string) $first->nodeValue);

        $last = end($textNodes);
        $last->nodeValue = rtrim((string) $last->nodeValue);
    }

    /**
     * Invoke an optional hook callback, returning $value unchanged if none is set.
     *
     * Replaces the `apply_filters()` call sites this library used to have,
     * since callers now supply these as constructor callbacks instead of
     * registering WordPress filters.
     *
     * @param  Closure|null  $callback  The optional callback.
     * @param  mixed  $value  The value to pass as the callback's first argument.
     * @param  mixed  ...$args  Additional arguments to pass to the callback.
     */
    protected function apply(?Closure $callback, mixed $value, mixed ...$args): mixed
    {
        return $callback ? $callback($value, ...$args) : $value;
    }

    /**
     * Create blockquote block.
     *
     * @param  Node  $node  The node.
     */
    protected function blockquote(Node $node): ?Block
    {
        // Set the class on the node equal to wp-block-quote.
        if ($node instanceof Element && empty($node->getAttribute('class'))) {
            $node->setAttribute('class', 'wp-block-quote');
        }

        $content = $this->convertWithChildren($node);

        if (empty($content)) {
            return null;
        }

        return new Block(
            blockName: 'quote',
            attributes: [],
            content: $content,
        );
    }

    /**
     * Create an embed block for a URL matching a known oEmbed provider.
     *
     * @param  string  $url  The URL.
     */
    protected function embedForUrl(string $url): ?Block
    {
        foreach (self::OEMBED_PROVIDERS as $provider) {
            if (! preg_match($provider['pattern'], $url)) {
                continue;
            }

            $attributes = [
                'url' => $url,
                'type' => $provider['type'],
                'providerNameSlug' => $provider['slug'],
                'responsive' => true,
            ];

            foreach ($provider['extra_attributes'] ?? [] as $key => $value) {
                $attributes[$key] = $value;
            }

            $className = '';

            if (! empty($provider['aspect_ratio'])) {
                $className = sprintf(
                    'wp-embed-aspect-%s wp-has-aspect-ratio',
                    $provider['aspect_ratio']
                );
                $attributes['className'] = $className;
            }

            return new Block(
                blockName: 'embed',
                attributes: $attributes,
                content: sprintf(
                    '<figure class="wp-block-embed is-type-%s is-provider-%s wp-block-embed-%s%s"><div class="wp-block-embed__wrapper">
					%s
					</div></figure>',
                    $provider['type'],
                    $provider['slug'],
                    $provider['slug'],
                    $className ? ' '.$className : '',
                    $url
                ),
            );
        }

        return null;
    }

    /**
     * Run the `on_block` callback against a generated block.
     *
     * Shared by `convertNode()` and any method that builds `Block` instances
     * outside of the normal per-node dispatch (e.g. splitting a single node
     * into multiple sibling blocks), so every generated block passes through
     * the same customization hook.
     *
     *
     * @param  mixed  $block  The generated block, if any.
     * @param  Node  $node  The node the block was generated from.
     *
     * @throws RuntimeException If the block is not an instance of Block or null.
     */
    protected function finalizeBlock(mixed $block, Node $node): ?Block
    {
        if ($block !== null && ! $block instanceof Block) {
            throw new RuntimeException('Returned block must be an instance of Block or null.');
        }

        $block = $this->apply($this->onBlock, $block, $node);

        return $block instanceof Block ? $block : null;
    }

    /**
     * Create heading blocks.
     *
     * @param  Node  $node  The node.
     */
    protected function h(Node $node): ?Block
    {
        if ($node instanceof Element) {
            $node->setAttribute('class', 'wp-block-heading');
        }

        $content = static::getNodeHtml($node);

        if (empty($content)) {
            return null;
        }

        return new Block(
            blockName: 'heading',
            attributes: [
                'level' => (int) str_replace('h', '', strtolower($node->nodeName)),
            ],
            content: $content,
        );
    }

    /**
     * Create HTML blocks.
     *
     * @param  Node  $node  The node.
     */
    protected function html(Node $node): ?Block
    {
        $this->sideloadChildImages($node);

        // Get the raw HTML.
        $html = static::getNodeHtml($node);

        if (empty($html)) {
            return null;
        }

        return new Block(
            blockName: 'html',
            content: static::selfCloseVoidElements($html),
        );
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
     * @param  Element|Node  $element  The node.
     * @param  bool  $splitFromParagraph  Whether this image was split out from inline
     *                                    paragraph text, in which case the block editor
     *                                    floats it right of the remaining text.
     */
    protected function img(Element|Node $element, bool $splitFromParagraph = false): ?Block
    {
        if (! $element instanceof Element) {
            return null;
        }

        // If the element passed isn't an <img> attempt to find it from the children.
        if (strtolower($element->nodeName) !== 'img') {
            $imageNode = $element->getElementsByTagName('img')->item(0);

            // Bail early if the image node is not found.
            if (! $imageNode) {
                return null;
            }
        } else {
            $imageNode = $element;
        }

        $imageSrc = $imageNode->getAttribute('data-srcset');
        $alt = $imageNode->getAttribute('alt') ?? '';

        if (empty($imageSrc) && ! empty($imageNode->getAttribute('src'))) {
            $imageSrc = $imageNode->getAttribute('src');
        }

        if (empty($imageSrc)) {
            return null;
        }

        // The block editor's image block always derives responsive sizes
        // from the attachment itself, so a copied-in srcset/sizes pair is
        // never valid on a converted image regardless of sideloading.
        if ($imageNode->hasAttribute('srcset')) {
            $imageNode->removeAttribute('srcset');
        }

        if ($imageNode->hasAttribute('sizes')) {
            $imageNode->removeAttribute('sizes');
        }

        $attributes = [];
        $wrappedInAnchor = $imageNode->parentNode instanceof Element
            && strtolower($imageNode->parentNode->nodeName) === 'a';

        if ($this->uploader) {
            try {
                $imageSrc = $this->uploadImage($imageSrc, $alt);

                $imageNode->setAttribute('src', $imageSrc);
            } catch (Exception) {
                return null;
            }

            $attachmentId = $this->uploader->attachmentIdFor($imageSrc);

            if ($attachmentId) {
                $imageNode->setAttribute('class', 'wp-image-'.$attachmentId);
            }

            // The block editor's exact attribute shape for a sideloaded
            // image, confirmed against a live WP 7.0 install: a link
            // disables the lightbox and points the image at its custom
            // href, while a bare image split out of paragraph text keeps
            // its (default) "none" link destination explicit and floats
            // right of the remaining text.
            if ($wrappedInAnchor) {
                $attributes['lightbox'] = ['enabled' => false];
            }

            if ($attachmentId !== null) {
                $attributes['id'] = $attachmentId;
            }

            $attributes['sizeSlug'] = 'full';

            if ($wrappedInAnchor) {
                $attributes['linkDestination'] = 'custom';
            } elseif ($splitFromParagraph) {
                $attributes['linkDestination'] = 'none';
            }

            if ($splitFromParagraph) {
                $attributes['align'] = 'right';
            }
        } elseif (! ($this->convertMsWordContent && $this->isMsWordHtml($this->html))) {
            // Without a known attachment, default to the block editor's
            // "Large" image size option — unless this is a Microsoft Word
            // paste, which the editor handles as a distinct import path
            // that doesn't assign a size slug.
            $attributes['sizeSlug'] = 'large';
        }

        if (empty($imageSrc)) {
            return null;
        }

        $class = 'wp-block-image'
            .($splitFromParagraph ? ' alignright' : '')
            .(isset($attributes['sizeSlug']) ? ' size-'.$attributes['sizeSlug'] : '');

        if (strtolower($element->nodeName) === 'figure') {
            $element->setAttribute('class', $class);

            $figcaption = $element->getElementsByTagName('figcaption')->item(0);

            if ($figcaption instanceof Element) {
                $figcaption->setAttribute('class', 'wp-element-caption');
            }

            // Drop the indentation/newline text nodes a pretty-printed
            // <figure> has between its children — the block editor's own
            // figure markup has no whitespace between its child elements.
            static::removeWhitespaceOnlyChildTextNodes($element);

            $content = static::getNodeHtml($element);
        } else {
            $content = sprintf('<figure class="%s">%s</figure>', $class, static::getNodeHtml($element));
        }

        return new Block(
            blockName: 'image',
            attributes: $attributes,
            content: static::selfCloseVoidElements($content),
        );
    }

    /**
     * Check if the node's only meaningful child is an <img>, e.g. an <a>
     * wrapping a single image.
     *
     * @param  Node|null  $node  The node.
     */
    protected function isAnchorWrappedImage(?Node $node): bool
    {
        if (! $node) {
            return false;
        }

        $children = static::significantChildNodes($node);

        return count($children) === 1 && strtolower($children[0]->nodeName) === 'img';
    }

    /**
     * Check if the figure node is supported for conversion.
     *
     * @param  Node  $node  The node.
     */
    protected function isSupportedFigure(Node $node): bool
    {
        $children = static::significantChildNodes($node);

        if (empty($children) || count($children) > 2) {
            return false;
        }

        if (count($children) === 2 && strtolower($children[1]->nodeName) !== 'figcaption') {
            return false;
        }

        $firstChild = $children[0];

        // Check if the first child is an <img> or an <a> with an <img> child.
        return strtolower($firstChild->nodeName) === 'img' || $this->isAnchorWrappedImage($firstChild);
    }

    /**
     * Create list blocks, wrapping each <li> child in a nested "list-item"
     * block to match the block editor's markup.
     *
     * @param  Node  $node  The node.
     * @param  bool  $ordered  Whether the list is ordered (<ol>).
     */
    protected function list(Node $node, bool $ordered): Block
    {
        $this->sideloadChildImages($node);

        if ($node instanceof Element) {
            $node->setAttribute('class', 'wp-block-list');
        }

        $items = [];

        foreach ($node->childNodes as $child) {
            if (strtolower($child->nodeName) !== 'li') {
                continue;
            }

            $items[] = (string) $this->listItem($child);
        }

        $node->textContent = '__CHILDREN__';

        $content = str_replace('__CHILDREN__', implode("\n\n", $items), static::getNodeHtml($node));

        return new Block(
            blockName: 'list',
            attributes: $ordered ? ['ordered' => true] : [],
            content: $content,
        );
    }

    /**
     * Create list-item blocks for a <li>.
     *
     * @param  Node  $node  The node.
     */
    protected function listItem(Node $node): Block
    {
        return new Block(
            blockName: 'list-item',
            content: $this->convertWithChildren($node),
        );
    }

    /**
     * Removing whitespace between blocks
     *
     * @param  string  $block  Gutenberg blocks.
     */
    protected function minifyBlock(string $block): string
    {
        if (\str_contains($block, 'wp-block-embed')) {
            if (preg_match('/(\h){2,}/s', $block) === 1) {
                return preg_replace('/(\h){2,}/s', '', $block) ?: '';
            }

            return $block;
        }

        return trim($block);
    }

    /**
     * Create ol blocks.
     *
     * @param  Node  $node  The node.
     */
    protected function ol(Node $node): Block
    {
        return $this->list($node, true);
    }

    /**
     * Create paragraph blocks.
     *
     * @param  Node  $node  The node.
     */
    protected function p(Node $node): ?Block
    {
        if ($this->isAnchorWrappedImage($node)) {
            return $this->img($node);
        }

        if (strtolower($node->nodeName) === 'p' && $this->paragraphHasInlineImage($node)) {
            return $this->splitParagraphWithInlineImages($node);
        }

        $this->sideloadChildImages($node);
        static::collapseWhitespace($node);
        static::trimEdgeWhitespace($node);

        $content = static::getNodeHtml($node);

        if (empty($content)) {
            return null;
        }

        $textContent = $node->textContent ?? '';

        // TODO: Account for Twitter/Facebook embeds being inline links in
        // content and not full embeds.
        if (! empty(filter_var($textContent, FILTER_VALIDATE_URL))) {
            if (\str_contains($textContent, '//x.com/') || \str_contains($textContent, '//www.x.com/')) {
                $textContent = str_replace(['//x.com/', '//www.x.com/'], '//twitter.com/', $textContent);
                $node->textContent = $textContent;
            }

            $embed = $this->embedForUrl($textContent);

            if ($embed) {
                return $embed;
            }
        }

        return new Block(
            blockName: 'paragraph',
            attributes: [],
            content: $content,
        );
    }

    /**
     * Check if a <p> has an <img> (bare or anchor-wrapped) as one of several
     * direct children, i.e. an image sitting inline in running text rather
     * than being the paragraph's sole content.
     *
     * @param  Node  $node  The node.
     */
    protected function paragraphHasInlineImage(Node $node): bool
    {
        foreach (static::significantChildNodes($node) as $child) {
            if (strtolower($child->nodeName) === 'img' || $this->isAnchorWrappedImage($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create preformatted blocks.
     *
     * @param  Node  $node  The node.
     */
    protected function preformatted(Node $node): ?Block
    {
        $content = trim((string) $node->textContent);

        if (empty($content)) {
            return null;
        }

        return new Block(
            blockName: 'preformatted',
            content: sprintf(
                '<pre class="wp-block-preformatted">%s</pre>',
                str_replace("\n", '<br>', htmlspecialchars($content, ENT_NOQUOTES)),
            ),
        );
    }

    /**
     * Create separator blocks.
     */
    protected function separator(): Block
    {
        return new Block(
            blockName: 'separator',
            content: '<hr class="wp-block-separator has-alpha-channel-opacity"/>'
        );
    }

    /**
     * Sideload any child images of a Node and replace the src with the new URL.
     *
     * @param  Node  $node  The node.
     */
    protected function sideloadChildImages(Node $node): void
    {
        if (! $this->uploader) {
            return;
        }

        $children = $node->childNodes;

        if (! $children->length) {
            return;
        }

        foreach ($children as $childNode) {
            // Skip if the node is not an image or is not an instance of Element.
            if (strtolower($childNode->nodeName) !== 'img' || ! $childNode instanceof Element) {
                // Recursively sideload images in child nodes.
                if ($childNode->hasChildNodes()) {
                    $this->sideloadChildImages($childNode);
                }

                continue;
            }

            // Allow the caller to decide whether this image should be sideloaded.
            $pre = (bool) $this->apply(
                $this->onPreSideloadImage,
                true,
                $childNode->getAttribute('src') ?? '',
                $childNode,
                $this
            );

            // Re-read the src attribute in case it was modified by the callback.
            $src = $childNode->getAttribute('src') ?? '';

            if (! $pre || empty($src)) {
                continue;
            }

            try {
                $previousSrc = $src;
                $src = $this->uploadImage($src, $childNode->getAttribute('alt') ?? '');

                if ($src) {
                    $childNode->setAttribute('src', $src);

                    // Remove any srcset and sizes attributes.
                    if ($childNode->hasAttribute('srcset')) {
                        $childNode->removeAttribute('srcset');
                    }
                    if ($childNode->hasAttribute('sizes')) {
                        $childNode->removeAttribute('sizes');
                    }

                    // Update the parent node with the new link if the parent
                    // node is an anchor.
                    if (
                        $node instanceof Element
                        && strtolower($node->nodeName) === 'a'
                        && $previousSrc === $node->getAttribute('href')
                    ) {
                        $node->setAttribute('href', $src);
                    }

                    // Notify the caller that a child image has been sideloaded.
                    if ($this->onSideloadedImage) {
                        ($this->onSideloadedImage)($src, $childNode);
                    }
                }
            } catch (Throwable $e) {
                $this->logger?->error(
                    "Error sideloading image: {$e->getMessage()}",
                    [
                        'exception' => $e,
                        'node' => $childNode,
                    ]
                );
            }
        }
    }

    /**
     * Split a <p> containing one or more inline images into separate
     * paragraph and image blocks, matching how the block editor splits a
     * pasted inline image out of its surrounding text into its own image
     * block (floated right of the remaining text).
     *
     * @param  Node  $node  The node.
     */
    protected function splitParagraphWithInlineImages(Node $node): ?Block
    {
        // Each inline image is sideloaded individually by img() below — not
        // pre-sideloaded here, which would overwrite its src with a local
        // URL before img() ever sees the original remote one.
        static::collapseWhitespace($node);

        $blocks = [];
        $buffer = '';

        foreach ($node->childNodes as $child) {
            if (strtolower($child->nodeName) === 'img' || $this->isAnchorWrappedImage($child)) {
                $text = trim($buffer);

                if ($text !== '') {
                    $blocks[] = $this->finalizeBlock(
                        new Block(blockName: 'paragraph', content: sprintf('<p>%s</p>', $text)),
                        $child,
                    );
                }

                $blocks[] = $this->finalizeBlock($this->img($child, splitFromParagraph: true), $child);

                $buffer = '';

                continue;
            }

            $buffer .= $child->nodeName === '#text' ? (string) $child->nodeValue : static::getNodeHtml($child);
        }

        $text = trim($buffer);

        if ($text !== '') {
            $blocks[] = $this->finalizeBlock(
                new Block(blockName: 'paragraph', content: sprintf('<p>%s</p>', $text)),
                $node,
            );
        }

        $blocks = array_filter($blocks);

        if (empty($blocks)) {
            return null;
        }

        return new Block(
            blockName: '',
            content: implode(
                "\n\n",
                array_map(fn (Block $block) => $this->minifyBlock((string) $block), $blocks)
            ),
        );
    }

    /**
     * Create ul blocks.
     *
     * @param  Node  $node  The node.
     */
    protected function ul(Node $node): Block
    {
        return $this->list($node, false);
    }
}
