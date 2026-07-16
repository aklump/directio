<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\IO;

use AKlump\Directio\IO\WriteDocument;
use AKlump\Directio\Model\Document;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
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
    $file = $this->getTestFilePath('project' . uniqid() . '/lorem.md');
    mkdir(dirname($file), 0755, TRUE);
    $this->expectException(RuntimeException::class);
    // Make the directory read-only so that file_put_contents fails
    chmod(dirname($file), 0555);
    (new WriteDocument())($file, $document);
  }

  public function testInvoke() {
    $document = (new Document())->setContent('foobar');
    $filepath = $this->getTestFilePath('/write_document.md');

    $this->deleteTestFile('write_document.md');
    $this->assertFileDoesNotExist($filepath);

    (new WriteDocument())($filepath, $document);
    $this->assertFileExists($filepath);
    $this->assertStringEqualsFile($filepath, 'foobar');

    $this->deleteTestFile('write_document.md');
  }

  public function testInvokeTrimsWhitespace() {
    $document = (new Document())->setContent("  foobar  \n\n");
    $filepath = $this->getTestFilePath('/write_document_trim.md');

    $this->deleteTestFile('write_document_trim.md');
    (new WriteDocument())($filepath, $document);
    $this->assertStringEqualsFile($filepath, 'foobar');

    $this->deleteTestFile('write_document_trim.md');
  }

  protected function tearDown(): void {
    $file = $this->getTestFilePath('lorem.md');
    chmod(dirname($file), 0755);
    $this->deleteAllTestFiles();
  }


}
