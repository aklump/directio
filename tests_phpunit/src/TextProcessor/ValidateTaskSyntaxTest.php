<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\TextProcessor;

use AKlump\Directio\Exception\InvalidRedoException;
use AKlump\Directio\Exception\LegacySyntaxException;
use AKlump\Directio\Exception\NestedTasksException;
use AKlump\Directio\Exception\NoIDException;
use AKlump\Directio\TextProcessor\ValidateTaskSyntax;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\TextProcessor\ValidateTaskSyntax
 * @uses \AKlump\Directio\Config\SpecialAttributes
 * @uses \AKlump\Directio\Model\Task
 * @uses \AKlump\Directio\TextProcessor\ParseAttributes
 * @uses \AKlump\Directio\TextProcessor\ParseTasks
 */
class ValidateTaskSyntaxTest extends TestCase {

  public static function dataFortestInvokeThrowsProvider(): array {
    $tests = [];

    $tests[] = [
      "## Foo {#foo}\n\n### Bar {#bar}\n",
      NestedTasksException::class,
    ];
    $tests[] = [
      "## Foo {}\n\nfoobar\n",
      NoIDException::class,
    ];
    $tests[] = [
      "## Foo {lorem=ipsum}\n\nfoobar\n",
      NoIDException::class,
    ];
    $tests[] = [
      "## Foo {#ipsum}\n\nfoobar\n\n## Bar {lorem=ipsum}\n",
      NoIDException::class,
    ];
    $tests[] = [
      "## Foo {#foo redo=P1S}\n\nfoobar\n",
      InvalidRedoException::class,
    ];
    $tests[] = [
      '<directio id="foo">foobar</directio>',
      LegacySyntaxException::class,
    ];

    return $tests;
  }

  public function testInvokeWithMissingRedoDoesNotThrow() {
    (new ValidateTaskSyntax())("## Foo {#foo}\n\nfoobar\n");
    $this->assertTrue(TRUE);
  }

  public function testInvokeWithValidRedoDoesNotThrow() {
    (new ValidateTaskSyntax())("## Foo {#foo redo=P1D}\n\nfoobar\n");
    $this->assertTrue(TRUE);
  }

  /**
   * @dataProvider dataFortestInvokeThrowsProvider
   */
  public function testInvokeThrows(string $content, string $expected, string $message = '') {
    $this->expectException($expected);
    if ($message) {
      $this->expectExceptionMessage($message);
    }
    (new ValidateTaskSyntax())($content);
  }

  public function testNestedTasksExceptionNamesBothIds() {
    $this->expectException(NestedTasksException::class);
    $this->expectExceptionMessage('foo>bar');
    (new ValidateTaskSyntax())("## Foo {#foo}\n\n### Bar {#bar}\n");
  }

  public function testContentWithoutTagsDoesNotThrow() {
    $content = 'Lorem ipsum dolor sit amet.';
    (new ValidateTaskSyntax())($content);
    $this->assertTrue(TRUE);
  }

  public function testEmptyContentDoesNotThrow() {
    (new ValidateTaskSyntax())('');
    $this->assertTrue(TRUE);
  }
}
