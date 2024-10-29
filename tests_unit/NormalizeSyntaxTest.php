<?php

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\NormalizeSyntax;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\NormalizeSyntax
 */
class NormalizeSyntaxTest extends TestCase {

  public static function dataFortestInvokeProvider(): array {
    $tests = [];
    $tests[] = [
      '',
      '',
    ];
    $tests[] = [
      '<!--directio expires="P5D"-->This content will expire on January 1, 2024.<!--/directio-->',
      '<!-- directio expires=P5D -->This content will expire on January 1, 2024.<!-- /directio -->',
    ];
    $tests[] = [
      '<!-- directio expires="P5D" -->This content will expire on January 1, 2024.<!-- /directio -->',
      '<!-- directio expires=P5D -->This content will expire on January 1, 2024.<!-- /directio -->',
    ];

    return $tests;
  }

  /**
   * @dataProvider dataFortestInvokeProvider
   */
  public function testInvoke(string $input, string $expected) {
    $this->assertSame($expected, (new NormalizeSyntax())->__invoke($input));
  }
}
