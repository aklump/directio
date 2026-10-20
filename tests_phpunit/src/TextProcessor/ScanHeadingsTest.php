<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\TextProcessor;

use AKlump\Directio\TextProcessor\ScanHeadings;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\TextProcessor\ScanHeadings
 * @uses \AKlump\Directio\Model\Heading
 * @uses \AKlump\Directio\TextProcessor\ScanLines
 */
class ScanHeadingsTest extends TestCase {

  public function testAtxHeadingWithoutBlock() {
    $headings = (new ScanHeadings())("intro\n## Title ##\nbody\n");
    $this->assertCount(1, $headings);
    $h = $headings[0];
    $this->assertSame(2, $h->getLevel());
    $this->assertSame('Title', $h->getTitle());
    $this->assertFalse($h->hasBlock());
    $this->assertNull($h->getBlock());
    $this->assertNull($h->getBlockOffset());
    $this->assertSame(6, $h->getStart());
    $this->assertSame(18, $h->getEnd());
  }

  public function testAtxHeadingWithBlockReportsAbsoluteBlockOffset() {
    $content = "x\n### Foo {#a redo=P1M}\n";
    $h = (new ScanHeadings())($content)[0];
    $this->assertSame('Foo', $h->getTitle());
    $this->assertSame('{#a redo=P1M}', $h->getBlock());
    $this->assertSame('{', $content[$h->getBlockOffset()]);
    $this->assertSame($h->getBlock(), substr($content, $h->getBlockOffset(), strlen($h->getBlock())));
  }

  public function testSetextHeadingSpansTitleAndUnderline() {
    $content = "para\n\nPermissions {#p}\n===\nbody\n";
    $h = (new ScanHeadings())($content)[0];
    $this->assertSame(1, $h->getLevel());
    $this->assertSame('Permissions', $h->getTitle());
    $this->assertSame('{#p}', $h->getBlock());
    $this->assertSame(6, $h->getStart());
    $this->assertSame(strlen("para\n\nPermissions {#p}\n===\n"), $h->getEnd());
    $this->assertSame('{', $content[$h->getBlockOffset()]);
  }

  public function testDashesUnderParagraphIsLevelTwo() {
    $headings = (new ScanHeadings())("Finalize\n--------\n");
    $this->assertCount(1, $headings);
    $this->assertSame(2, $headings[0]->getLevel());
  }

  public function testHtmlListBlockQuoteAndBlankLinesAreNotSetextTitles() {
    $this->assertCount(0, (new ScanHeadings())("<feature>\n---\n"));
    $this->assertCount(0, (new ScanHeadings())("- item\n---\n"));
    $this->assertCount(0, (new ScanHeadings())("> quote\n---\n"));
    $this->assertCount(0, (new ScanHeadings())("\n---\n"));
    $this->assertCount(0, (new ScanHeadings())("---\n---\n"));
  }

  public function testAnAtxHeadingIsNotASetextTitle() {
    $headings = (new ScanHeadings())("# One\n---\n");
    $this->assertCount(1, $headings);
    $this->assertSame(1, $headings[0]->getLevel());
  }

  public function testNothingInsideAFenceIsAHeading() {
    $headings = (new ScanHeadings())("```\n# no\nTitle\n===\n```\n## yes\n");
    $this->assertCount(1, $headings);
    $this->assertSame('yes', $headings[0]->getTitle());
  }

  public function testBracketsAreNotABlock() {
    $h = (new ScanHeadings())("## Fix login [WIP]\n")[0];
    $this->assertFalse($h->hasBlock());
    $this->assertSame('Fix login [WIP]', $h->getTitle());
  }

}
