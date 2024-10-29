<?php

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\ParseAttributes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\ParseAttributes
 * @uses   \AKlump\Directio\Lexer\AttributesLexer
 */
class ParseAttributesTest extends TestCase {

  public static function dataFortestInvokeProvider(): array {
    $tests = [];
    $tests[] = [
      'id=lorem ipsum dolar',
      [
        'id' => 'lorem ipsum dolar',
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
