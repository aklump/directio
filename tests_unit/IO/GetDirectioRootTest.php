<?php

namespace AKlump\Directio\Tests\Unit\IO;

use AKlump\Directio\IO\GetDirectioRoot;
use AKlump\Directio\IO\InitializeDirectory;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\IO\GetDirectioRoot
 * @uses   \AKlump\Directio\IO\InitializeDirectory
 */
class GetDirectioRootTest extends TestCase {

  use TestWithFilesTrait;

  public function testInvokeReturnsEmptyStringWhenNoParentFound() {
    $start_dir = sys_get_temp_dir();
    chdir($start_dir);
    $this->assertSame('', (new GetDirectioRoot())());
  }

  public function testInvokeWorksFromRootOrChildrenDirectories() {
    $start_dir = $this->getTestFileFilepath('.cache/foo/bar/baz/', TRUE);
    $root_dir = $this->getTestFileFilepath('.cache/foo/', TRUE);
    (new InitializeDirectory())($root_dir);

    // Grandchild
    chdir($start_dir);
    $this->assertSame($root_dir, (new GetDirectioRoot())());

    // Child
    chdir(dirname($start_dir));
    $this->assertSame($root_dir, (new GetDirectioRoot())());

    // Root
    chdir($root_dir);
    $this->assertSame($root_dir, (new GetDirectioRoot())());
    $this->deleteTestFile('.cache/');
  }

}
