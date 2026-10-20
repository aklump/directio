<?php

namespace AKlump\Directio\Tests\Helper;

use AKlump\Directio\Helper\MarkTaskDoneInDocument;
use AKlump\Directio\Model\Document;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Helper\MarkTaskDoneInDocument
 * @uses \AKlump\Directio\Config\SpecialAttributes
 * @uses \AKlump\Directio\Model\Document
 * @uses \AKlump\Directio\Model\Task
 * @uses \AKlump\Directio\TextProcessor\ParseAttributes
 * @uses \AKlump\Directio\TextProcessor\ParseTasks
 * @uses \AKlump\Directio\Model\Heading
 * @uses \AKlump\Directio\TextProcessor\ScanHeadings
 * @uses \AKlump\Directio\TextProcessor\ScanLines
 */
class MarkTaskDoneInDocumentTest extends TestCase {

  public function testMarkTaskDoneAddsDoneAttribute() {
    $content = "## Task 1 {#task1 fixture=f1}\n\nbody\n";
    $document = (new Document())->setContent($content);
    $helper = new MarkTaskDoneInDocument();

    $updated = $helper('task1', $document);
    $this->assertStringContainsString('## Task 1 {done #task1 fixture=f1}', $updated->getContent());
  }

  public function testMarkTaskDoneDoesNotDuplicateDoneAttribute() {
    $content = "## Task 1 {done #task1 fixture=f1}\n\nbody\n";
    $document = (new Document())->setContent($content);
    $helper = new MarkTaskDoneInDocument();

    $updated = $helper('task1', $document);
    $this->assertEquals($content, $updated->getContent());
  }

  public function testMarkTaskDoneWithOnlyIdAttribute() {
    $content = "## Task 1 {#task1}\n\nbody\n";
    $document = (new Document())->setContent($content);
    $helper = new MarkTaskDoneInDocument();

    $updated = $helper('task1', $document);
    $this->assertStringContainsString('## Task 1 {done #task1}', $updated->getContent());
  }

  public function testMarkTaskDoneOnlyAffectsSpecifiedId() {
    $content = "## Task 1 {#task1 fixture=f1}\n\n## Task 2 {#task2 fixture=f2}\n";
    $document = (new Document())->setContent($content);
    $helper = new MarkTaskDoneInDocument();

    $updated = $helper('task2', $document);
    $this->assertStringContainsString('## Task 1 {#task1 fixture=f1}', $updated->getContent());
    $this->assertStringContainsString('## Task 2 {done #task2 fixture=f2}', $updated->getContent());
  }
}
