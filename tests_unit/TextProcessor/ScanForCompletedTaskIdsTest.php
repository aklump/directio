<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit\TextProcessor;

use AKlump\Directio\TextProcessor\ScanForCompletedTasks;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\TextProcessor\ScanForCompletedTasks
 * @uses   \AKlump\Directio\Lexer\TaskLexer
 * @uses   \AKlump\Directio\Config\SpecialAttributes
 * @uses   \AKlump\Directio\Lexer\AttributesLexer
 * @uses   \AKlump\Directio\TextProcessor\ParseAttributes
 */
class ScanForCompletedTaskIdsTest extends TestCase {

  public static function dataFortestInvokeProvider(): array {
    $tests = [];
    $tests[] = ['', []];
    $tests[] = [
      '<!-- directio [x] id=foo -->',
      [
        [
          '[x]' => TRUE,
          'id' => 'foo',
        ],
      ],
    ];
    $tests[] = [
      'lorem <!-- directio [x] id=foo --> ipsum <!-- /directio --> dolar sit  <!-- directio [] id=bar --> ipsum <!-- /directio -->  dolar sit  <!-- directio [x] id=baz expires=P3M --> ipsum <!-- /directio --> alpha bravo',
      [
        [
          '[x]' => TRUE,
          'id' => 'foo',
        ],
        [
          '[x]' => TRUE,
          'id' => 'baz',
          'expires' => 'P3M',
        ],
      ],
    ];

    return $tests;
  }

  /**
   * @dataProvider dataFortestInvokeProvider
   */
  public function testInvoke(string $content, array $expected) {
    $result = (new ScanForCompletedTasks())($content);
    $this->assertSame($expected, $result);
  }
}
