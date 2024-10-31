<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit\TextProcessor;

use AKlump\Directio\Exception\NestedTagsException;
use AKlump\Directio\Exception\NoClosingException;
use AKlump\Directio\Exception\NoIDException;
use AKlump\Directio\Exception\NoOpeningException;
use AKlump\Directio\TextProcessor\ValidateTaskSyntax;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\TextProcessor\ValidateTaskSyntax
 * @uses   \AKlump\Directio\Lexer\AttributesLexer
 * @uses   \AKlump\Directio\Lexer\TaskLexer
 * @uses   \AKlump\Directio\TextProcessor\ParseAttributes
 */
class ValidateTaskSyntaxTest extends TestCase {

  public static function dataFortestInvokeThrowsProvider(): array {
    $tests = [];
    $tests[] = [
      '<!-- directio [] id=foo --><!-- directio [] id=bar --><!-- /directio --><!-- /directio -->',
      NestedTagsException::class,
    ];
    $tests[] = [
      '<!-- directio -->foobar<!-- /directio -->',
      NoIDException::class,
    ];
    $tests[] = [
      '<!-- directio id=foo -->foobar',
      NoClosingException::class,
    ];
    $tests[] = [
      '<!-- directio lorem=ipsum -->foobar<!-- /directio -->',
      NoIDException::class,
    ];
    $tests[] = ['', NoOpeningException::class];

    return $tests;
  }

  /**
   * @dataProvider dataFortestInvokeThrowsProvider
   */
  public function testInvokeThrows(string $content, string $expected) {
    $this->expectException($expected);
    (new ValidateTaskSyntax())->__invoke($content);
  }
}
