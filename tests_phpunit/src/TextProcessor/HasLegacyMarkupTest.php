<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\TextProcessor;

use AKlump\Directio\TextProcessor\HasLegacyMarkup;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\TextProcessor\HasLegacyMarkup
 * @uses \AKlump\Directio\TextProcessor\ScanLines
 */
class HasLegacyMarkupTest extends TestCase {

  public static function dataForTestInvokeProvider(): array {
    return [
      ['', FALSE],
      ['## Foo {#foo}', FALSE],
      ['<directio id="foo">', TRUE],
      ['<directio>', TRUE],
      ['<DIRECTIO id="foo">', TRUE],
      ['<directio/>', TRUE],
      ['<directiofoo>', FALSE],
      ["```html\n<directio id=\"old\">\n```\n", FALSE],
      ["```\n<directio id=\"old\">\n```\n<directio id=\"new\">\n", TRUE],
    ];
  }

  /**
   * @dataProvider dataForTestInvokeProvider
   */
  public function testInvoke(string $content, bool $expected) {
    $this->assertSame($expected, (new HasLegacyMarkup())($content));
  }

}
