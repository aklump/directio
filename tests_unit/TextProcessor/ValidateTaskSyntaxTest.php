<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit\TextProcessor;

use AKlump\Directio\Exception\BadWhitespaceException;
use AKlump\Directio\Exception\NestedTagsException;
use AKlump\Directio\Exception\NoClosingException;
use AKlump\Directio\Exception\NoIDException;
use AKlump\Directio\Exception\NoOpeningException;
use AKlump\Directio\TextProcessor\ValidateTaskSyntax;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\TextProcessor\ValidateTaskSyntax
 * @uses   \AKlump\Directio\Lexer\AttributesLexer
 * @uses   \AKlump\Directio\Lexer\TaskLexer
 * @uses   \AKlump\Directio\TextProcessor\ParseAttributes
 * @uses   \AKlump\Directio\Exception\BadWhitespaceException
 */
class ValidateTaskSyntaxTest extends TestCase {

  public static function dataFortestInvokeThrowsProvider(): array {
    $tests = [];

    $tests[] = [
      '<!-- directio [x] id=easy_perms_install -->

- Update to https://github.com/aklump/easy-perms<!-- /directio -->

<!-- directio [] id=easy_perms redo=P1D-->

- `j a && cd opt/aklump/easy-perms && lando composer require aklump/easy-perms:^0.0 && lando composer update`
- Update the permissions settings if necessary.

<!-- /directio -->',
      BadWhitespaceException::class,
      '<!-- directio [] id=easy_perms redo=P1D-->',
    ];

    $tests[] = [
      '<!-- directio [] id=foo --><!-- directio [] id=bar --><!-- /directio --><!-- /directio -->',
      NestedTagsException::class,
    ];
    $tests[] = [
      '<!-- directio -->foobar<!-- /directio -->',
      NoIDException::class,
    ];
    $tests[] = [
      '<!-- directio id=foo -->foobar',
      NoClosingException::class,
    ];
    $tests[] = [
      '<!-- directio lorem=ipsum -->foobar<!-- /directio -->',
      NoIDException::class,
    ];
    $tests[] = [
      '<!-- directio id=ipsum -->foobar<!-- /directio --><!-- directio lorem=ipsum -->',
      NoIDException::class,
    ];
    $tests[] = ['# Lorem Ipsum<!-- /directio -->', NoOpeningException::class];
    $tests[] = ['', NoOpeningException::class];

    return $tests;
  }

  /**
   * @dataProvider dataFortestInvokeThrowsProvider
   */
  public function testInvokeThrows(string $content, string $expected, string $message = '') {
    $this->expectException($expected);
    if ($message) {
      $this->expectExceptionMessage($message);
    }
    (new ValidateTaskSyntax())->__invoke($content);
  }
}
