<?php

namespace AKlump\Directio\Tests\Unit\Serializer;

use AKlump\Directio\Serializer\MhtmlDecoder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Serializer\MhtmlDecoder
 */
class MhtmlDecoderTest extends TestCase {

  public function testDecodeMhtml() {
    $mhtml = <<<EOD
MIME-Version: 1.0
Content-Type: multipart/related; boundary="----MultipartBoundary"

------MultipartBoundary
Content-Type: text/html
Content-Transfer-Encoding: quoted-printable

<html><body><a href="/foo">Foo</a></body></html>
------MultipartBoundary--
EOD;

    $decoder = new MhtmlDecoder();
    $html = $decoder->decode($mhtml, 'mhtml');
    $this->assertStringContainsString('<html><body><a href="/foo">Foo</a></body></html>', $html);
  }

  public function testDecodeQuotedPrintable() {
    $mhtml = <<<EOD
MIME-Version: 1.0
Content-Type: multipart/related; boundary="boundary"

--boundary
Content-Type: text/html
Content-Transfer-Encoding: quoted-printable

=3Chtml=3E=3Cbody=3EHello=3C/body=3E=3C/html=3E
--boundary--
EOD;

    $decoder = new MhtmlDecoder();
    $html = $decoder->decode($mhtml, 'mhtml');
    $this->assertEquals('<html><body>Hello</body></html>', trim($html));
  }

  public function testDecodeBase64() {
    $mhtml = <<<EOD
MIME-Version: 1.0
Content-Type: multipart/related; boundary="boundary"

--boundary
Content-Type: text/html
Content-Transfer-Encoding: base64

PGh0bWw+PGJvZHk+SGVsbG8gQmFzZTY0PC9ib2R5PjwvaHRtbD4=
--boundary--
EOD;

    $decoder = new MhtmlDecoder();
    $html = $decoder->decode($mhtml, 'mhtml');
    $this->assertEquals('<html><body>Hello Base64</body></html>', trim($html));
  }

  public function testSupportsDecoding() {
    $decoder = new MhtmlDecoder();
    $this->assertTrue($decoder->supportsDecoding('mhtml'));
    $this->assertFalse($decoder->supportsDecoding('json'));
  }
}
