<?php

namespace IO;

use AKlump\Directio\IO\GetShortPath;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\IO\GetShortPath
 */
class GetShortPathTest extends TestCase {

  use TestWithFilesTrait;

  public function testInvoke() {
    $file = $this->getTestFileFilepath('.cache/foo/bar.txt');
    $directory = dirname($file);;
    $this->assertSame('bar.txt', (new GetShortPath($directory))($file));
    $this->assertSame($file, (new GetShortPath('/lorem/'))($file));
    chdir($directory);
    $this->assertSame('./bar.txt', (new GetShortPath($directory))($file));
  }
}
