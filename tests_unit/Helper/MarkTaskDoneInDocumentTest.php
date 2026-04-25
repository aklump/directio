<?php

namespace AKlump\Directio\Tests\Unit\Helper;

use AKlump\Directio\Helper\MarkTaskDoneInDocument;
use AKlump\Directio\Model\Document;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Helper\MarkTaskDoneInDocument
 */
class MarkTaskDoneInDocumentTest extends TestCase {

  public function testMarkTaskDoneAddsDoneAttribute() {
    $content = '## Task 1' . PHP_EOL . '<directio id="task1" fixture="f1"></directio>';
    $document = (new Document())->setContent($content);
    $helper = new MarkTaskDoneInDocument();

    $updated = $helper('task1', $document);
    $this->assertStringContainsString('<directio done id="task1" fixture="f1">', $updated->getContent());
  }

  public function testMarkTaskDoneDoesNotDuplicateDoneAttribute() {
    $content = '## Task 1' . PHP_EOL . '<directio done id="task1" fixture="f1"></directio>';
    $document = (new Document())->setContent($content);
    $helper = new MarkTaskDoneInDocument();

    $updated = $helper('task1', $document);
    $this->assertEquals($content, $updated->getContent());
  }

  public function testMarkTaskDoneHandlesSelfClosingTag() {
    $content = '## Task 1' . PHP_EOL . '<directio id="task1" fixture="f1" />';
    $document = (new Document())->setContent($content);
    $helper = new MarkTaskDoneInDocument();

    $updated = $helper('task1', $document);
    // TaskLexer/AttributesLexer might not strictly follow XML/HTML but we want 'done' inside.
    $this->assertStringContainsString('<directio done id="task1" fixture="f1" />', $updated->getContent());
  }

  public function testMarkTaskDoneOnlyAffectsSpecifiedId() {
    $content = '<directio id="task1" fixture="f1"></directio>' . PHP_EOL . '<directio id="task2" fixture="f2"></directio>';
    $document = (new Document())->setContent($content);
    $helper = new MarkTaskDoneInDocument();

    $updated = $helper('task2', $document);
    $this->assertStringContainsString('<directio id="task1" fixture="f1">', $updated->getContent());
    $this->assertStringContainsString('<directio done id="task2" fixture="f2">', $updated->getContent());
  }
}
