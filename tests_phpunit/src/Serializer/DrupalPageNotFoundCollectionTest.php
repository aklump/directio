<?php

namespace AKlump\Directio\Tests\Serializer;

use AKlump\Directio\Serializer\DrupalPageNotFoundCollection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Serializer\DrupalPageNotFoundCollection
 */
class DrupalPageNotFoundCollectionTest extends TestCase {

  public function testDecodeDrupalReport() {
    $html = <<<EOD
<div class="block-system-main-block">
  <table>
    <thead>
      <tr><th>Count</th><th>Message</th></tr>
    </thead>
    <tbody>
      <tr>
        <td>10</td>
        <td>/foo</td>
      </tr>
      <tr>
        <td>5</td>
        <td>/bar</td>
      </tr>
    </tbody>
  </table>
</div>
EOD;

    $decoder = new DrupalPageNotFoundCollection();
    $data = $decoder->decode($html, DrupalPageNotFoundCollection::FORMAT);

    $this->assertCount(2, $data);
    $this->assertEquals(10, $data[0]['count']);
    $this->assertEquals('/foo', $data[0]['url']);
    $this->assertEquals(5, $data[1]['count']);
    $this->assertEquals('/bar', $data[1]['url']);
  }

  public function testDecodeEmptyData() {
    $decoder = new DrupalPageNotFoundCollection();
    $data = $decoder->decode('<html><body>No table here</body></html>', DrupalPageNotFoundCollection::FORMAT);
    $this->assertIsArray($data);
    $this->assertEmpty($data);
  }

  public function testSupportsDecoding() {
    $decoder = new DrupalPageNotFoundCollection();
    $this->assertTrue($decoder->supportsDecoding(DrupalPageNotFoundCollection::FORMAT));
    $this->assertFalse($decoder->supportsDecoding('json'));
  }
}
