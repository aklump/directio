<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\Model\TaskState;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Model\TaskState
 */
class TaskInterfaceTest extends TestCase {

  public function testId() {
    $task = new TaskState();
    $this->assertSame('lorem ipsum', $task->setId('lorem ipsum')->getId());
  }

}
