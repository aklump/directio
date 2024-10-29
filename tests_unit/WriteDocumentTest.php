<?php

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\Document;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use AKlump\Directio\WriteDocument;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \AKlump\Directio\WriteDocument
 * @uses   \AKlump\Directio\Document
 */
class WriteDocumentTest extends TestCase {

  use TestWithFilesTrait;

  public function testCantWriteThrows() {
    $document = new Document();
    $file = $this->getTestFileFilepath('foo/lorem.md');
    $this->expectException(RuntimeException::class);
    (new WriteDocument())($file, $document);
  }

  public function testInvoke() {
    $document = (new Document())->setContent('foobar');
    $filepath = $this->getTestFileFilepath('/write_document.md');

    $this->deleteTestFile('write_document.md');
    $this->assertFileDoesNotExist($filepath);

    (new WriteDocument())($filepath, $document);
    $this->assertFileExists($filepath);
    $this->assertStringEqualsFile($filepath, 'foobar');

    $this->deleteTestFile('write_document.md');
  }

  protected function tearDown(): void {
    $this->deleteTestFile('foo/lorem.md');
  }


}
