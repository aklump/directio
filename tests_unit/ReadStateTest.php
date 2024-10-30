<?php

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\IO\ReadState;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \AKlump\Directio\IO\ReadState
 * @uses \AKlump\Directio\Model\Task
 */
class ReadStateTest extends TestCase {

  use TestWithFilesTrait;

  public function testCanReadYaml() {
    $path = $this->getTestFileFilepath('/state.yml');
    $state = (new ReadState())($path);
    $this->assertSame('install_runs_update', $state[0]->getId());
  }

  public function testNoFileThrows() {
    $this->expectException(RuntimeException::class);
    $path = $this->getTestFileFilepath('/foo/state.md');
    (new ReadState())($path);
  }
}
