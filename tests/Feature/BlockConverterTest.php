<?php
/**
 * Class Block_Converter
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter\Tests\Feature;

use Alley\WP\Block_Converter\Block;
use Alley\WP\Block_Converter\Block_Converter;
use Alley\WP\Block_Converter\Tests\TestCase;
use Dom\Node;
use Mantle\Testing\Concerns\Prevent_Remote_Requests;
use Mantle\Support\Str;
use Mantle\Testing\Concerns\Refresh_Database;
use PHPUnit\Framework\Attributes\DataProvider;

use function Mantle\Support\Helpers\collect;
use function Mantle\Testing\mock_http_response;

/**
 * Test case for Block_Converter Module.
 */
class BlockConverterTest extends TestCase {
	use Prevent_Remote_Requests, Refresh_Database;

	protected function setUp(): void {
		parent::setUp();

		$this->fake_request( [
			'https://publish.twitter.com/oembed?maxwidth=500&maxheight=750&url=https%3A%2F%2Ftwitter.com%2Falleyco%2Fstatus%2F1679189879086018562&dnt=1&format=json' => mock_http_response()->with_json( '{"url":"https:\/\/twitter.com\/alleyco\/status\/1679189879086018562","author_name":"Alley","author_url":"https:\/\/twitter.com\/alleyco","html":"\u003Cblockquote class=\"twitter-tweet\" data-width=\"500\" data-dnt=\"true\"\u003E\u003Cp lang=\"en\" dir=\"ltr\"\u003EWe’re a full-service digital agency with the foresight, perspective, and grit to power your brightest ideas and build solutions for your most evasive problems. Learn more about our services here:\u003Ca href=\"https:\/\/t.co\/8zZ5zP1Oyc\"\u003Ehttps:\/\/t.co\/8zZ5zP1Oyc\u003C\/a\u003E\u003C\/p\u003E&mdash; Alley (@alleyco) \u003Ca href=\"https:\/\/twitter.com\/alleyco\/status\/1679189879086018562?ref_src=twsrc%5Etfw\"\u003EJuly 12, 2023\u003C\/a\u003E\u003C\/blockquote\u003E\n\u003Cscript async src=\"https:\/\/platform.twitter.com\/widgets.js\" charset=\"utf-8\"\u003E\u003C\/script\u003E\n\n","width":500,"height":null,"type":"rich","cache_age":"3153600000","provider_name":"Twitter","provider_url":"https:\/\/twitter.com","version":"1.0"}' ),
			'https://www.tiktok.com/oembed?maxwidth=500&maxheight=750&url=https%3A%2F%2Fwww.tiktok.com%2F%40atribecalledval%2Fvideo%2F7348705314746699054&dnt=1&format=json' => mock_http_response()->with_json( '{"version":"1.0","type":"video","title":"Andre 3000 performing at Luna Luna was such an incredible night. I will never forget this night. #losangeles #andre3000 #fyp #foryou #foryoupage ","author_url":"https://www.tiktok.com/@atribecalledval","author_name":"Valeria Cardona","width":"100%","height":"100%","html":"<blockquote class=\"tiktok-embed\" cite=\"https://www.tiktok.com/@atribecalledval/video/7348705314746699054\" data-video-id=\"7348705314746699054\" data-embed-from=\"oembed\" style=\"max-width:605px; min-width:325px;\"> <section> <a target=\"_blank\" title=\"@atribecalledval\" href=\"https://www.tiktok.com/@atribecalledval?refer=embed\">@atribecalledval</a> <p>Andre 3000 performing at Luna Luna was such an incredible night. I will never forget this night. <a title=\"losangeles\" target=\"_blank\" href=\"https://www.tiktok.com/tag/losangeles?refer=embed\">#losangeles</a> <a title=\"andre3000\" target=\"_blank\" href=\"https://www.tiktok.com/tag/andre3000?refer=embed\">#andre3000</a> <a title=\"fyp\" target=\"_blank\" href=\"https://www.tiktok.com/tag/fyp?refer=embed\">#fyp</a> <a title=\"foryou\" target=\"_blank\" href=\"https://www.tiktok.com/tag/foryou?refer=embed\">#foryou</a> <a title=\"foryoupage\" target=\"_blank\" href=\"https://www.tiktok.com/tag/foryoupage?refer=embed\">#foryoupage</a> </p> <a target=\"_blank\" title=\"♬ I swear, I Really Wanted To Make A\" href=\"https://www.tiktok.com/music/I-swear-I-Really-Wanted-To-Make-A-Rap-Album-But-This-Is-Literally-The-Way-The-Wind-Blew-Me-This-Time-7302364812792547330?refer=embed\">♬ I swear, I Really Wanted To Make A \"Rap\" Album But This Is Literally The Way The Wind Blew Me This Time - André 3000</a> </section> </blockquote> <script async src=\"https://www.tiktok.com/embed.js\"></script>","thumbnail_width":576,"thumbnail_height":1024,"thumbnail_url":"https://p19-pu-sign-useast8.tiktokcdn-us.com/obj/tos-useast5-p-0068-tx/afac3ae6ea3343c890e12e3cbbca1218_1711003872?lk3s=b59d6b55&nonce=81617&refresh_token=bf81ce66fb4d648cbd499791f37a6354&x-expires=1722110400&x-signature=tpTiBYwvSXjjAEgNRU2F%2BUAz7jo%3D&shp=b59d6b55&shcp=-","provider_url":"https://www.tiktok.com","provider_name":"TikTok","author_unique_id":"atribecalledval","embed_product_id":"7348705314746699054","embed_type":"video"}' ),
		] );

		$this->fake_request( 'https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png' )
			->with_file( __DIR__ . '/../fixtures/image.png' );

		// Delete all uploaded files between tests.
		$dir = wp_upload_dir();

		shell_exec( "rm -rf {$dir['path']}/*" );

		remove_all_actions( 'add_attachment' );
	}

	#[DataProvider( 'converter_data_provider' )]
	public function test_convert_to_blocks( string $html, string $expected ) {
		$this->assertSame(
			expected: $expected,
			actual: ( new Block_Converter( $html ) )->convert(),
		);
	}

	public static function converter_data_provider() {
		return [
			'paragraph' => [
				<<<HTML
<p>Content to migrate</p>
HTML,
				<<<HTML
<!-- wp:paragraph -->
<p>Content to migrate</p>
<!-- /wp:paragraph -->
HTML,
			],
			'empty-paragraphs' => [
				<<<HTML
<p>Content to migrate</p>
<p></p>
HTML,
				<<<HTML
<!-- wp:paragraph -->
<p>Content to migrate</p>
<!-- /wp:paragraph -->
HTML,
			],
			'paragraph-heading' => [
				<<<HTML
<p>Content to migrate</p>
<h1>Heading 01</h1>
HTML,
					<<<HTML
<!-- wp:paragraph -->
<p>Content to migrate</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Heading 01</h1>
<!-- /wp:heading -->
HTML,
			],
			'h1' => [
				<<<HTML
<h1>Another content</h1>
HTML,
				<<<HTML
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Another content</h1>
<!-- /wp:heading -->
HTML,
			],
			'h2' => [
				<<<HTML
<h2>Another content</h2>
HTML,
				<<<HTML
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Another content</h2>
<!-- /wp:heading -->
HTML,
			],
			'h3' => [
				<<<HTML
<h3>Another content</h3>
HTML,
				<<<HTML
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Another content</h3>
<!-- /wp:heading -->
HTML,
			],
			'h4' => [
				<<<HTML
<h4>Another content</h4>
HTML,
				<<<HTML
<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Another content</h4>
<!-- /wp:heading -->
HTML,
			],
			'h5' => [
				<<<HTML
<h5>Another content</h5>
HTML,
				<<<HTML
<!-- wp:heading {"level":5} -->
<h5 class="wp-block-heading">Another content</h5>
<!-- /wp:heading -->
HTML,
			],
			'ol' => [
				<<<HTML
<ol>
	<li>Random content</li>
	<li>Another random content</li>
</ol>
HTML,
				<<<HTML
<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li>Random content</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Another random content</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->
HTML,
			],
			'ul' => [
				<<<HTML
<ul>
	<li>Random content</li>
	<li>Another random content</li>
</ul>
HTML,
				<<<HTML
<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Random content</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Another random content</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
HTML,
			],
			'blockquote' => [
				<<<HTML
<blockquote>
	<p>Lorem ipsum</p>
</blockquote>
HTML,
				<<<HTML
<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>Lorem ipsum</p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote -->
HTML,
			],
			'blockquote with cite' => [
				<<<HTML
<blockquote>
	<p>Lorem ipsum</p>
	<cite>Source</cite>
</blockquote>
HTML,
				<<<HTML
<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>Lorem ipsum</p>
<!-- /wp:paragraph --><cite>Source</cite></blockquote>
<!-- /wp:quote -->
HTML,
			],
			'non-oembed-embed' => [
				<<<HTML
<embed type="video/webm" src="/media/mr-arnold.mp4" width="250" height="200" />
HTML,
				<<<HTML
<!-- wp:html -->
<embed type="video/webm" src="/media/mr-arnold.mp4" width="250" height="200" />
<!-- /wp:html -->
HTML,
			],
			'paragraph with double spaces' => [
				<<<HTML
<p>This is content with  a double space.</p>
HTML,
				<<<HTML
<!-- wp:paragraph -->
<p>This is content with a double space.</p>
<!-- /wp:paragraph -->
HTML,
			],
			'paragraph with multiple spaces' => [
				<<<HTML
<p>This has    four spaces and	a tab.</p>
HTML,
				<<<HTML
<!-- wp:paragraph -->
<p>This has four spaces and a tab.</p>
<!-- /wp:paragraph -->
HTML,
			],
		];
	}

	#[DataProvider( 'image_dataprovider' )]
	public function test_image( string $html, string $expected ) {
		$converter = new Block_Converter(
			html: $html,
			sideload_images: true,
		);
		$block     = $converter->convert();

		// TODO: Get image ID and URL for sideloaded image; replace {{IMAGE_ID}} and {{IMAGE_URL}} placeholders in $expected before comparison.

		$this->assertEquals(
			expected: $expected,
			actual: $block,
		);

		$this->assertCount(
			expectedCount: 1,
			haystack: $converter->get_created_attachment_ids(),
		);
		$this->assertRequestSent(
			url_or_callback: 'https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png',
			expected_times: 1,
		);
	}

	public function test_image_with_sideloading_disabled(): void {
		$html = <<<HTML
<img src="https://example.org/image.jpg" alt="Sample alt text" />
HTML;

		$result = ( new Block_Converter( $html, false ) )->convert();

		$this->assertEquals(
			expected: <<<HTML
<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="https://example.org/image.jpg" alt="Sample alt text"/></figure>
<!-- /wp:image -->
HTML,
			actual: $result,
		);
	}

	public static function image_dataprovider(): array {
		return [
			'image wrapped with figure/a' => [
				<<<HTML
<figure>
	<a href="https://alley.com/"><img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"></a>
</figure>
HTML,
				<<<HTML
<!-- wp:image {"lightbox":{"enabled":false},"id":{{IMAGE_ID}},"sizeSlug":"full","linkDestination":"custom"} -->
<figure class="wp-block-image size-full"><a href="https://alley.com/"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></a></figure>
<!-- /wp:image -->
HTML,
			],
			'image wrapped with figure/a with caption' => [
				<<<HTML
<figure>
	<a href="https://alley.com/"><img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"></a>
	<figcaption>Image caption</figcaption>
</figure>
HTML,
				<<<HTML
<!-- wp:image {"lightbox":{"enabled":false},"id":{{IMAGE_ID}},"sizeSlug":"full","linkDestination":"custom"} -->
<figure class="wp-block-image size-full"><a href="https://alley.com/"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></a><figcaption class="wp-element-caption">Image caption</figcaption></figure>
<!-- /wp:image -->
HTML,
			],
			'image wrapped with anchor' => [
				<<<HTML
<a href="https://alley.com/"><img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"></a>
HTML,
				<<<HTML
<!-- wp:image {"lightbox":{"enabled":false},"id":{{IMAGE_ID}},"sizeSlug":"full","linkDestination":"custom"} -->
<figure class="wp-block-image size-full"><a href="https://alley.com/"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></a></figure>
<!-- /wp:image -->
HTML,
			],
			'image wrapped with paragraph' => [
				<<<HTML
<p>Content before image. <img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"> Content after image.</p>
HTML,
				<<<HTML
<!-- wp:paragraph -->
<p>Content before image.</p>
<!-- /wp:paragraph -->

<!-- wp:image {"id":{{IMAGE_ID}},"sizeSlug":"full","linkDestination":"none","align":"right"} -->
<figure class="wp-block-image alignright size-full"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Content after image.</p>
<!-- /wp:paragraph -->
HTML,
			],
			'image wrapped with paragraph and anchor' => [
				<<<HTML
<p>Content before image. <a href="https://alley.com/"><img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text"></a> Content after image.</p>
HTML,
				<<<HTML
<!-- wp:paragraph -->
<p>Content before image.</p>
<!-- /wp:paragraph -->

<!-- wp:image {"lightbox":{"enabled":false},"id":{{IMAGE_ID}},"sizeSlug":"full","linkDestination":"custom","align":"right"} -->
<figure class="wp-block-image alignright size-full"><a href="https://alley.com/"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></a></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Content after image.</p>
<!-- /wp:paragraph -->
HTML,
			],
			'image not wrapped' => [
				<<<HTML
<img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Sample alt text">
HTML,
				<<<HTML
<!-- wp:image {"id":{{IMAGE_ID}},"sizeSlug":"full"} -->
<figure class="wp-block-image size-full"><img src="{{IMAGE_SRC}}" alt="Sample alt text" class="wp-image-{{IMAGE_ID}}"/></figure>
<!-- /wp:image -->
HTML,
			],
		];
	}

	public function test_convert_with_empty_paragraphs_of_arbitrary_length_to_block() {
		$arbitraryNewLines = str_repeat( "\n\r", mt_rand( 1, 1000) );
		$arbitrarySpaces = str_repeat( " ", mt_rand( 1, 1000 ) );

		$converter = new Block_Converter( '<p>bar</p><p></p><p>' . $arbitrarySpaces . $arbitraryNewLines . '</p>' );
		$block     = $converter->convert();

		$this->assertNotEmpty( actual: $block );
		$this->assertSame(
			expected: <<<HTML
<!-- wp:paragraph -->
<p>bar</p>
<!-- /wp:paragraph -->
HTML,
			actual: $block,
		);
	}

	public function test_convert_with_filter_override_single_tag() {
		$this->expectApplied( 'wp_block_converter_document_html' )->once();
		$this->expectApplied( 'wp_block_converter_block' )->once()->andReturnInstanceOf( Block::class );

		$html = <<<HTML
<p>Content to migrate</p>
<h1>Heading 01</h1>
HTML;

		add_filter(
			'wp_block_converter_block',
			function ( Block $block ) {
				remove_all_filters( 'wp_block_converter_block' );

				$block->content = 'Override content';

				return $block;
			}
		);


		$converter = new Block_Converter( $html );
		$block     = $converter->convert();


		$this->assertSame(
			expected: <<<HTML
<!-- wp:paragraph -->
Override content
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Heading 01</h1>
<!-- /wp:heading -->
HTML,
			actual: $block,
		);
	}

	public function test_convert_with_filter_override_entire_content() {
		$this->expectApplied( 'wp_block_converter_block' )->twice();
		$this->expectApplied( 'wp_block_converter_document_html' )->once();

		$html = <<<HTML
<p>Content to migrate</p>
<h1>Heading 01</h1>
HTML;

		add_filter( 'wp_block_converter_document_html', fn () => 'Override' );

		$converter = new Block_Converter( $html );
		$block     = $converter->convert();

		$this->assertSame(
			expected: 'Override',
			actual: $block,
		);
	}

	#[DataProvider( 'multi_line_pre_tag_data_provider' )]
	public function test_converting_multi_line_pre_tag( string $html, string $expected ) {
		$this->assertSame(
			expected: $expected,
			actual: ( new Block_Converter( $html ) )->convert(),
		);
	}

	public static function multi_line_pre_tag_data_provider(): array {
		return [
			[
				<<<HTML
<pre>
Line 1
Line 2

Line 3
</pre>
HTML,
				<<<HTML
<!-- wp:preformatted -->
<pre class="wp-block-preformatted">Line 1<br>Line 2<br><br>Line 3</pre>
<!-- /wp:preformatted -->
HTML,
			],
			[
				<<<HTML
<pre>
Lorem ipsum dolor sit amet, consectetur adipiscing elit.

1815 - Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
1900 - Ut enim ad minim veniam, quis nostrud exercitation ullamco.
1915 - Duis aute irure dolor in reprehenderit in voluptate velit.
1925 - Excepteur sint occaecat cupidatat non proident sunt in culpa.

Names: John Doe, Example Person (1800-1900)
		Jane Smith, Test Author (1850-1950)

Deaths: Lorem Ipsum, Historical Figure (1700-1800)
		Dolor Sit, Notable Person (1750-1850)
</pre>
HTML,
				<<<HTML
<!-- wp:preformatted -->
<pre class="wp-block-preformatted">Lorem ipsum dolor sit amet, consectetur adipiscing elit.<br><br>1815 - Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.<br>1900 - Ut enim ad minim veniam, quis nostrud exercitation ullamco.<br>1915 - Duis aute irure dolor in reprehenderit in voluptate velit.<br>1925 - Excepteur sint occaecat cupidatat non proident sunt in culpa.<br><br>Names: John Doe, Example Person (1800-1900)<br>		Jane Smith, Test Author (1850-1950)<br><br>Deaths: Lorem Ipsum, Historical Figure (1700-1800)<br>		Dolor Sit, Notable Person (1750-1850)</pre>
<!-- /wp:preformatted -->
HTML,
			],
		];
	}

	#[DataProvider( 'embed_data_provider' )]
	public function test_url_to_embed( string $html, string $expected, array $requests = [] ) {
		foreach ( $requests as $url => $response ) {
			$this->fake_request( $url )
				->with_response_code( 200 )
				->with_body( $response );
		}

		$converter = new Block_Converter( $html );
		$block     = $converter->convert();

		$this->assertNotEmpty( actual: $block );
		$this->assertSame(
			expected: $expected,
			actual: $block,
		);
	}

	public static function embed_data_provider(): array {
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
				[
					'https://www.youtube.com/oembed?maxwidth=500&maxheight=750&url=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3DdQw4w9WgXcQ&dnt=1&format=json' => '{"title":"Rick Astley - Never Gonna Give You Up (Official Music Video)","author_name":"Rick Astley","author_url":"https://www.youtube.com/@RickAstleyYT","type":"video","height":281,"width":500,"version":"1.0","provider_name":"YouTube","provider_url":"https://www.youtube.com/","thumbnail_height":360,"thumbnail_width":480,"thumbnail_url":"https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg","html":"\u003ciframe width=\u0022500\u0022 height=\u0022281\u0022 src=\u0022https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed\u0022 frameborder=\u00220\u0022 allow=\u0022accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share\u0022 allowfullscreen title=\u0022Rick Astley - Never Gonna Give You Up (Official Music Video)\u0022\u003e\u003c/iframe\u003e"}',
				],
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
				[
					'https://publish.x.com/oembed?url=https%3A%2F%2Fx.com%2Falleyco%2Fstatus%2F1679189879086018562' => '{"url":"https:\/\/twitter.com\/alleyco\/status\/1679189879086018562","author_name":"Alley","author_url":"https:\/\/twitter.com\/alleyco","html":"\u003Cblockquote class=\"twitter-tweet\"\u003E\u003Cp lang=\"en\" dir=\"ltr\"\u003EWe\'re a full-service digital agency with the foresight, perspective, and grit to power your brightest ideas and build solutions for your most evasive problems. Learn more about our services here:\u003Ca href=\"https:\/\/t.co\/8zZ5zP1Oyc\"\u003Ehttps:\/\/t.co\/8zZ5zP1Oyc\u003C\/a\u003E\u003C\/p\u003E&mdash; Alley (@alleyco) \u003Ca href=\"https:\/\/twitter.com\/alleyco\/status\/1679189879086018562?ref_src=twsrc%5Etfw\"\u003EJuly 12, 2023\u003C\/a\u003E\u003C\/blockquote\u003E\n\u003Cscript async src=\"https:\/\/platform.twitter.com\/widgets.js\" charset=\"utf-8\"\u003E\u003C\/script\u003E\n\n","width":550,"height":null,"type":"rich","cache_age":"3153600000","provider_name":"Twitter","provider_url":"https:\/\/twitter.com","version":"1.0"}',
				],
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
				[
					'https://publish.x.com/oembed?url=https%3A%2F%2Fx.com%2Falleyco%2Fstatus%2F1679189879086018562' => '{"url":"https:\/\/twitter.com\/alleyco\/status\/1679189879086018562","author_name":"Alley","author_url":"https:\/\/twitter.com\/alleyco","html":"\u003Cblockquote class=\"twitter-tweet\"\u003E\u003Cp lang=\"en\" dir=\"ltr\"\u003EWe\'re a full-service digital agency with the foresight, perspective, and grit to power your brightest ideas and build solutions for your most evasive problems. Learn more about our services here:\u003Ca href=\"https:\/\/t.co\/8zZ5zP1Oyc\"\u003Ehttps:\/\/t.co\/8zZ5zP1Oyc\u003C\/a\u003E\u003C\/p\u003E&mdash; Alley (@alleyco) \u003Ca href=\"https:\/\/twitter.com\/alleyco\/status\/1679189879086018562?ref_src=twsrc%5Etfw\"\u003EJuly 12, 2023\u003C\/a\u003E\u003C\/blockquote\u003E\n\u003Cscript async src=\"https:\/\/platform.twitter.com\/widgets.js\" charset=\"utf-8\"\u003E\u003C\/script\u003E\n\n","width":550,"height":null,"type":"rich","cache_age":"3153600000","provider_name":"Twitter","provider_url":"https:\/\/twitter.com","version":"1.0"}',
				],
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
<!-- wp:embed {"url":"https://www.facebook.com/sesametheopossum/posts/1329405240877426","type":"rich","providerNameSlug":"facebook","responsive":true} -->
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

	public function test_microsoft_word_importing(): void {
		$html = <<<HTML
<meta content="text/html; charset=utf-8" http-equiv="Content-Type"><meta content="Word.Document" name="ProgId"><meta content="Microsoft Word 12" name="Generator"><meta content="Microsoft Word 12" name="Originator"><link href="file:///C:%5CDOCUME%7E1%5C{{REDACTED}}%5CLOCALS%7E1%5CTemp%5Cmsohtmlclip1%5C01%5Cclip_filelist.xml" rel="File-List"><link href="file:///C:%5CDOCUME%7E1%5C{{REDACTED}}%5CLOCALS%7E1%5CTemp%5Cmsohtmlclip1%5C01%5Cclip_themedata.thmx" rel="themeData"><link href="file:///C:%5CDOCUME%7E1%5C{{REDACTED}}%5CLOCALS%7E1%5CTemp%5Cmsohtmlclip1%5C01%5Cclip_colorschememapping.xml" rel="colorSchemeMapping">\n
<!--[if gte mso 9]><xml> Normal0falsefalsefalseEN-USX-NONEX-NONEMicrosoftInternetExplorer4 </xml><![endif]-->\n
<!--[if gte mso 9]><![endif]-->\n
<!--[if gte mso 10]>&lt;style>/* Style Definitions */table.MsoNormalTable{mso-style-name:"Table Normal";mso-tstyle-rowband-size:0;mso-tstyle-colband-size:0;mso-style-noshow:yes;mso-style-priority:99;mso-style-qformat:yes;mso-style-parent:"";mso-padding-alt:0in 5.4pt 0in 5.4pt;mso-para-margin-top:0in;mso-para-margin-right:0in;mso-para-margin-bottom:10.0pt;mso-para-margin-left:0in;line-height:115%;mso-pagination:widow-orphan;font-size:11.0pt;font-family:"Calibri","sans-serif";mso-ascii-font-family:Calibri;mso-ascii-theme-font:minor-latin;mso-fareast-font-family:"Times New Roman";mso-fareast-theme-font:minor-fareast;mso-hansi-font-family:Calibri;mso-hansi-theme-font:minor-latin;}\n
&lt;/style><![endif]-->
<p class="MsoNormal" style="margin-bottom:12.0pt;line-height:115%"><b><span style="font-size:11.0pt;line-height:115%;font-family:&quot;Calibri&quot;,sans-serif;color:black">This is a test from Microsoft Word</span></b></p>
<p class="MsoNormal" style="margin-bottom:12.0pt;line-height:115%"><span style="font-size:11.0pt;line-height:115%;font-family:&quot;Calibri&quot;,sans-serif;color:black">Here is a link to <a href="https://alley.com" style="color:blue;text-decoration:underline">Alley</a></span></p>
<ul style="margin-top:0in" type="disc">
	<li class="MsoNormal" style="margin-bottom:12.0pt;line-height:115%"><span style="font-size:11.0pt;line-height:115%;font-family:&quot;Calibri&quot;,sans-serif;color:black">First item</span></li>
	<li class="MsoNormal" style="margin-bottom:12.0pt;line-height:115%"><span style="font-size:11.0pt;line-height:115%;font-family:&quot;Calibri&quot;,sans-serif;color:black">Second item</span></li>
</ul>
<p class="MsoNormal" style="margin-bottom:12.0pt;line-height:115%"><span style="font-size:11.0pt;line-height:115%;font-family:&quot;Calibri&quot;,sans-serif;color:black">Here is an image:</span></p>

<img border="0" src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Screen Shot 2022-01-19 at 2.51.37 PM" width="300" style="width:225.0pt;border:none;mso-border-alt:solid #000000 .5pt;mso-border-alt:solid windowtext .5pt;mso-padding-alt:0in 0in 0in 0in" />
HTML;

		$converted = ( new Block_Converter( $html, false ) )->convert();

		$this->assertMatchesSnapshot( actual: $converted );
	}

	public function test_macroable() {
		Block_Converter::macro(
			'special-tag',
			function (Node $node) {
				return new Block( 'paragraph', [ 'attribute' => '123' ], Block_Converter::get_node_html( $node ) );
			},
		);

		$block = ( new Block_Converter( '<special-tag>content here</special-tag>' ) )->convert();

		$this->assertEquals(
			expected: <<<HTML
<!-- wp:paragraph {"attribute":"123"} -->
<special-tag>content here</special-tag>
<!-- /wp:paragraph -->
HTML,
			actual: $block,
		);
	}

	/**
	 * Test that all elements can be manually overridden with a macro.
	 *
	 * This must be the last method in the class because it overrides
	 * built-in macros and does not remove them (yet).
	 */
	#[DataProvider( 'macroable_dataprovider' )]
	public function test_macroable_override_built_in( string $tag ): void {
		$is_single_tag = in_array( $tag, [ 'img', 'br', 'hr', 'source' ], true );

		Block_Converter::macro(
			$tag,
			fn ( \Dom\Node $node ) => new Block( 'core/paragraph', [], $is_single_tag ? strtolower( $node->nodeName ) : ( $node->textContent ?? '' ) ),
		);

		$block = ( new Block_Converter( $is_single_tag ? "<$tag />" : "<$tag>content here</$tag>" ) )->convert();

		if ( $is_single_tag ) {
			$this->assertEquals(
				expected: <<<HTML
<!-- wp:paragraph -->
$tag
<!-- /wp:paragraph -->
HTML,
				actual: $block,
			);
		} else {
			$this->assertEquals(
				expected: <<<HTML
<!-- wp:paragraph -->
content here
<!-- /wp:paragraph -->
HTML,
				actual: $block,
			);
		}
	}

	public static function macroable_dataprovider(): array {
		return collect( [
			'ul',
			'ol',
			'img',
			'blockquote',
			'h1',
			'h2',
			'h3',
			'h4',
			'h5',
			'h6',
			'p',
			'a',
			'abbr',
			'b',
			'code',
			'em',
			'i',
			'strong',
			'sub',
			'sup',
			'span',
			'u',
			'figure',
			'br',
			'cite',
			'source',
			'hr',
		] )->map_with_keys( fn ( $tag ) => [ $tag => [ $tag ] ] )->all();
	}

	public function test_convert_with_children(): void {
		$html = <<<HTML
<blockquote><p><em>Sint sint nulla voluptate nulla adipisicing non proident excepteur duis fugiat fugiat qui minim reprehenderit. Irure adipisicing mollit ipsum eiusmod consequat reprehenderit elit anim irure deserunt in deserunt. In dolore ut quis ex quis laboris ex eu. Minim culpa cillum eu.</em></p>
<blockquote>
<blockquote><p><em>-proident excepteur duis fugiat fugiat qui minim reprehenderit </em></p></blockquote>
</blockquote>
</blockquote>
HTML;

		$block = ( new Block_Converter( $html ) )->convert();

		$this->assertNotEmpty( actual: $block );
		$this->assertMatchesSnapshot( actual: $block );
	}
}
