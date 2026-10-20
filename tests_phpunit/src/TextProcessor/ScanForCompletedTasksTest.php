<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\TextProcessor;

use AKlump\Directio\TextProcessor\ScanForCompletedTasks;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\TextProcessor\ScanForCompletedTasks
 * @uses   \AKlump\Directio\Config\SpecialAttributes
 * @uses   \AKlump\Directio\Model\Task
 * @uses   \AKlump\Directio\TextProcessor\ParseAttributes
 * @uses   \AKlump\Directio\TextProcessor\ParseTasks
 * @uses   \AKlump\Directio\Model\Heading
 * @uses   \AKlump\Directio\TextProcessor\ScanHeadings
 * @uses   \AKlump\Directio\TextProcessor\ScanLines
 */
class ScanForCompletedTasksTest extends TestCase {

  public static function dataFortestInvokeProvider(): array {
    $tests = [];
    $tests[] = [
      '<feature>
---

## Server Backup Files

> Use cron to create backups of key server files (crontab, .bash_profile, etc). Create a files group in LDP for these backups, that can be pulled in the repo, redacted if necessary, and committed to source control. This facilitates easier server rebuilds.

### Review LDP Files {x #review_ldp_files_server redo=P1W}

- `ldp pull -f --group=server`
- Ensure the dates are current in the file headers in install/server/*
- Commit any changes.
- Add any missing files.
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
      '## Foo {x #foo}',
      [
        [
          'x' => TRUE,
          'id' => 'foo',
        ],
      ],
    ];
    $tests[] = [
      "lorem\n\n## Foo {x #foo}\n\ndolar sit\n\n## Bar {#bar}\n\ndolar sit\n\n## Baz {x #baz expires=P3M}\n\nalpha bravo\n",
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
