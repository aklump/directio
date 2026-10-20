<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Model;

use AKlump\Directio\Model\Task;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Model\Task
 */
class TaskTest extends TestCase {

  public function testGetters() {
    $task = new Task(2, 'Foo', ['id' => 'a'], 10, 17, 4);
    $this->assertSame(2, $task->getLevel());
    $this->assertSame('Foo', $task->getTitle());
    $this->assertSame(['id' => 'a'], $task->getAttributes());
    $this->assertSame(10, $task->getStart());
    $this->assertSame(17, $task->getBlockOffset());
    $this->assertSame(4, $task->getBlockLength());
  }

  public function testEndDefaultsToStartUntilSet() {
    $task = new Task(2, 'Foo', ['id' => 'a'], 10, 17, 4);
    $this->assertSame(10, $task->getEnd());

    $task->setEnd(42);
    $this->assertSame(42, $task->getEnd());
  }

}
