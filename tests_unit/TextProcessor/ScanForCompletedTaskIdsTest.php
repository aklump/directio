<?php

namespace AKlump\Directio\Tests\Unit\TextProcessor;

use AKlump\Directio\TextProcessor\ScanForCompletedTaskIds;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\TextProcessor\ScanForCompletedTaskIds
 * @uses   \AKlump\Directio\Lexer\TaskLexer
 * @uses   \AKlump\Directio\Config\SpecialAttributes
 * @uses   \AKlump\Directio\Lexer\AttributesLexer
 * @uses   \AKlump\Directio\TextProcessor\ParseAttributes
 */
class ScanForCompletedTaskIdsTest extends TestCase {

  public static function dataFortestInvokeProvider(): array {
    $tests = [];
    $tests[] = ['', []];
    $tests[] = ['<!-- directio [x] id=foo -->', ['foo']];
    $tests[] = [
      'lorem <!-- directio [x] id=foo --> ipsum <!-- /directio --> dolar sit  <!-- directio [] id=bar --> ipsum <!-- /directio -->  dolar sit  <!-- directio [x] id=baz --> ipsum <!-- /directio --> alpha bravo',
      ['foo', 'baz'],
    ];

    return $tests;
  }

  /**
   * @dataProvider dataFortestInvokeProvider
   */
  public function testInvoke(string $content, array $expected) {
    $result = (new ScanForCompletedTaskIds())($content);
    $this->assertSame($expected, $result);
  }
}
