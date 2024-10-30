<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\IO\GetResultFilename;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\IO\GetResultFilename
 */
class GetResultFilenameTest extends TestCase {

  public static function dataFortestInvokeProvider(): array {
    $tests = [];
    $tests[] = [
      '/path/to/source.md',
      'source_2020-01-01_000000.md',
    ];
    $tests[] = [
      '/path/to/source.txt',
      'source_2020-01-01_000000.txt',
    ];
    $tests[] = [
      'source.md',
      'source_2020-01-01_000000.md',
    ];

    return $tests;
  }

  /**
   * @dataProvider dataFortestInvokeProvider
   */
  public function testInvokeWithDate(string $document_path, string $expected_filename) {
    $date = date_create('2020-01-01');
    $getter = new GetResultFilename($date);
    $result = $getter($document_path);
    $this->assertSame($expected_filename, $result);
  }

  public function testInvokeWithoutDate() {
    $getter = new GetResultFilename();
    $result = $getter('.cache/bar/document.md');
    $this->assertSame('document.md', $result);
  }
}
