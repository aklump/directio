<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\TextProcessor;

use AKlump\Directio\Exception\InvalidAttributesException;
use AKlump\Directio\TextProcessor\ParseAttributes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\TextProcessor\ParseAttributes
 */
class ParseAttributesTest extends TestCase {

  public static function dataFortestInvokeProvider(): array {
    $tests = [];
    $tests[] = [
      '{#a}',
      ['id' => 'a'],
    ];
    $tests[] = [
      // A later duplicate key wins.
      '{#a id=b}',
      ['id' => 'b'],
    ];
    $tests[] = [
      '{done #a redo=P1M}',
      ['done' => TRUE, 'id' => 'a', 'redo' => 'P1M'],
    ];
    $tests[] = [
      '{id="lorem ipsum"}',
      ['id' => 'lorem ipsum'],
    ];
    $tests[] = [
      '{x}',
      ['x' => TRUE],
    ];
    $tests[] = [
      '{#install_runs_update}',
      ['id' => 'install_runs_update'],
    ];
    $tests[] = [
      '{#install_runs_update complete}',
      ['id' => 'install_runs_update', 'complete' => TRUE],
    ];
    $tests[] = [
      '{#foo redo=P5D}',
      ['id' => 'foo', 'redo' => 'P5D'],
    ];
    $tests[] = [
      '{#data-id fixture="my-fixture"}',
      ['id' => 'data-id', 'fixture' => 'my-fixture'],
    ];
    $tests[] = [
      '{#a x="a{b" y=""}',
      ['id' => 'a', 'x' => 'a{b', 'y' => ''],
    ];
    $tests[] = [
      '{}',
      [],
    ];
    $tests[] = [
      // Whitespace-only blocks parse to no attributes.
      '{ }',
      [],
    ];

    return $tests;
  }

  /**
   * @dataProvider dataFortestInvokeProvider
   */
  public function testInvoke(string $block, array $expected_attributes) {
    $attributes = (new ParseAttributes())($block);
    $this->assertEquals($expected_attributes, $attributes);
  }

  public static function dataFortestInvokeThrowsProvider(): array {
    $tests = [];
    // A single-quoted value is not supported.
    $tests[] = ["{id='foo'}"];
    // Spaces around "=" break the grammar.
    $tests[] = ['{id = foo}'];
    // A stray quote.
    $tests[] = ['{foo"}'];
    // Pandoc-style ".class" items are not supported.
    $tests[] = ['{#a .unnumbered}'];
    // Not wrapped in braces at all.
    $tests[] = ['#a'];
    // An unterminated quoted value.
    $tests[] = ['{id="foo}'];
    // An empty unquoted value.
    $tests[] = ['{id=}'];
    // An empty "#" id shorthand.
    $tests[] = ['{#}'];
    // An unquoted value may not contain "{".
    $tests[] = ['{#a x=a{b}'];

    return $tests;
  }

  /**
   * @dataProvider dataFortestInvokeThrowsProvider
   */
  public function testInvokeThrows(string $block) {
    $this->expectException(InvalidAttributesException::class);
    (new ParseAttributes())($block);
  }

}
