<?php

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\ReadDocument;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \AKlump\Directio\ReadDocument
 * @uses   \AKlump\Directio\Document
 */
class ReadDocumentTest extends TestCase {

  use TestWithFilesTrait;

  public function testCanReadYaml() {
    $path = $this->getTestFileFilepath('/document.md');
    $document = (new ReadDocument())($path);
    $this->assertStringStartsWith('# My Instructions', $document->getContent());
  }

  public function testNoFileThrows() {
    $this->expectException(RuntimeException::class);
    $path = $this->getTestFileFilepath('/foo/document.md');
    (new ReadDocument())($path);
  }
}
