<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit\Model;

use AKlump\Directio\Model\TaskState;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Model\TaskState
 */
class TaskStateTest extends TestCase {

  public function testCompletedGetBeforeSet() {
    $task = new TaskState();
    $this->assertEmpty($task->getCompleted());
  }

  public function testRedoGetBeforeSet() {
    $task = new TaskState();
    $this->assertEmpty($task->getRedo());
  }

  public function testCompleted() {
    $task = new TaskState();
    $this->assertSame('2024-10-31', $task->setCompleted('2024-10-31')
      ->getCompleted());
  }

  public function testRedo() {
    $task = new TaskState();
    $this->assertSame('2024-11-15', $task->setRedo('2024-11-15')
      ->getRedo());
  }

  public function testId() {
    $task = new TaskState();
    $this->assertSame('lorem ipsum', $task->setId('lorem ipsum')->getId());
  }

  public function testEnv() {
    $task = new TaskState();
    $this->assertSame('foo-machine.local', $task->setEnv('foo-machine.local')
      ->getEnv());
  }

  public function testUser() {
    $task = new TaskState();
    $this->assertSame('aklump', $task->setUser('aklump')->getUser());
  }

}
