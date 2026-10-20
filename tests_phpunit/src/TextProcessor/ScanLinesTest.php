<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\TextProcessor;

use AKlump\Directio\TextProcessor\ScanLines;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\TextProcessor\ScanLines
 */
class ScanLinesTest extends TestCase {

  public function testEmptyContentHasNoLines() {
    $this->assertSame([], (new ScanLines())(''));
  }

  public function testLinesConcatenateBackToContentWithOffsets() {
    $content = "one\r\ntwo\nthree";
    $lines = (new ScanLines())($content);
    $this->assertCount(3, $lines);
    $this->assertSame($content, implode('', array_column($lines, 'raw')));
    $this->assertSame([0, 5, 9], array_column($lines, 'offset'));
    $this->assertSame(['one', 'two', 'three'], array_column($lines, 'text'));
  }

  public function testTrailingNewlineDoesNotAddAnEmptyLine() {
    $lines = (new ScanLines())("one\n");
    $this->assertCount(1, $lines);
    $this->assertSame("one\n", $lines[0]['raw']);
  }

  public function testBacktickFenceMarksDelimitersAndContents() {
    $lines = (new ScanLines())("a\n```\n# b\n```\nc\n");
    $this->assertSame([FALSE, TRUE, TRUE, TRUE, FALSE], array_column($lines, 'inFence'));
  }

  public function testTildeFenceAndIndentedFence() {
    $lines = (new ScanLines())("~~~\nx\n~~~\n  ```\n  y\n  ```\n");
    $this->assertSame([TRUE, TRUE, TRUE, TRUE, TRUE, TRUE], array_column($lines, 'inFence'));
  }

  public function testCloserMustBeAtLeastAsLongAsOpener() {
    $lines = (new ScanLines())("````\n```\nstill fenced\n````\nout\n");
    $this->assertSame([TRUE, TRUE, TRUE, TRUE, FALSE], array_column($lines, 'inFence'));
  }

  public function testUnclosedFenceRunsToTheEnd() {
    $lines = (new ScanLines())("```\nx\ny");
    $this->assertSame([TRUE, TRUE, TRUE], array_column($lines, 'inFence'));
  }

}
