<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\TextProcessor;

use AKlump\Directio\Exception\InvalidAttributesException;
use AKlump\Directio\TextProcessor\ParseTasks;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\TextProcessor\ParseTasks
 * @uses \AKlump\Directio\TextProcessor\ParseAttributes
 * @uses \AKlump\Directio\Model\Task
 */
class ParseTasksTest extends TestCase {

  public function testBareHeadingWithNoBlockIsNotATask() {
    $content = "# Title\n\nSome text\n";
    $tasks = (new ParseTasks())($content);
    $this->assertCount(0, $tasks);
  }

  public function testBracketedHeadingIsNotATask() {
    $content = "## Fix login [WIP]\n\nbody\n";
    $tasks = (new ParseTasks())($content);
    $this->assertCount(0, $tasks);
  }

  public function testEmptyContentHasNoTasks() {
    $tasks = (new ParseTasks())('');
    $this->assertCount(0, $tasks);
  }

  public function testDeeperHeadingStaysInBodyAndSameLevelHeadingCloses() {
    $content = "## Enable SSH Access {#ssh_on redo=P1D}\n\n"
      . "body line\n\n"
      . "### Details\n\n"
      . "more body\n\n"
      . "## Contrib Modules\n\n"
      . "trailer\n";
    $closerStart = strpos($content, '## Contrib Modules');

    $tasks = (new ParseTasks())($content);
    $this->assertCount(1, $tasks);
    $task = $tasks[0];
    $this->assertSame('ssh_on', $task->getAttributes()['id']);
    $this->assertSame(2, $task->getLevel());
    $this->assertSame(0, $task->getStart());
    $this->assertSame($closerStart, $task->getEnd());
  }

  public function testHashInsideBacktickFenceIsNotHeading() {
    $content = "```bash\n# comment\n```\n\n## Real Heading {#real}\n";
    $tasks = (new ParseTasks())($content);
    $this->assertCount(1, $tasks);
    $this->assertSame('real', $tasks[0]->getAttributes()['id']);
  }

  public function testHashInsideTildeFenceIsNotHeading() {
    $content = "~~~\n# comment\n~~~\n\n## Real Heading {#real}\n";
    $tasks = (new ParseTasks())($content);
    $this->assertCount(1, $tasks);
    $this->assertSame('real', $tasks[0]->getAttributes()['id']);
  }

  public function testIndentedFenceUnderListItemIsNotHeading() {
    $content = "## Task {#t}\n\n"
      . "- item\n\n"
      . "  ```\n"
      . "  # not a heading\n"
      . "  ```\n\n"
      . "more\n";
    $tasks = (new ParseTasks())($content);
    $this->assertCount(1, $tasks);
    $this->assertSame('t', $tasks[0]->getAttributes()['id']);
    $this->assertSame(strlen($content), $tasks[0]->getEnd());
  }

  public function testTaskAtEndOfFileWithNoFollowingHeading() {
    $content = '## Last Task {#last}' . "\n\n" . 'body text with no trailing newline';
    $tasks = (new ParseTasks())($content);
    $this->assertCount(1, $tasks);
    $this->assertSame('last', $tasks[0]->getAttributes()['id']);
    $this->assertSame(strlen($content), $tasks[0]->getEnd());
  }

  public function testClosingHashRunIsStrippedFromTitle() {
    $content = "## Foo {#a} ##\n";
    $tasks = (new ParseTasks())($content);
    $this->assertCount(1, $tasks);
    $this->assertSame('Foo', $tasks[0]->getTitle());
    $this->assertSame(['id' => 'a'], $tasks[0]->getAttributes());
  }

  public function testNestedTasksWithDifferentIdsBothParseInnermostFirst() {
    $content = "## Outer {#outer}\n\n### Inner {#inner}\n\nbody\n";
    $tasks = (new ParseTasks())($content);
    $this->assertCount(2, $tasks);
    $this->assertSame('inner', $tasks[0]->getAttributes()['id']);
    $this->assertSame('outer', $tasks[1]->getAttributes()['id']);
    $this->assertSame(strlen($content), $tasks[0]->getEnd());
    $this->assertSame(strlen($content), $tasks[1]->getEnd());
  }

  public function testNestedTasksWithTheSameIdBothParse() {
    $content = "## Dup {#x}\n\n### Dup Inner {#x}\n\nbody\n";
    $tasks = (new ParseTasks())($content);
    $this->assertCount(2, $tasks);
    $this->assertSame('x', $tasks[0]->getAttributes()['id']);
    $this->assertSame('x', $tasks[1]->getAttributes()['id']);
  }

  public function testBlockOffsetPointsAtTheOpeningBrace() {
    $content = '## Foo {#a}';
    $tasks = (new ParseTasks())($content);
    $this->assertSame('{', $content[$tasks[0]->getBlockOffset()]);
    $this->assertSame('{#a}', substr($content, $tasks[0]->getBlockOffset(), $tasks[0]->getBlockLength()));
  }

  public function testInvalidAttributeBlockPropagates() {
    $this->expectException(InvalidAttributesException::class);
    (new ParseTasks())("## Foo {id = bad}\n");
  }

}
