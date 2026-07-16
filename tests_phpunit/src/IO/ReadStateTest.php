<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\IO;

use AKlump\Directio\IO\ReadState;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \AKlump\Directio\IO\ReadState
 * @uses \AKlump\Directio\IO\WriteState
 * @uses \AKlump\Directio\Model\TaskState
 */
class ReadStateTest extends TestCase {

  use TestWithFilesTrait;

  public function testCanReadEmptyFile() {
    $path = $this->getTestFilePath('state.sqlite', true);
    $state = (new ReadState())($path);
    $this->assertEmpty($state);
  }

  public function testCanReadSqlite() {
    $path = $this->getTestFilePath('state.sqlite', true);
    $task = (new \AKlump\Directio\Model\TaskState())->setId('foo');
    (new \AKlump\Directio\IO\WriteState())->writeOne($path, $task);

    $state = (new ReadState())($path);
    $this->assertCount(1, $state);
    $this->assertSame('foo', $state[0]->getId());
  }

  public function testReadById() {
    $path = $this->getTestFilePath('state.sqlite', true);
    $task = (new \AKlump\Directio\Model\TaskState())->setId('foo');
    (new \AKlump\Directio\IO\WriteState())->writeOne($path, $task);

    $read = (new ReadState())->readById($path, 'foo');
    $this->assertNotNull($read);
    $this->assertSame('foo', $read->getId());

    $this->assertNull((new ReadState())->readById($path, 'bar'));
  }

  public function testHas() {
    $path = $this->getTestFilePath('state.sqlite', true);
    $task = (new \AKlump\Directio\Model\TaskState())->setId('foo');
    (new \AKlump\Directio\IO\WriteState())->writeOne($path, $task);

    $this->assertTrue((new ReadState())->has($path, 'foo'));
    $this->assertFalse((new ReadState())->has($path, 'bar'));
  }

  public function testMigration() {
    $json_path = $this->getTestFilePath('migrate.json', true);
    $sqlite_path = $this->getTestFilePath('migrate.sqlite', true);
    unlink($sqlite_path); // Ensure it doesn't exist

    $data = '[{"id":"foo","completed":"2023-01-01T00:00:00+00:00","redo":"","env":"","user":""}]';
    file_put_contents($json_path, $data);

    $state = (new ReadState())($sqlite_path);
    $this->assertCount(1, $state);
    $this->assertSame('foo', $state[0]->getId());
    $this->assertFileExists($sqlite_path);
    $this->assertFileExists($json_path . '.bak');
  }

  public function testNoFileThrows() {
    $this->expectException(RuntimeException::class);
    $path = $this->getTestFilePath('state.md');
    (new ReadState())($path);
  }

  protected function tearDown(): void {
    $this->deleteAllTestFiles();
  }

}
