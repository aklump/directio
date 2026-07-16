<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\TextProcessor;

use AKlump\Directio\TextProcessor\ScanForCompletedTasks;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\TextProcessor\ScanForCompletedTasks
 * @uses   \AKlump\Directio\Lexer\TaskLexer
 * @uses   \AKlump\Directio\Config\SpecialAttributes
 * @uses   \AKlump\Directio\Lexer\AttributesLexer
 * @uses   \AKlump\Directio\TextProcessor\ParseAttributes
 * @uses   \AKlump\Directio\HTMLElementStyle
 */
class ScanForCompletedTasksTest extends TestCase {

  public static function dataFortestInvokeProvider(): array {
    $tests = [];
    $tests[] = [
      '<feature>
---

## Server Backup Files

> Use cron to create backups of key server files (crontab, .bash_profile, etc). Create a files group in LDP for these backups, that can be pulled in the repo, redacted if necessary, and committed to source control. This facilitates easier server rebuilds.

<directio x id="review_ldp_files_server" redo="P1W">

- `ldp pull -f --group=server`
- Ensure the dates are current in the file headers in install/server/*
- Commit any changes.
- Add any missing files.

</directio>
',
      [
        [
          'x' => TRUE,
          'id' => 'review_ldp_files_server',
          'redo' => 'P1W',
        ],
      ],
    ];

    $tests[] = ['', []];
    $tests[] = [
      '<directio x id="foo">',
      [
        [
          'x' => TRUE,
          'id' => 'foo',
        ],
      ],
    ];
    $tests[] = [
      'lorem <directio x id="foo"> ipsum </directio> dolar sit  <directio id="bar"> ipsum </directio>  dolar sit  <directio x id="baz" expires="P3M"> ipsum </directio> alpha bravo',
      [
        [
          'x' => TRUE,
          'id' => 'foo',
        ],
        [
          'x' => TRUE,
          'id' => 'baz',
          'expires' => 'P3M',
        ],
      ],
    ];

    return $tests;
  }

  /**
   * @dataProvider dataFortestInvokeProvider
   */
  public function testInvoke(string $content, array $expected) {
    $result = (new ScanForCompletedTasks())($content);
    $this->assertSame($expected, $result);
  }
}
