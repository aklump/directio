<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\TextProcessor\ParseAttributes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\TextProcessor\ParseAttributes
 * @uses   \AKlump\Directio\Lexer\AttributesLexer
 */
class ParseAttributesTest extends TestCase {

  public static function dataFortestInvokeProvider(): array {
    $tests = [];
    $tests[] = [
      '<!-- directio [ ] -->',
      ['[ ]' => TRUE],
    ];
    $tests[] = [
      '<!-- directio [] -->',
      ['[]' => TRUE],
    ];
    $tests[] = [
      '<!-- directio [x] -->',
      ['[x]' => TRUE],
    ];
    $tests[] = [
      'id="lorem ipsum dolar"',
      [
        'id' => 'lorem ipsum dolar',
      ],
    ];
    $tests[] = [
      '<!-- directio id=install_runs_update -->',
      [
        'id' => 'install_runs_update',
      ],
    ];
    $tests[] = [
      '<!-- directio id=install_runs_update complete -->',
      [
        'id' => 'install_runs_update',
        'complete' => TRUE,
      ],
    ];
    $tests[] = [
      'id=foo expires=P5D',
      [
        'id' => 'foo',
        'expires' => 'P5D',
      ],
    ];

    return $tests;
  }

  /**
   * @dataProvider dataFortestInvokeProvider
   */
  public function testInvoke(string $open_tag, array $expected_attributes) {
    $attributes = (new ParseAttributes())($open_tag);
    $this->assertEquals($expected_attributes, $attributes);
  }
}
