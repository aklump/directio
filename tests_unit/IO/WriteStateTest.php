<?php

namespace IO;

use AKlump\Directio\IO\WriteState;
use AKlump\Directio\Model\TaskState;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \AKlump\Directio\IO\WriteState
 * @uses   \AKlump\Directio\Model\TaskState
 */
class WriteStateTest extends TestCase {

  use TestWithFilesTrait;

  public function testCantWriteThrows() {
    $path = $this->getTestFileFilepath('.cache/state.json');
    $task = new TaskState();
    $task->setId('foo');
    chmod(dirname($path), 0444);
    $this->expectException(RuntimeException::class);
    (new WriteState())->__invoke($path, [$task]);
  }

  public function testInvoke() {
    $path = $this->getTestFileFilepath('.cache/state.json');
    $task = new TaskState();
    $task->setId('foo');
    (new WriteState())->__invoke($path, [$task]);
    $this->assertFileExists($path);
    $this->assertJsonStringEqualsJsonFile($path, '[{"id":"foo","completed":"","redo":"","env":"","user":""}]');
  }

  protected function tearDown(): void {
    $this->deleteTestFile('.cache/');
  }

}
