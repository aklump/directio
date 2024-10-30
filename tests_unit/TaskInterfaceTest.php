<?php

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\Model\Task;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Model\Task
 */
class TaskInterfaceTest extends TestCase {

  public function testId() {
    $task = new Task();
    $this->assertSame('lorem ipsum', $task->setId('lorem ipsum')->getId());
  }

}
