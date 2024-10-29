<?php

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\GetResultFilename;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\GetResultFilename
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
  public function testInvoke(string $document_path, string $expected_filename) {
    $date = date_create('2020-01-01');
    $getter = new GetResultFilename($date);
    $result = $getter($document_path);
    $this->assertSame($expected_filename, $result);
  }
}
