<?php

/**
 * Block class file
 *
 * @package wp-block-converter
 */

namespace Alley\WP\BlockConverter;

/**
 * Block Transfer Object
 */
class Block
{
    /**
     * Constructor.
     *
     * @param string               $blockName  The block name.
     * @param array<string, mixed> $attributes The block attributes.
     * @param string|null          $content    The block content.
     */
    public function __construct(public string $blockName, public array $attributes = [], public ?string $content = null)
    {
    }

    /**
     * Convert the block to HTML.
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * Render the block.
     *
     * Reimplements WordPress' get_comment_delimited_block_content() without
     * requiring WordPress to be loaded, and with a line break after the
     * opening comment delimiter and before the closing one to match the
     * output of the block editor.
     */
    public function render(): string
    {
        $blockName  = self::stripCoreNamespace($this->blockName);
        $attributes = empty($this->attributes) ? '' : self::serializeAttributes($this->attributes) . ' ';
        $content    = $this->content ?? '';

        // An empty block name marks this as a passthrough for content that's
        // already fully block-comment-delimited (e.g. multiple sibling
        // blocks produced from splitting a single source node), so render
        // it verbatim instead of wrapping it in another comment delimiter.
        if ('' === $blockName) {
            return $content;
        }

        if (empty($content)) {
            return sprintf('<!-- wp:%s %s/-->', $blockName, $attributes);
        }

        return sprintf(
            "<!-- wp:%s %s-->\n%s\n<!-- /wp:%s -->",
            $blockName,
            $attributes,
            $content,
            $blockName,
        );
    }

    /**
     * Remove the default "core/" namespace from a block name.
     *
     * @param string $blockName Original block name.
     */
    private static function stripCoreNamespace(string $blockName): string
    {
        return str_starts_with($blockName, 'core/') ? substr($blockName, 5) : $blockName;
    }

    /**
     * Serialize block attributes for inclusion in a block comment delimiter.
     *
     * @param array<string, mixed> $attributes Block attributes.
     */
    private static function serializeAttributes(array $attributes): string
    {
        return strtr(
            (string) json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            [
                '\\\\' => '\\u005c',
                '--'   => '\\u002d\\u002d',
                '<'    => '\\u003c',
                '>'    => '\\u003e',
                '&'    => '\\u0026',
                '\\"'  => '\\u0022',
            ],
        );
    }
}
