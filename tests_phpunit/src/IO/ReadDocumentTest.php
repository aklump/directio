<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\IO;

use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \AKlump\Directio\IO\ReadDocument
 * @uses   \AKlump\Directio\Model\Document
 */
class ReadDocumentTest extends TestCase {

  use TestWithFilesTrait;

  public function testCanReadYaml() {
    $path = $this->getTestFilePath('document.md');
    file_put_contents($path, '# My Instructions');
    $document = (new ReadDocument())($path);
    $this->assertStringStartsWith('# My Instructions', $document->getContent());
  }

  public function testNoFileThrows() {
    $this->expectException(RuntimeException::class);
    $path = $this->getTestFilePath('document.md');
    (new ReadDocument())($path);
  }
}
