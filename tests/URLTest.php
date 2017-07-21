<?php
namespace Lyte\URL\Tests;
use Lyte\URL\URL;
class URLTest extends TestCase {
	public function testTranslateLinkSiteRelative() {
		$url = new URL('http://example.com/foo/bar');
		$link = $url->translate('/');
		$this->assertSame('http://example.com/', $link);
	}
	
	public function testTranslateLinkRelative()	{
		$url = new URL('http://example.com/foo');
		$link = $url->translate('bar');
		$this->assertSame('http://example.com/bar', $link);
	}
	
	public function testTranslateLinkRelative2() {
		$url = new URL('http://example.com/foo/');
		$link = $url->translate('bar');
		$this->assertSame('http://example.com/foo/bar', $link);
	}
	
	public function testTranslateLinkRelative3() {
		// this was buggy previously
		$url = new URL('http://example.com/foo/');
		$link = $url->translate('./');
		$this->assertSame('http://example.com/foo/', $link);
		$url = new URL('http://example.com/foo');
		$link = $url->translate('./');
		$this->assertSame('http://example.com/', $link);
	}
	
	public function testTranslateLinkRelative4() {
		// this was buggy previously
		$url = new URL('http://example.com/foo/');
		$link = $url->translate('../');
		$this->assertSame('http://example.com/', $link);
	}

	public function testTranslateLinkRelativeExcessiveUps() {
		$url = new URL('http://example.com/');
		$link = $url->translate('../foo');
		$this->assertSame('http://example.com/foo', $link);
	}
	
	public function testTranslateLinkFragment() {
		// fragment specs that point to the local page should stay that way
		$url = new URL('http://example.com/foo/');
		$link = $url->translate('#bar');
		$this->assertSame('#bar', $link);
		$url = new URL('http://example.com/foo/?bar');
		$link = $url->translate('#bar');
		$this->assertSame('#bar', $link);

		$url = new URL('http://example.com/foo/');
		$link = $url->translate('http://example.com/bar/#bar');
		$this->assertSame('http://example.com/bar/#bar', $link);

		$url = new URL('http://example.com/');
		$link = $url->translate('/foo?bar#baz');
		$this->assertSame('http://example.com/foo?bar#baz', $link);
	}
	
	public function testTranslateLinkQuery() {
		$url = new URL('http://example.com/foo/');
		$link = $url->translate('?bar');
		$this->assertSame('http://example.com/foo/?bar', $link);
		$url = new URL('http://example.com/');
		$link = $url->translate('?bar');
		$this->assertSame('http://example.com/?bar', $link);
		$url = new URL('http://example.com/foo?bar');
		$link = $url->translate('?baz');
		$this->assertSame('http://example.com/foo?baz', $link);
		$url = new URL('http://example.com/foo?bar');
		$link = $url->translate('baz?qux');
		$this->assertSame('http://example.com/baz?qux', $link);
	}
	
	public function testTranslateLinkAbsolute() {
		$url = new URL('http://example.com/');
		$link = $url->translate('http://example.net/');
		$this->assertSame('http://example.net/', $link);
	}
	
	/**
	 * A scheme relative link starts with '//' and will maintain the same
	 * protocol that the browser is currently connected on
	 */
	public function testTranslateSchemeRelative() {
		$url = new URL('http://example.com/');
		$this->assertSame(
			'http://example.net/',
			$url->translate('//example.net/')
		);
		$url = new URL('https://example.com/');
		$this->assertSame(
			'https://example.net/',
			$url->translate('//example.net/')
		);
		$url = new URL('https://example.com/');
		$this->assertSame(
			'https://example.net/',
			$url->translate('//example.net')
		);
	}
	
	/**
	 * Make sure translating a link with spec://host but no path adds the
	 * missing path in
	 */
	public function testTranslateMissingPath() {
		$url = new URL('http://example.com/');
		$this->assertSame(
			'http://example.net/',
			$url->translate('http://example.net')
		);
		$this->assertSame(
			'https://example.net/',
			$url->translate('https://example.net')
		);
	}

	/**
	 * Test bug parsing this link: <a href="&#xA;                              #1963">
	 * browser interprets as just "#1963" because 0x10 is a "\n" char (i.e. whitespace)
	 */
	public function testWhiteSpaceNewLinePrefix() {
		$url = new URL('http://example.com/');
		$link = $url->translate("\n          #foo");
		$this->assertSame('#foo', $link);
	}

	/**
	 * Test new lines in path, e.g
	 * <a href="/part/of/path
	 * rest/of/path" />
	 */
	public function testNewLineInPath() {
		$url = new URL('http://example.com/');
		$link = $url->translate("/foo\n/bar");
		$this->assertSame('http://example.com/foo/bar', $link);
		$link = $url->translate("http://example.net/foo\n/bar");
		$this->assertSame('http://example.net/foo/bar', $link);
		$link = $url->translate("foo\n/bar");
		$this->assertSame('http://example.com/foo/bar', $link);
	}

	/**
	 * Test bug with protocol and domain lower casing.
	 * Some sites have links like "HTTP://DOMAINHERE/pages" we should
	 * lowercase the protocol and domain to ensure we don't duplicately import
	 * objects on such sites.
	 */
	public function testProtocolDomainCase() {
		$url = new URL('http://example.com/');
		$link = $url->translate("HTTP://example.com/PAGE");
		$this->assertSame('http://example.com/PAGE', $link);
		
		$url = new URL('http://example.com/');
		$link = $url->translate("http://EXAMPLE.com/PAGE");
		$this->assertSame('http://example.com/PAGE', $link);
		
		$url = new URL('http://example.com/');
		$link = $url->translate("http://EXAMPLE.com");
		$this->assertSame('http://example.com/', $link);
		
		$url = new URL('http://example.com/');
		$link = $url->translate("http://EXAMPLE.com:91");
		$this->assertSame('http://example.com:91/', $link);
	}

	/**
	 * Ensure if there's bonus protocol slashes (i.e http:///) that we
	 * still parse the expected URL
	 */
	public function testBonusSlashesTranslate() {
		$url = new URL('http://example.com/');

		$link = $url->translate("http:///example.com/foo");
		$this->assertEquals('http://example.com/foo', $link);

		$link = $url->translate("http:/example.com/foo");
		$this->assertEquals('http://example.com/foo', $link);
	}
	public function testBonusSlashesParseHREF() {
		$parts = URL::parseHREF('http:///foo/bar');
		$this->assertEquals(array(
			'scheme' => 'http',
			'host' => 'foo',
			'path' => '/bar',
		), $parts);

		$parts = URL::parseHREF('http:/foo/bar');
		$this->assertEquals(array(
			'scheme' => 'http',
			'host' => 'foo',
			'path' => '/bar',
		), $parts);
	}

	/**
	 * Spaces should be converted to %20
	 */
	public function testSpaceURL() {
		$url = new URL('http://example.com/');
		$link = $url->translate("http://example.com/ foo");
		$this->assertSame('http://example.com/%20foo', $link);

		// relative spaces
		$url = new URL('http://example.com/');
		$link = $url->translate("foo bar");
		$this->assertSame('http://example.com/foo%20bar', $link);

		// in query strings
		$url = new URL('http://example.com/foo?bar=1');
		$link = $url->translate("bar?baz=yay spaces&qux=1");
		$this->assertSame('http://example.com/bar?baz=yay%20spaces&qux=1', $link);
	}

	/**
	 * Ensure we normalise '$' in URLs
	 */
	public function testDolar() {
		$url = new URL('http://example.com/');
		$link = $url->translate("\$foo");
		$this->assertSame('http://example.com/%24foo', $link);
	}

	/**
	 * Don't translate empty links
	 */
	public function testEmptyLinks() {
		$url = new URL('http://example.com/');
		$link = $url->translate("");
		$this->assertSame('', $link);
		
		$url = new URL('http://example.com/');
		$link = $url->translate("   ");
		$this->assertSame('', $link);
	}

	/**
	 * Don't translate javascript or mailto links
	 */
	public function testIgnoredSchemes() {
		$url = new URL('http://example.com/');
		$tests = array(
			'javascript: foo',
			'mailto: foo@bar',
			'tel: +6123456789',
			'sms:',
			'sms://+6123456789',
			'sms://+6123456789?body=Foo%20Bar.',
			'data:image/jpg;base64,aaaaa'
		);
		foreach ($tests as $test) {
			$this->assertEquals(
				$test, $url->translate($test)
			);
		}
	}

	/**
	 * guiValue links breaking - bug identified in real world profile
	 */
	public function testGuiLink() {
		$url = new URL('http://example.com/www/html/7-home-page.asp');
		$link = $url->translate("http://example.com/www/default.asp?guiValue=22B33E10-E6E9-44A4-9910-4DD5DC2B5544");
		$this->assertSame('http://example.com/www/default.asp?guiValue=22B33E10-E6E9-44A4-9910-4DD5DC2B5544', $link);
	}

	/**
	 * fragment on pdfs breaking - bug identified in real world profile
	 *
	 * <a href="http://www.example.com/upload/foo.pdf#s.76A" target="_blank">
	 * was importing as:
	 * <a href="http://www.example.com/upload/foo.pdfs.76A" target="_blank">	 
	 */
	public function testFragmentOnPdf() {
		$url = new URL('www.example.com/jsp/content/NavigationController.do?areaID=26&tierID=1&navID=D9A36C887F00000100BCE28AABAC406A&navLink=null&pageID=1330');
		$link = $url->translate("http://www.example.com/upload/foo.pdf#s.76A");
		$this->assertSame('http://www.example.com/upload/foo.pdf#s.76A', $link);
	}//*/

	/**
	 * URLs stringify correctly
	 */
	public function testURLStringify() {
		$str = 'http://foo.bar/';
		$url = new URL($str);
		$this->assertEquals($str, (string)($url));
	}

	/**
	 * URLs barf if given !strings
	 *
	 * @expectedException InvalidArgumentException
	 */
	public function testURLNonString() {
		new URL(NULL);
	}

	/**
	 * Can determine scheme of URL
	 */
	public function testURLScheme() {
		$url = new URL('http://example.com');
		$this->assertEquals('http', $url->getScheme());
		$url = new URL('https://example.com');
		$this->assertEquals('https', $url->getScheme());
		$url = new URL('ftp://example.com');
		$this->assertEquals('ftp', $url->getScheme());
	}

	/**
	 * Throw error on empty link
	 *
	 * @expectedException InvalidArgumentException
	 */
	public function testURLEmpty() {
		$url = new URL('');
	}

	/**
	 * Create URLs from existing URLs
	 *
	 * This essentially deprecates coerce()
	 */
	public function testCreateFromExisting() {
		$urls = array(
			'http://example.com/',
			'https://example.com:123/foo?bar#baz',
		);
		foreach ($urls as $surl) {
			$url = new URL(new URL($surl));
			$this->assertInstanceOf('Lyte\url\\URL', $url);
			$this->assertEquals($surl, (string)$url);
		}
	}

	/**
	 * Coercion shortcut
	 */
	public function testCoercionShortcut() {
		$url = 'http://example.com/';
		$url = URL::coerce($url);
		$this->assertInstanceOf('Lyte\\url\\URL', $url);

		// doesn't break __toString() on invalid urls
		$url = new URL('foo');
		$this->assertEquals('foo', (string)$url);
		$newUrl = new URL($url);
		$this->assertEquals('foo', (string)$newUrl);
		
		$orig_url = new URL('http://example.com/');
		$url = URL::coerce($orig_url);
		$this->assertTrue(
			$orig_url === $url,
			"coercion shouldn't create new objects if type is already correct"
		);
	}

	/**
	 * Get basename
	 */
	public function testGetBasename() {
		$url = new URL('http://example.com/kitten.jpg');
		$this->assertEquals('kitten.jpg', $url->getBasename());
	}

	/**
	 * Get scheme
	 */
	public function testGetScheme() {
		$url = new URL('http://example.com/');
		$this->assertEquals('http', $url->getScheme());
		$url = new URL('https://example.com/');
		$this->assertEquals('https', $url->getScheme());
		$url = new URL('ftp://example.com/');
		$this->assertEquals('ftp', $url->getScheme());
	}

	/**
	 * Get host
	 */
	public function testGetHost() {
		$url = new URL('http://example.com/');
		$this->assertEquals('example.com', $url->getHost());
		$url = new URL('http://127.0.0.1/');
		$this->assertEquals('127.0.0.1', $url->getHost());
	}

	/**
	 * Get port
	 */
	public function testGetPort() {
		$url = new URL('http://example.com/');
		$this->assertEquals('', $url->getPort());
		$url = new URL('http://example.com:80/');
		$this->assertEquals('80', $url->getPort());
	}

	/**
	 * Get path
	 */
	public function testGetPath() {
		$url = new URL('http://example.com/');
		$this->assertEquals('/', $url->getPath());
		$url = new URL('http://example.com/foo/bar/baz');
		$this->assertEquals('/foo/bar/baz', $url->getPath());
	}

	/**
	 * Get query
	 */
	public function testGetQuery() {
		$url = new URL('http://example.com/?a=b&b=c');
		$this->assertEquals('a=b&b=c', $url->getQuery());

		$url = new URL('http://example.com/');
		$this->assertEquals('', $url->getQuery());
	}

	/**
	 * Get fragment, e.g the bit after the hash
	 */
	public function testGetFragment() {
		$url = new URL('http://example.com/#foobar');
		$this->assertEquals('foobar', $url->getFragment());

		$url = new URL('http://example.com/');
		$this->assertEquals('', $url->getFragment());
	}

	/**
	 * Buggy scheme + target
	 *
	 * We can fairly safely assume that it's just meant to be a fragment spec.
	 */
	public function testHttpHash() {
		$url = new URL('http://example.com/');
		$link = $url->translate("http://#NewWorkOrders");
		$this->assertEquals('#NewWorkOrders', $link);
		// and https
		$link = $url->translate("https://#NewWorkOrders");
		$this->assertEquals('#NewWorkOrders', $link);
	}

	/**
	 * Treat '\' as '/'
	 *
	 * Sometimes sites just get their slashes wrong...
	 * Firefox breaks on this, but Chrome corrects the backslashes to 
	 * slashes. We want the Chrome behaviour.
	 */
	public function testBackslashSiteRelative() {
		$url = new URL('http://example.com/foo/');
		$link = $url->translate("\\bar");
		$this->assertEquals('http://example.com/bar', $link);
	}
	public function testBackslashRelative() {
		$url = new URL('http://example.com/foo/');
		$link = $url->translate("bar\\baz");
		$this->assertEquals('http://example.com/foo/bar/baz', $link);
	}
	public function testBackslashPathInFullURL() {
		$url = new URL('http://example.com/');
		$link = $url->translate("http://example.com\\foo");
		$this->assertEquals('http://example.com/foo', $link, 'should translate slashes in path on full urls');
	}
	public function testBackslashPathInFullURLWithPort() {
		$url = new URL('http://example.com/');
		$link = $url->translate("http://example.com:80\\foo");
		$this->assertEquals('http://example.com:80/foo', $link, 'should translate slashes in path on full urls');
	}
	public function testBackslashScheme() {
		$url = new URL('http://example.com/');
		$link = $url->translate("http:\\\\example.com/");
		$this->assertEquals('http://example.com/', $link, 'should translate slashes in scheme');
	}
	public function testBackslashQuery() {
		$url = new URL('http://example.com/');
		$link = $url->translate("?\\");
		$this->assertEquals('http://example.com/?\\', $link, 'should not translate slashes in query');
	}
	public function testBackslashFragment() {
		$url = new URL('http://example.com/');
		$link = $url->translate("#\\");
		$this->assertEquals('#\\', $link, 'should not translate slashes in fragments');
	}

	/**
	 * Putting a URL in a query string parameter was highlighting a bug in PHP's inbuilt parse_url()
	 */
	public function testURLInQueryValue() {
		$url = new URL('http://example.com/');
		$link = $url->translate("/foo.php?url=http://example.net/");
		$this->assertEquals('http://example.com/foo.php?url=http://example.net/', $link);
	}

	/**
	 * Build up a new URL using modified components
	 *
	 * We may want to massage parts of the URL, but we want to keep the URL
	 * immutable so that it can't be changed under the hood while it's used in
	 * another object.
	 */
	public function testRebuildURL() {
		$url = new URL(
			'http://example.com/?a=b&b=c',
			array(
				'query' => 'c=d&d=e',
			)
		);
		$this->assertEquals('c=d&d=e', $url->getQuery());
		$this->assertEquals('http://example.com/?c=d&d=e', (string)$url);

		$url = new URL(
			new URL('http://example.com/'),
			array(
				'query' => 'c=d&d=e',
			)
		);
		$this->assertEquals('c=d&d=e', $url->getQuery());
		$this->assertEquals('http://example.com/?c=d&d=e', (string)$url);

		$url = new URL(
			new URL('http://example.com/'),
			array(
				'host' => 'foo',
				'path' => '/bar',
				'query' => 'baz',
			)
		);
		$this->assertEquals('http://foo/bar?baz', (string)$url);
	}

	/**
	 * Alternative to PHP's inbuilt parse_url()
	 */
	public function testParseHREFPathOnly() {
		$this->assertEquals(
			array(
				'path' => '/foo.php',
			),
			URL::parseHREF('/foo.php')
		);
	}
	public function testParseHREFTopLevel() {
		$this->assertEquals(
			array(
				'scheme' => 'http',
				'host' => 'example.com',
				'path' => '/',
			),
			URL::parseHREF('http://example.com/')
		);
	}
	public function testParseHREFNoPath() {
		$this->assertEquals(
			array(
				'scheme' => 'http',
				'host' => 'example.com',
			),
			URL::parseHREF('http://example.com')
		);
	}
	public function testParseHREFRelativeQueryWithURLVal() {
		$this->assertEquals(
			array(
				'path' => '/foo.php',
				'query' => 'url=http://example.net/',
			),
			URL::parseHREF('/foo.php?url=http://example.net/')
		);
	}
	public function testParseHREFNoPathAfterPort() {
		$this->assertEquals(
			array(
				'scheme' => 'http',
				'host' => 'EXAMPLE.com',
				'port' => 91,
			),
			URL::parseHREF("http://EXAMPLE.com:91")
		);
	}
	public function testParseHREFNewLineInPath() {
		$this->assertEquals(
			array(
				'scheme' => 'http',
				'host' => 'example.com',
				'path' => "/foobar",
			),
			URL::parseHREF("http://example.com/foo\nbar")
		);
	}
	public function testParseHREFJavascript() {
		$url = "javascript: some js here";
		$this->assertEquals(
			parse_url($url),
			URL::parseHREF($url)
		);
	}
	public function testParseHREFMailto() {
		$url = "mailto:user@host";
		$this->assertEquals(
			parse_url($url),
			URL::parseHREF($url)
		);
	}
	public function testParseHREFTel() {
		$url = "tel:666";
		// parse_url() gets this wrong
		$this->assertEquals(
			array(
				'scheme' => 'tel',
				'path' => '666',
			),
			URL::parseHREF($url)
		);
	}
	public function testParseHREFSms() {
		$this->assertEquals(
			array(
				'scheme' => 'sms',
			),
			URL::parseHREF('sms:')
		);
		$this->assertEquals(
			array(
				'scheme' => 'sms',
				'path' => '666',
			),
			URL::parseHREF('sms:666')
		);
		$this->assertEquals(
			array(
				'scheme' => 'sms',
				'path' => '666',
				'query' => 'body=Foo%20Bar.',
			),
			URL::parseHREF('sms:666?body=Foo%20Bar.')
		);
	}
	public function testParseHREFData() {
		$url = "data:image/jpg;base64,aaaaa";
		$this->assertEquals(
			array(
				'scheme' => 'data',
				'path' => 'image/jpg;base64,aaaaa',
			),
			URL::parseHREF($url)
		);
	}

	/**
	 * Test dots are handled correctly.
	 * 
	 * @return void
	 */
	public function testDotsHandledCorrectly()
	{
		$url      = new URL('http://foo.com/bar/1');
		$input    = 'http://foo.com/bar/.';
		$expected = 'http://foo.com/bar/';
		$actual   = $url->translate($input);
		$this->assertEquals($expected, $actual);

	}//end testDotsHandledCorrectly()


}
