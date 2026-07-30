<?php

/**
 * Trait ConvertsUrlsToEmbeds
 *
 * @package wp-block-converter
 */

namespace Alley\WP\BlockConverter\Tests\Shared\Concerns;

use Alley\WP\BlockConverter\BlockConverter;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Rich embed coverage for the static oEmbed provider table
 * (BlockConverter::OEMBED_PROVIDERS). This needs no HTTP mocking at all —
 * embedForUrl() never makes a request — so it needs nothing WordPress
 * specific and is shared between the WordPress and standalone suites.
 */
trait ConvertsUrlsToEmbeds
{
    /**
     * Data provider of URLs for each supported oEmbed provider (YouTube,
     * Vimeo, Twitter/X, Instagram, Facebook, TikTok, etc.) and their expected
     * embed block markup.
     *
     * @return array<string, array{0: string, 1: string}> Each item is
     *                                                     [ $html, $expected ]
     *                                                     matching
     *                                                     testUrlToEmbed()'s parameters.
     */
    public static function embedDataProvider(): array
    {
        return [
            'youtube' => [
                <<<HTML
<p>https://www.youtube.com/watch?v=dQw4w9WgXcQ</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://www.youtube.com/watch?v=dQw4w9WgXcQ","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://www.youtube.com/watch?v=dQw4w9WgXcQ
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'youtube shorts' => [
                <<<HTML
<p>https://www.youtube.com/shorts/dQw4w9WgXcQ</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://www.youtube.com/shorts/dQw4w9WgXcQ","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-9-16 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-9-16 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://www.youtube.com/shorts/dQw4w9WgXcQ
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'vimeo' => [
                <<<HTML
<p>https://vimeo.com/76979871</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://vimeo.com/76979871","type":"video","providerNameSlug":"vimeo","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-vimeo wp-block-embed-vimeo wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://vimeo.com/76979871
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'dailymotion' => [
                <<<HTML
<p>https://www.dailymotion.com/video/x7tgplay</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://www.dailymotion.com/video/x7tgplay","type":"video","providerNameSlug":"dailymotion","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-dailymotion wp-block-embed-dailymotion wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://www.dailymotion.com/video/x7tgplay
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'wordpress-tv' => [
                <<<HTML
<p>https://wordpress.tv/2023/01/01/example-video/</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://wordpress.tv/2023/01/01/example-video/","type":"video","providerNameSlug":"wordpress-tv","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-wordpress-tv wp-block-embed-wordpress-tv wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://wordpress.tv/2023/01/01/example-video/
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'videopress' => [
                <<<HTML
<p>https://videopress.com/v/abc123XY</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://videopress.com/v/abc123XY","type":"video","providerNameSlug":"videopress","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-videopress wp-block-embed-videopress wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://videopress.com/v/abc123XY
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'flickr' => [
                <<<HTML
<p>https://www.flickr.com/photos/example/1234567890/</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://www.flickr.com/photos/example/1234567890/","type":"rich","providerNameSlug":"flickr","responsive":true} -->
<figure class="wp-block-embed is-type-rich is-provider-flickr wp-block-embed-flickr"><div class="wp-block-embed__wrapper">
https://www.flickr.com/photos/example/1234567890/
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'soundcloud' => [
                <<<HTML
<p>https://soundcloud.com/example-artist/example-track</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://soundcloud.com/example-artist/example-track","type":"rich","providerNameSlug":"soundcloud","responsive":true} -->
<figure class="wp-block-embed is-type-rich is-provider-soundcloud wp-block-embed-soundcloud"><div class="wp-block-embed__wrapper">
https://soundcloud.com/example-artist/example-track
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'spotify' => [
                <<<HTML
<p>https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT","type":"rich","providerNameSlug":"spotify","responsive":true} -->
<figure class="wp-block-embed is-type-rich is-provider-spotify wp-block-embed-spotify"><div class="wp-block-embed__wrapper">
https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'slideshare' => [
                <<<HTML
<p>https://www.slideshare.net/example/example-presentation</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://www.slideshare.net/example/example-presentation","type":"rich","providerNameSlug":"slideshare","responsive":true} -->
<figure class="wp-block-embed is-type-rich is-provider-slideshare wp-block-embed-slideshare"><div class="wp-block-embed__wrapper">
https://www.slideshare.net/example/example-presentation
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'scribd' => [
                <<<HTML
<p>https://www.scribd.com/document/123456789/Example-Document</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://www.scribd.com/document/123456789/Example-Document","type":"rich","providerNameSlug":"scribd","responsive":true} -->
<figure class="wp-block-embed is-type-rich is-provider-scribd wp-block-embed-scribd"><div class="wp-block-embed__wrapper">
https://www.scribd.com/document/123456789/Example-Document
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'reddit' => [
                <<<HTML
<p>https://www.reddit.com/r/wordpress/comments/abc123/example_post/</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://www.reddit.com/r/wordpress/comments/abc123/example_post/","type":"rich","providerNameSlug":"reddit","responsive":true} -->
<figure class="wp-block-embed is-type-rich is-provider-reddit wp-block-embed-reddit"><div class="wp-block-embed__wrapper">
https://www.reddit.com/r/wordpress/comments/abc123/example_post/
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'imgur' => [
                <<<HTML
<p>https://imgur.com/gallery/abc123</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://imgur.com/gallery/abc123","type":"rich","providerNameSlug":"imgur","responsive":true} -->
<figure class="wp-block-embed is-type-rich is-provider-imgur wp-block-embed-imgur"><div class="wp-block-embed__wrapper">
https://imgur.com/gallery/abc123
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'twitter' => [
                <<<HTML
<p>https://twitter.com/alleyco/status/1679189879086018562</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://twitter.com/alleyco/status/1679189879086018562","type":"rich","providerNameSlug":"x","responsive":true} -->
<figure class="wp-block-embed is-type-rich is-provider-x wp-block-embed-x"><div class="wp-block-embed__wrapper">
https://twitter.com/alleyco/status/1679189879086018562
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'x.com' => [
                <<<HTML
<p>https://x.com/alleyco/status/1679189879086018562</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://twitter.com/alleyco/status/1679189879086018562","type":"rich","providerNameSlug":"x","responsive":true} -->
<figure class="wp-block-embed is-type-rich is-provider-x wp-block-embed-x"><div class="wp-block-embed__wrapper">
https://twitter.com/alleyco/status/1679189879086018562
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'x.com linked' => [
                <<<HTML
<p><a href="https://x.com/alleyco/status/1679189879086018562">https://x.com/alleyco/status/1679189879086018562</a></p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://twitter.com/alleyco/status/1679189879086018562","type":"rich","providerNameSlug":"x","responsive":true} -->
<figure class="wp-block-embed is-type-rich is-provider-x wp-block-embed-x"><div class="wp-block-embed__wrapper">
https://twitter.com/alleyco/status/1679189879086018562
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'instagram' => [
                <<<HTML
<p>https://www.instagram.com/p/CSpmSvAphdf/</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://www.instagram.com/p/CSpmSvAphdf/","type":"rich","providerNameSlug":"instagram","responsive":true} -->
<figure class="wp-block-embed is-type-rich is-provider-instagram wp-block-embed-instagram"><div class="wp-block-embed__wrapper">
https://www.instagram.com/p/CSpmSvAphdf/
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'facebook' => [
                <<<HTML
<p>https://www.facebook.com/sesametheopossum/posts/1329405240877426</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://www.facebook.com/sesametheopossum/posts/1329405240877426","type":"rich","providerNameSlug":"facebook","responsive":true,"previewable":false} -->
<figure class="wp-block-embed is-type-rich is-provider-facebook wp-block-embed-facebook"><div class="wp-block-embed__wrapper">
https://www.facebook.com/sesametheopossum/posts/1329405240877426
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
            'tiktok' => [
                <<<HTML
<p>https://www.tiktok.com/@atribecalledval/video/7348705314746699054</p>
HTML,
                <<<HTML
<!-- wp:embed {"url":"https://www.tiktok.com/@atribecalledval/video/7348705314746699054","type":"video","providerNameSlug":"tiktok","responsive":true} -->
<figure class="wp-block-embed is-type-video is-provider-tiktok wp-block-embed-tiktok"><div class="wp-block-embed__wrapper">
https://www.tiktok.com/@atribecalledval/video/7348705314746699054
</div></figure>
<!-- /wp:embed -->
HTML,
            ],
        ];
    }

    /**
     * Tests that a bare or linked URL from embedDataProvider() converts to
     * the expected embed block for its provider, generated from the static
     * OEMBED_PROVIDERS table rather than a live oEmbed request.
     *
     * @param string $html     The source HTML to convert.
     * @param string $expected The expected converted embed block markup.
     */
    #[DataProvider('embedDataProvider')]
    public function testUrlToEmbed(string $html, string $expected): void
    {
        $block = ( new BlockConverter($html) )->convert();

        $this->assertSame(
            expected: $expected,
            actual: $block,
        );
    }
}
