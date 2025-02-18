<?php
/**
 * Class Block Block_Converter
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Block_Converter\Tests\Feature;

use Alley\WP\Block_Converter\Block;
use Alley\WP\Block_Converter\Block_Converter;
use DOMNode;
use Mantle\Testing\Concerns\Prevent_Remote_Requests;
use Mantle\Testkit\Test_Case;
use Mantle\Support\Str;
use Mantle\Testing\Concerns\Refresh_Database;
use PHPUnit\Framework\Attributes\DataProvider;

use function Mantle\Support\Helpers\collect;
use function Mantle\Testing\mock_http_response;

/**
 * Test case for Block Block_Converter Module.
 */
class BlockConverterTest extends Test_Case {
	use Prevent_Remote_Requests, Refresh_Database;

	protected function setUp(): void {
		parent::setUp();

		$this->fake_request( [
			'https://publish.twitter.com/oembed?maxwidth=500&maxheight=750&url=https%3A%2F%2Ftwitter.com%2Falleyco%2Fstatus%2F1679189879086018562&dnt=1&format=json' => mock_http_response()->with_json( '{"url":"https:\/\/twitter.com\/alleyco\/status\/1679189879086018562","author_name":"Alley","author_url":"https:\/\/twitter.com\/alleyco","html":"\u003Cblockquote class=\"twitter-tweet\" data-width=\"500\" data-dnt=\"true\"\u003E\u003Cp lang=\"en\" dir=\"ltr\"\u003EWe’re a full-service digital agency with the foresight, perspective, and grit to power your brightest ideas and build solutions for your most evasive problems. Learn more about our services here:\u003Ca href=\"https:\/\/t.co\/8zZ5zP1Oyc\"\u003Ehttps:\/\/t.co\/8zZ5zP1Oyc\u003C\/a\u003E\u003C\/p\u003E&mdash; Alley (@alleyco) \u003Ca href=\"https:\/\/twitter.com\/alleyco\/status\/1679189879086018562?ref_src=twsrc%5Etfw\"\u003EJuly 12, 2023\u003C\/a\u003E\u003C\/blockquote\u003E\n\u003Cscript async src=\"https:\/\/platform.twitter.com\/widgets.js\" charset=\"utf-8\"\u003E\u003C\/script\u003E\n\n","width":500,"height":null,"type":"rich","cache_age":"3153600000","provider_name":"Twitter","provider_url":"https:\/\/twitter.com","version":"1.0"}' ),
			'https://www.tiktok.com/oembed?maxwidth=500&maxheight=750&url=https%3A%2F%2Fwww.tiktok.com%2F%40atribecalledval%2Fvideo%2F7348705314746699054&dnt=1&format=json' => mock_http_response()->with_json( '{"version":"1.0","type":"video","title":"Andre 3000 performing at Luna Luna was such an incredible night. I will never forget this night. #losangeles #andre3000 #fyp #foryou #foryoupage ","author_url":"https://www.tiktok.com/@atribecalledval","author_name":"Valeria Cardona","width":"100%","height":"100%","html":"<blockquote class=\"tiktok-embed\" cite=\"https://www.tiktok.com/@atribecalledval/video/7348705314746699054\" data-video-id=\"7348705314746699054\" data-embed-from=\"oembed\" style=\"max-width:605px; min-width:325px;\"> <section> <a target=\"_blank\" title=\"@atribecalledval\" href=\"https://www.tiktok.com/@atribecalledval?refer=embed\">@atribecalledval</a> <p>Andre 3000 performing at Luna Luna was such an incredible night. I will never forget this night. <a title=\"losangeles\" target=\"_blank\" href=\"https://www.tiktok.com/tag/losangeles?refer=embed\">#losangeles</a> <a title=\"andre3000\" target=\"_blank\" href=\"https://www.tiktok.com/tag/andre3000?refer=embed\">#andre3000</a> <a title=\"fyp\" target=\"_blank\" href=\"https://www.tiktok.com/tag/fyp?refer=embed\">#fyp</a> <a title=\"foryou\" target=\"_blank\" href=\"https://www.tiktok.com/tag/foryou?refer=embed\">#foryou</a> <a title=\"foryoupage\" target=\"_blank\" href=\"https://www.tiktok.com/tag/foryoupage?refer=embed\">#foryoupage</a> </p> <a target=\"_blank\" title=\"♬ I swear, I Really Wanted To Make A\" href=\"https://www.tiktok.com/music/I-swear-I-Really-Wanted-To-Make-A-Rap-Album-But-This-Is-Literally-The-Way-The-Wind-Blew-Me-This-Time-7302364812792547330?refer=embed\">♬ I swear, I Really Wanted To Make A \"Rap\" Album But This Is Literally The Way The Wind Blew Me This Time - André 3000</a> </section> </blockquote> <script async src=\"https://www.tiktok.com/embed.js\"></script>","thumbnail_width":576,"thumbnail_height":1024,"thumbnail_url":"https://p19-pu-sign-useast8.tiktokcdn-us.com/obj/tos-useast5-p-0068-tx/afac3ae6ea3343c890e12e3cbbca1218_1711003872?lk3s=b59d6b55&nonce=81617&refresh_token=bf81ce66fb4d648cbd499791f37a6354&x-expires=1722110400&x-signature=tpTiBYwvSXjjAEgNRU2F%2BUAz7jo%3D&shp=b59d6b55&shcp=-","provider_url":"https://www.tiktok.com","provider_name":"TikTok","author_unique_id":"atribecalledval","embed_product_id":"7348705314746699054","embed_type":"video"}' ),
		] );

		// Delete all uploaded files between tests.
		$dir = wp_upload_dir();

		shell_exec( "rm -rf {$dir['path']}/*" );
	}

	#[DataProvider( 'converter_data_provider' )]
	public function test_convert_to_blocks( string $html, string $expected ) {
		$this->assertSame( $expected, ( new Block_Converter( $html ) )->convert() );
	}

	public static function converter_data_provider() {
		return [
			'paragraph' => [
				'<p>Content to migrate</p>',
				'<!-- wp:paragraph --><p>Content to migrate</p><!-- /wp:paragraph -->',
			],
			'empty-paragraphs' => [
				'<p>Content to migrate</p><p></p>',
				'<!-- wp:paragraph --><p>Content to migrate</p><!-- /wp:paragraph -->',
			],
			'paragraph-heading' => [
				'<p>Content to migrate</p><h1>Heading 01</h1>',
				'<!-- wp:paragraph --><p>Content to migrate</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":1} --><h1>Heading 01</h1><!-- /wp:heading -->',
			],
			'h1' => [
				'<h1>Another content</h1>',
				'<!-- wp:heading {"level":1} --><h1>Another content</h1><!-- /wp:heading -->',
			],
			'h2' => [
				'<h2>Another content</h2>',
				'<!-- wp:heading {"level":2} --><h2>Another content</h2><!-- /wp:heading -->',
			],
			'h3' => [
				'<h3>Another content</h3>',
				'<!-- wp:heading {"level":3} --><h3>Another content</h3><!-- /wp:heading -->',
			],
			'h4' => [
				'<h4>Another content</h4>',
				'<!-- wp:heading {"level":4} --><h4>Another content</h4><!-- /wp:heading -->',
			],
			'h5' => [
				'<h5>Another content</h5>',
				'<!-- wp:heading {"level":5} --><h5>Another content</h5><!-- /wp:heading -->',
			],
			'ol' => [
				'<ol><li>Random content</li><li>Another random content</li></ol>',
				'<!-- wp:list {"ordered":true} --><ol><li>Random content</li><li>Another random content</li></ol><!-- /wp:list -->',
			],
			'ul' => [
				'<ul><li>Random content</li><li>Another random content</li></ul>',
				'<!-- wp:list --><ul><li>Random content</li><li>Another random content</li></ul><!-- /wp:list -->',
			],
			'blockquote' => [
				'<blockquote><p>Lorem ipsum</p></blockquote>',
				'<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Lorem ipsum</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->',
			],
			'blockquote with cite' => [
				'<blockquote><p>Lorem ipsum</p><cite>Source</cite></blockquote>',
				'<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Lorem ipsum</p><!-- /wp:paragraph --><cite>Source</cite></blockquote><!-- /wp:quote -->',
			],
			'non-oembed-embed' => [
				'<embed type="video/webm" src="/media/mr-arnold.mp4" width="250" height="200" />',
				'<!-- wp:html --><embed type="video/webm" src="/media/mr-arnold.mp4" width="250" height="200"></embed><!-- /wp:html -->',
			],
		];
	}

	#[DataProvider( 'image_dataprovider' )]
	public function test_image( string $html, string $expected ) {
		$this->fake_request( 'https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png' )
			->with_file( __DIR__ . '/../fixtures/image.png' );

		$converter = new Block_Converter( $html );
		$block     = $converter->convert();

		$this->assertEquals( $expected, $block );

		$this->assertCount( 1, $converter->get_created_attachment_ids() );
		$this->assertRequestSent( 'https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png', 1 );
	}

	public static function image_dataprovider(): array {
		$url = wp_upload_dir()['url'];

		return [
			'image wrapped with figure/a' => [
				'<figure class="wp-block-image size-large"><a href="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png"><img decoding="async" loading="lazy" width="786" height="672" src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="" class="wp-image-5962"></a></figure>',
				'<!-- wp:image --><figure class="wp-block-image"><a href="' . $url . '/Screen-Shot-2022-01-19-at-2.51.37-PM.png"><img decoding="async" loading="lazy" width="786" height="672" src="' . $url . '/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="" class="wp-image-5962"></a></figure><!-- /wp:image -->'
			],
			'image wrapped with figure/a with caption' => [
				'<figure class="wp-block-image size-large"><a href="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png"><img decoding="async" loading="lazy" width="786" height="672" src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="" class="wp-image-5962"></a><figcaption>Image caption</figcaption></figure>',
				'<!-- wp:image --><figure class="wp-block-image"><a href="' . $url . '/Screen-Shot-2022-01-19-at-2.51.37-PM.png"><img decoding="async" loading="lazy" width="786" height="672" src="' . $url . '/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="" class="wp-image-5962"></a><figcaption>Image caption</figcaption></figure><!-- /wp:image -->'
			],
			'image wrapped with anchor' => [
				'<a href="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png"><img decoding="async" loading="lazy" width="786" height="672" src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="" class="wp-image-5962"></a>',
				'<!-- wp:image --><figure class="wp-block-image"><a href="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png"><img decoding="async" loading="lazy" width="786" height="672" src="' . $url . '/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="" class="wp-image-5962"></a></figure><!-- /wp:image -->',
			],
			'image wrapped with paragraph' => [
				'<p>Content to migrate <img decoding="async" loading="lazy" width="786" height="672" src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="" class="wp-image-5962"></p>',
				'<!-- wp:paragraph --><p>Content to migrate <img decoding="async" loading="lazy" width="786" height="672" src="' . $url . '/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="" class="wp-image-5962"></p><!-- /wp:paragraph -->'
			],
			'image wrapped with paragraph and anchor' => [
				'<p>Content to migrate <a href="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png"><img decoding="async" loading="lazy" width="786" height="672" src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="" class="wp-image-5962"></a></p>',
				'<!-- wp:paragraph --><p>Content to migrate <a href="' . $url . '/Screen-Shot-2022-01-19-at-2.51.37-PM.png"><img decoding="async" loading="lazy" width="786" height="672" src="' . $url . '/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="" class="wp-image-5962"></a></p><!-- /wp:paragraph -->'
			],
			'image not wrapped' => [
				'<img decoding="async" loading="lazy" width="786" height="672" src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="" class="wp-image-5962">',
				'<!-- wp:image --><figure class="wp-block-image"><img decoding="async" loading="lazy" width="786" height="672" src="' . $url . '/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="" class="wp-image-5962"></figure><!-- /wp:image -->',
			],
		];
	}

	public function test_convert_with_empty_paragraphs_of_arbitrary_length_to_block() {
		$arbitraryNewLines = str_repeat( "\n\r", mt_rand( 1, 1000) );
		$arbitrarySpaces = str_repeat( " ", mt_rand( 1, 1000 ) );

		$converter = new Block_Converter( '<p>bar</p><p></p><p>' . $arbitrarySpaces . $arbitraryNewLines . '</p>' );
		$block     = $converter->convert();

		$this->assertNotEmpty( $block );
		$this->assertSame(
			$block,
			'<!-- wp:paragraph --><p>bar</p><!-- /wp:paragraph -->',
		);
	}

	public function test_convert_with_filter_override_single_tag() {
		$this->expectApplied( 'wp_block_converter_document_html' )->once();
		$this->expectApplied( 'wp_block_converter_block' )->once()->andReturnInstanceOf( Block::class );

		$html = '<p>Content to migrate</p><h1>Heading 01</h1>';

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
			'<!-- wp:paragraph -->Override content<!-- /wp:paragraph -->

<!-- wp:heading {"level":1} --><h1>Heading 01</h1><!-- /wp:heading -->',
			$block,
		);
	}

	public function test_convert_with_filter_override_entire_content() {
		$this->expectApplied( 'wp_block_converter_block' )->twice();
		$this->expectApplied( 'wp_block_converter_document_html' )->once();

		$html = '<p>Content to migrate</p><h1>Heading 01</h1>';

		add_filter( 'wp_block_converter_document_html', fn () => 'Override' );

		$converter = new Block_Converter( $html );
		$block     = $converter->convert();

		$this->assertSame( 'Override', $block );
	}

	public function test_youtube_url_to_embed() {
		$this->fake_request( 'https://www.youtube.com/oembed?maxwidth=500&maxheight=750&url=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3DdQw4w9WgXcQ&dnt=1&format=json' )
			->with_response_code( 200 )
			->with_body( '{"title":"Rick Astley - Never Gonna Give You Up (Official Music Video)","author_name":"Rick Astley","author_url":"https://www.youtube.com/@RickAstleyYT","type":"video","height":281,"width":500,"version":"1.0","provider_name":"YouTube","provider_url":"https://www.youtube.com/","thumbnail_height":360,"thumbnail_width":480,"thumbnail_url":"https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg","html":"\u003ciframe width=\u0022500\u0022 height=\u0022281\u0022 src=\u0022https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed\u0022 frameborder=\u00220\u0022 allow=\u0022accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share\u0022 allowfullscreen title=\u0022Rick Astley - Never Gonna Give You Up (Official Music Video)\u0022\u003e\u003c/iframe\u003e"}' );

		$converter = new Block_Converter( '<p>https://www.youtube.com/watch?v=dQw4w9WgXcQ</p>' );
		$block     = $converter->convert();

		$this->assertNotEmpty( $block );
		$this->assertSame(
			'<!-- wp:embed {"url":"https://www.youtube.com/watch?v=dQw4w9WgXcQ","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} --><figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://www.youtube.com/watch?v=dQw4w9WgXcQ
</div></figure><!-- /wp:embed -->',
			$block,
		);
	}

	public function test_twitter_url_to_embed() {
		$converter = new Block_Converter( '<p>https://twitter.com/alleyco/status/1679189879086018562</p>' );
		$block     = $converter->convert();

		$this->assertNotEmpty( $block );
		$this->assertSame(
			'<!-- wp:embed {"url":"https://twitter.com/alleyco/status/1679189879086018562","type":"rich","providerNameSlug":"twitter","responsive":true} --><figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter"><div class="wp-block-embed__wrapper">
https://twitter.com/alleyco/status/1679189879086018562
</div></figure><!-- /wp:embed -->',
			$block,
		);
	}

	public function test_x_url_to_embed() {
		$this->fake_request( 'https://publish.x.com/oembed?url=https%3A%2F%2Fx.com%2Falleyco%2Fstatus%2F1679189879086018562' )
			->with_response_code( 200 )
			->with_body( '{"url":"https:\/\/twitter.com\/alleyco\/status\/1679189879086018562","author_name":"Alley","author_url":"https:\/\/twitter.com\/alleyco","html":"\u003Cblockquote class=\"twitter-tweet\"\u003E\u003Cp lang=\"en\" dir=\"ltr\"\u003EWe’re a full-service digital agency with the foresight, perspective, and grit to power your brightest ideas and build solutions for your most evasive problems. Learn more about our services here:\u003Ca href=\"https:\/\/t.co\/8zZ5zP1Oyc\"\u003Ehttps:\/\/t.co\/8zZ5zP1Oyc\u003C\/a\u003E\u003C\/p\u003E&mdash; Alley (@alleyco) \u003Ca href=\"https:\/\/twitter.com\/alleyco\/status\/1679189879086018562?ref_src=twsrc%5Etfw\"\u003EJuly 12, 2023\u003C\/a\u003E\u003C\/blockquote\u003E\n\u003Cscript async src=\"https:\/\/platform.twitter.com\/widgets.js\" charset=\"utf-8\"\u003E\u003C\/script\u003E\n\n","width":550,"height":null,"type":"rich","cache_age":"3153600000","provider_name":"Twitter","provider_url":"https:\/\/twitter.com","version":"1.0"}' );

		$converter = new Block_Converter( '<p>https://x.com/alleyco/status/1679189879086018562</p>' );
		$block     = $converter->convert();

		$this->assertNotEmpty( $block );
		$this->assertSame(
			'<!-- wp:embed {"url":"https://twitter.com/alleyco/status/1679189879086018562","type":"rich","providerNameSlug":"twitter","responsive":true} --><figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter"><div class="wp-block-embed__wrapper">
https://twitter.com/alleyco/status/1679189879086018562
</div></figure><!-- /wp:embed -->',
			$block,
		);
	}

	public function test_linked_x_url_to_embed() {
		$this->fake_request( 'https://publish.x.com/oembed?url=https%3A%2F%2Fx.com%2Falleyco%2Fstatus%2F1679189879086018562' )
			->with_response_code( 200 )
			->with_body( '{"url":"https:\/\/twitter.com\/alleyco\/status\/1679189879086018562","author_name":"Alley","author_url":"https:\/\/twitter.com\/alleyco","html":"\u003Cblockquote class=\"twitter-tweet\"\u003E\u003Cp lang=\"en\" dir=\"ltr\"\u003EWe’re a full-service digital agency with the foresight, perspective, and grit to power your brightest ideas and build solutions for your most evasive problems. Learn more about our services here:\u003Ca href=\"https:\/\/t.co\/8zZ5zP1Oyc\"\u003Ehttps:\/\/t.co\/8zZ5zP1Oyc\u003C\/a\u003E\u003C\/p\u003E&mdash; Alley (@alleyco) \u003Ca href=\"https:\/\/twitter.com\/alleyco\/status\/1679189879086018562?ref_src=twsrc%5Etfw\"\u003EJuly 12, 2023\u003C\/a\u003E\u003C\/blockquote\u003E\n\u003Cscript async src=\"https:\/\/platform.twitter.com\/widgets.js\" charset=\"utf-8\"\u003E\u003C\/script\u003E\n\n","width":550,"height":null,"type":"rich","cache_age":"3153600000","provider_name":"Twitter","provider_url":"https:\/\/twitter.com","version":"1.0"}' );

		$converter = new Block_Converter( '<p><a href="https://x.com/alleyco/status/1679189879086018562">https://x.com/alleyco/status/1679189879086018562</a></p>' );
		$block     = $converter->convert();

		$this->assertNotEmpty( $block );
		$this->assertSame(
			'<!-- wp:embed {"url":"https://twitter.com/alleyco/status/1679189879086018562","type":"rich","providerNameSlug":"twitter","responsive":true} --><figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter"><div class="wp-block-embed__wrapper">
https://twitter.com/alleyco/status/1679189879086018562
</div></figure><!-- /wp:embed -->',
			$block,
		);
	}

	public function test_instagram_url_to_embed() {
		$converter = new Block_Converter( '<p>https://www.instagram.com/p/CSpmSvAphdf/</p>' );
		$block     = $converter->convert();

		$this->assertNotEmpty( $block );
		$this->assertSame(
			'<!-- wp:embed {"url":"https://www.instagram.com/p/CSpmSvAphdf/","type":"rich","providerNameSlug":"instagram","responsive":true} --><figure class="wp-block-embed is-type-rich is-provider-instagram wp-block-embed-instagram"><div class="wp-block-embed__wrapper">
https://www.instagram.com/p/CSpmSvAphdf/
</div></figure><!-- /wp:embed -->',
			$block,
		);
	}

	public function test_facebook_url_to_embed() {
		$converter = new Block_Converter( '<p>https://www.facebook.com/sesametheopossum/posts/1329405240877426</p>' );
		$block     = $converter->convert();

		$this->assertNotEmpty( $block );
		$this->assertSame(
			'<!-- wp:embed {"url":"https://www.facebook.com/sesametheopossum/posts/1329405240877426","type":"rich","providerNameSlug":"embed-handler","responsive":true,"previewable":false} --><figure class="wp-block-embed is-type-rich is-provider-embed-handler wp-block-embed-embed-handler"><div class="wp-block-embed__wrapper">
https://www.facebook.com/sesametheopossum/posts/1329405240877426
</div></figure><!-- /wp:embed -->',
			$block,
		);
	}

	public function test_tiktok_url_to_embed() {
		$converter = new Block_Converter( '<p>https://www.tiktok.com/@atribecalledval/video/7348705314746699054</p>' );
		$block     = $converter->convert();

		$this->assertNotEmpty( $block );
		$this->assertSame(
			'<!-- wp:embed {"url":"https://www.tiktok.com/@atribecalledval/video/7348705314746699054","type":"video","providerNameSlug":"tiktok","responsive":true} --><figure class="wp-block-embed is-type-video is-provider-tiktok wp-block-embed-tiktok"><div class="wp-block-embed__wrapper">
https://www.tiktok.com/@atribecalledval/video/7348705314746699054
</div></figure><!-- /wp:embed -->',
			$block,
		);
	}

	public function test_macroable() {
		Block_Converter::macro(
			'special-tag',
			function (DOMNode $node) {
				return new Block( 'paragraph', [ 'attribute' => '123' ], Block_Converter::get_node_html( $node ) );
			},
		);

		$block = ( new Block_Converter( '<special-tag>content here</special-tag>' ) )->convert();

		$this->assertEquals(
			'<!-- wp:paragraph {"attribute":"123"} --><special-tag>content here</special-tag><!-- /wp:paragraph -->',
			$block,
		);
	}

	/**
	 * Test that all elements can be manually overridden with a macro.
	 */
	#[DataProvider( 'macroable_dataprovider' )]
	public function test_macroable_override_built_in( string $tag ): void {
		$is_single_tag = in_array( $tag, [ 'img', 'br', 'hr' ], true );

		Block_Converter::macro(
			$tag,
			fn ( \DOMNode $node ) => new Block( 'core/paragraph', [], $is_single_tag ? $node->nodeName : $node->textContent ),
		);

		$block = ( new Block_Converter( $is_single_tag ? "<$tag />" : "<$tag>content here</$tag>" ) )->convert();

		if ( $is_single_tag ) {
			$this->assertEquals(
				"<!-- wp:paragraph -->$tag<!-- /wp:paragraph -->",
				$block,
			);
		} else {
			$this->assertEquals(
				"<!-- wp:paragraph -->content here<!-- /wp:paragraph -->",
				$block,
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

		$this->assertNotEmpty( $block );
		$this->assertMatchesSnapshot( $block );
	}
}
