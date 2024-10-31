<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit\Model;

use AKlump\Directio\Model\Document;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Model\Document
 * @uses   \AKlump\Directio\Lexer\TaskLexer
 * @uses   \AKlump\Directio\Lexer\AttributesLexer
 * @uses   \AKlump\Directio\TextProcessor\ParseAttributes
 */
class DocumentTest extends TestCase {

  use TestWithFilesTrait;

  public function testGetIdsDoesNotMakeUnique() {
    $document = new Document();
    $document->setContent('<!-- directio [] id=foo --><!-- /directio --><!-- directio [] id=foo --><!-- /directio -->');
    $ids = $document->getIds();
    $this->assertCount(2, $ids);
    $this->assertCount(1, array_unique($ids));
  }

  public function testGetIds() {
    $document = new Document();
    $content = file_get_contents($this->getTestFileFilepath('document.md'));
    $document->setContent($content);
    $ids = $document->getIds();
    $this->assertContains('install_runs_update', $ids);
    $this->assertContains('drush_control', $ids);
  }

  public static function dataFortestWithoutTaskProvider(): array {
    $tests = [];

    // Duplicate ids are both removed.
    $tests[] = [
      '<!-- directio [] id=foo --><!-- /directio --><!-- directio [] id=foo --><!-- /directio -->',
      'foo',
      '',
    ];
    // Duplicate ids are both removed.
    $tests[] = [
      "lorem\n\n<!-- directio [] id=foo -->\n\nipsum<!-- /directio -->\n\n",
      'foo',
      "lorem\n",
    ];

    $tests[] = ['', 'foo', ''];

    $tests[] = [
      "foo\n<!-- directio [] id=bar -->\nbar\n<!-- /directio -->\nbaz",
      'bar',
      "foo\n\nbaz\n",
    ];

    return $tests;
  }

  /**
   * @dataProvider dataFortestWithoutTaskProvider
   */
  public function testWithoutTask(string $content, string $id, string $expected) {
    $document = new Document();
    $result = $document->setContent($content)->withoutTask($id);
    $this->assertSame($expected, $result->getContent());
  }

  public function testSetGetContent() {
    $document = new Document();
    $content = $document->setContent('foo bar')->getContent();
    $this->assertSame('foo bar', $content);
  }
}
