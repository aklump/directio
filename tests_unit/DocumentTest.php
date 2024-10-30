<?php

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\Model\Document;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Model\Document
 */
class DocumentTest extends TestCase {

  public function testContent() {
    $document = new Document();
    $content = $document->setContent('foo bar')->getContent();
    $this->assertSame('foo bar', $content);
  }
}
