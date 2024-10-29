<?php

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\Task;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Task
 */
class TaskInterfaceTest extends TestCase {

  public function testId() {
    $task = new Task();
    $this->assertSame('lorem ipsum', $task->setId('lorem ipsum')->getId());
  }

}
