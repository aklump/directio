<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit\IO;

use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \AKlump\Directio\IO\ReadDocument
 * @uses   \AKlump\Directio\Model\Document
 */
class ReadDocumentTest extends TestCase {

  use TestWithFilesTrait;

  public function testCanReadYaml() {
    $path = $this->getTestFileFilepath('document.md');
    $document = (new ReadDocument())($path);
    $this->assertStringStartsWith('# My Instructions', $document->getContent());
  }

  public function testNoFileThrows() {
    $this->expectException(RuntimeException::class);
    $path = $this->getTestFileFilepath('.cache/document.md');
    (new ReadDocument())($path);
  }
}
