<?php

namespace AKlump\Directio\Tests\IO;

use AKlump\Directio\IO\WriteState;
use AKlump\Directio\Model\TaskState;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \AKlump\Directio\IO\WriteState
 * @uses \AKlump\Directio\IO\ReadState
 * @uses \AKlump\Directio\Model\TaskState
 */
class WriteStateTest extends TestCase {

  use TestWithFilesTrait;

  public function testCantWriteThrows() {
    $path = $this->getTestFilePath('no_write/state.sqlite', true);
    if (file_exists(dirname($path))) {
      $this->deleteTestFile('no_write/');
    }
    $task = new TaskState();
    $task->setId('foo');
    mkdir(dirname($path), 0555, true);
    $this->expectException(RuntimeException::class);
    (new WriteState())->writeOne($path, $task);
  }

  public function testWriteOne() {
    $path = $this->getTestFilePath('state.sqlite', true);
    $task = (new TaskState())->setId('foo')->setCompleted('today');
    (new WriteState())->writeOne($path, $task);
    $this->assertFileExists($path);

    $read = (new \AKlump\Directio\IO\ReadState())->readById($path, 'foo');
    $this->assertSame('foo', $read->getId());
    $this->assertSame('today', $read->getCompleted());

    // Test Update
    $task->setCompleted('tomorrow');
    (new WriteState())->writeOne($path, $task);
    $read = (new \AKlump\Directio\IO\ReadState())->readById($path, 'foo');
    $this->assertSame('tomorrow', $read->getCompleted());
  }

  public function testWriteMany() {
    $path = $this->getTestFilePath('state_many.sqlite', true);
    $task1 = (new TaskState())->setId('foo');
    $task2 = (new TaskState())->setId('bar');
    (new WriteState())->writeMany($path, [$task1, $task2]);

    $state = (new \AKlump\Directio\IO\ReadState())($path);
    $this->assertCount(2, $state);
  }

  public function testInvoke() {
    $path = $this->getTestFilePath('state_invoke.sqlite', true);
    $task1 = (new TaskState())->setId('foo');
    (new WriteState())($path, [$task1]);

    $state = (new \AKlump\Directio\IO\ReadState())($path);
    $this->assertCount(1, $state);
  }

  public function testWriteManyRollsBackOnFailure() {
    $path = $this->getTestFilePath('state_rollback.sqlite', true);
    $task1 = (new TaskState())->setId('foo');
    $task2 = $this->createMock(\AKlump\Directio\Model\TaskStateInterface::class);
    $task2->method('getId')->willThrowException(new \Exception('Forced failure'));

    $writer = new WriteState();
    try {
      $writer->writeMany($path, [$task1, $task2]);
    }
    catch (RuntimeException $e) {
      $this->assertStringContainsString('Forced failure', $e->getMessage());
    }

    $state = (new \AKlump\Directio\IO\ReadState())($path);
    // If it rolled back, task1 should NOT be there.
    $this->assertCount(0, $state);
  }

  protected function tearDown(): void {
    $this->deleteTestFile('.cache/');
  }

}
