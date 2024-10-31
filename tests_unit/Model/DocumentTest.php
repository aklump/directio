<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit\Model;

use AKlump\Directio\Model\Document;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Model\Document
 * @uses   \AKlump\Directio\Lexer\TaskLexer
 * @uses   \AKlump\Directio\Lexer\AttributesLexer
 * @uses   \AKlump\Directio\TextProcessor\ParseAttributes
 */
class DocumentTest extends TestCase {

  public static function dataFortestWithoutTaskProvider(): array {
    $tests = [];

    $tests[] = ['', 'foo', ''];

    $tests[] = [
      "foo\n<!-- directio [] id=bar -->\nbar\n<!-- /directio -->\nbaz",
      'bar',
      "foo\n\nbaz",
    ];

    $_tests[] = [
      "# Some Thing

Excepteur sint occaecat cupidatat non proident laborum.
<!-- directio [] id=parent -->

* Lorem ipsum dolor sit amet, consectetur adipiscing elit.

<!-- directio [] id=child -->

* Ullamco laboris nisi ut aliquip ex ea commodo consequat<!-- /directio -->
* Ut enim ad minim veniam, quis nostrud exercitation.

<!-- /directio -->
Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.
",
      'child',
      "# Some Thing

Excepteur sint occaecat cupidatat non proident laborum.
<!-- directio [] id=parent -->

* Lorem ipsum dolor sit amet, consectetur adipiscing elit.

* Ut enim ad minim veniam, quis nostrud exercitation.

<!-- /directio -->
Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.
",
    ];

    return $tests;
  }

  /**
   * @dataProvider dataFortestWithoutTaskProvider
   */
  public function testWithoutTask(string $content, string $id, string $expected) {
    $document = new Document();
    $result = $document->setContent($content)->withoutTask($id);
    $foo = $result->getContent();
    $this->assertSame($expected, $result->getContent());
  }

  public function testContent() {
    $document = new Document();
    $content = $document->setContent('foo bar')->getContent();
    $this->assertSame('foo bar', $content);
  }
}
