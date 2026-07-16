<?php

namespace AKlump\Directio\Tests\IO;

use AKlump\Directio\IO\GetShortPath;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\IO\GetShortPath
 */
class GetShortPathTest extends TestCase {

  use TestWithFilesTrait;

  public function testInvoke() {
    $file = $this->getTestFilePath('foo/bar.txt', TRUE);
    $directory = dirname($file);
    $this->assertSame('bar.txt', (new GetShortPath($directory))($file));
    $this->assertSame($file, (new GetShortPath('/lorem/'))($file));
    chdir($directory);
    $this->assertSame('./bar.txt', (new GetShortPath($directory))($file));
  }
}
