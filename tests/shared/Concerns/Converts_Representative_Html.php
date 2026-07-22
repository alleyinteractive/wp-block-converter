<?php
/**
 * Trait Converts_Representative_Html
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter\Tests\Shared\Concerns;

use Alley\WP\Block_Converter\Block_Converter;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Core HTML-to-block conversion coverage that needs nothing WordPress
 * specific — reused by both the WordPress and standalone suites so the two
 * environments are proven to produce identical output rather than
 * maintaining two copies of the same assertions.
 */
trait Converts_Representative_Html {
	#[DataProvider( 'converter_data_provider' )]
	public function test_convert_to_blocks( string $html, string $expected ): void {
		$this->assertSame(
			expected: $expected,
			actual: ( new Block_Converter( $html ) )->convert(),
		);
	}

	public static function converter_data_provider(): array {
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
<embed type="video/webm" src="/media/mr-arnold.mp4" width="250" height="200"/>
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

	public function test_convert_with_empty_paragraphs_of_arbitrary_length_to_block(): void {
		$arbitraryNewLines = str_repeat( "\n\r", mt_rand( 1, 1000 ) );
		$arbitrarySpaces   = str_repeat( ' ', mt_rand( 1, 1000 ) );

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

	public function test_images_are_left_untouched_without_an_uploader(): void {
		$converter = new Block_Converter(
			html: <<<HTML
<img src="https://example.org/image.jpg" alt="Sample alt text" />
HTML,
		);

		$this->assertSame(
			expected: <<<HTML
<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="https://example.org/image.jpg" alt="Sample alt text"/></figure>
<!-- /wp:image -->
HTML,
			actual: $converter->convert(),
		);
		$this->assertSame( [], $converter->get_created_attachment_ids() );
	}

	public function test_image_with_srcset_and_sizes_attributes_removed(): void {
		$converter = new Block_Converter(
			html: <<<HTML
<img src="https://example.org/image.jpg" srcset="https://example.org/image.jpg 1x, https://example.org/image-2x.jpg 2x" sizes="(max-width: 600px) 100vw, 600px" alt="Sample alt text" />
HTML,
		);

		$this->assertSame(
			expected: <<<HTML
<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="https://example.org/image.jpg" alt="Sample alt text"/></figure>
<!-- /wp:image -->
HTML,
			actual: $converter->convert(),
		);
	}

	#[DataProvider( 'multi_line_pre_tag_data_provider' )]
	public function test_converting_multi_line_pre_tag( string $html, string $expected ): void {
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

		$converted = ( new Block_Converter( $html ) )->convert();

		$this->assertSame(
			expected: <<<HTML
<!-- wp:paragraph -->
<p><strong>This is a test from Microsoft Word</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Here is a link to <a href="https://alley.com">Alley</a></p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>First item</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Second item</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Here is an image:</p>
<!-- /wp:paragraph -->

<!-- wp:image -->
<figure class="wp-block-image"><img src="https://alley.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-2.51.37-PM.png" alt="Screen Shot 2022-01-19 at 2.51.37 PM"/></figure>
<!-- /wp:image -->
HTML,
			actual: $converted,
		);
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
		$this->assertSame(
			expected: <<<HTML
<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p><em>Sint sint nulla voluptate nulla adipisicing non proident excepteur duis fugiat fugiat qui minim reprehenderit. Irure adipisicing mollit ipsum eiusmod consequat reprehenderit elit anim irure deserunt in deserunt. In dolore ut quis ex quis laboris ex eu. Minim culpa cillum eu.</em></p>
<!-- /wp:paragraph -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p><em>-proident excepteur duis fugiat fugiat qui minim reprehenderit</em></p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote --></blockquote>
<!-- /wp:quote --></blockquote>
<!-- /wp:quote -->
HTML,
			actual: $block,
		);
	}
}
