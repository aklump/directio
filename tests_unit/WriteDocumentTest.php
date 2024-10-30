<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\IO\WriteDocument;
use AKlump\Directio\Model\Document;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \AKlump\Directio\IO\WriteDocument
 * @uses   \AKlump\Directio\Model\Document
 */
class WriteDocumentTest extends TestCase {

  use TestWithFilesTrait;

  public function testCantWriteThrows() {
    $document = new Document();
    $file = $this->getTestFileFilepath('.cache/lorem.md');
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
    $this->deleteTestFile('.cache/lorem.md');
  }


}
