<?php

namespace AKlump\Directio\Tests\Fixture;

use AKlump\Directio\Fixture\AbstractFileSync;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @covers \AKlump\Directio\Fixture\AbstractFileSync
 * @uses \AKlump\Directio\FixtureFramework\AbstractFixture
 */
class AbstractFileSyncRenameTest extends TestCase {

  private string $tempDir;

  protected function setUp(): void {
    $this->tempDir = sys_get_temp_dir() . '/directio_test_rename_' . uniqid();
    mkdir($this->tempDir);
  }

  protected function tearDown(): void {
    if (is_dir($this->tempDir)) {
      $this->removeDir($this->tempDir);
    }
  }

  private function removeDir(string $dir) {
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
      (is_dir("$dir/$file")) ? $this->removeDir("$dir/$file") : unlink("$dir/$file");
    }

    return rmdir($dir);
  }

  public function testLegacyFilesAreRenamedAndUpdated() {
    $legacyFile1 = $this->tempDir . '/legacy1.txt';
    $targetFile = $this->tempDir . '/target.txt';

    file_put_contents($legacyFile1, 'old content 1');

    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();
    $source = 'data://text/plain,new content';

    $fixture = new class($input, $output, $source, 'target.txt', ['legacy1.txt'], $this->tempDir) extends AbstractFileSync {
      private string $source;
      private string $target;
      private array $legacy;
      private string $searchDir;
      public function __construct($input, $output, $source, $target, $legacy, $searchDir) {
        parent::__construct($input, $output);
        $this->source = $source;
        $this->target = $target;
        $this->legacy = $legacy;
        $this->searchDir = $searchDir;
      }
      protected function getSource(): string { return $this->source; }
      protected function getTargetFilename(): string { return $this->target; }
      protected function getLegacyFilenames(): array { return $this->legacy; }
      protected function getSearchDirectories(): array { return [$this->searchDir]; }
    };

    $fixture();

    $this->assertFileExists($targetFile);
    $this->assertFileDoesNotExist($legacyFile1);
    $this->assertEquals('new content', file_get_contents($targetFile));
  }

  public function testLegacyFilesInDifferentDirsAreRenamedAndUpdated() {
    $dir1 = $this->tempDir . '/dir1';
    $dir2 = $this->tempDir . '/dir2';
    mkdir($dir1);
    mkdir($dir2);

    $legacyFile1 = $dir1 . '/legacy1.txt';
    $legacyFile2 = $dir2 . '/legacy2.txt';

    file_put_contents($legacyFile1, 'old content 1');
    file_put_contents($legacyFile2, 'old content 2');

    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();
    $source = 'data://text/plain,new content';

    $fixture = new class($input, $output, $source, 'target.txt', ['legacy1.txt', 'legacy2.txt'], $this->tempDir) extends AbstractFileSync {
      private string $source;
      private string $target;
      private array $legacy;
      private string $searchDir;
      public function __construct($input, $output, $source, $target, $legacy, $searchDir) {
        parent::__construct($input, $output);
        $this->source = $source;
        $this->target = $target;
        $this->legacy = $legacy;
        $this->searchDir = $searchDir;
      }
      protected function getSource(): string { return $this->source; }
      protected function getTargetFilename(): string { return $this->target; }
      protected function getLegacyFilenames(): array { return $this->legacy; }
      protected function getSearchDirectories(): array { return [$this->searchDir]; }
    };

    $fixture();

    $this->assertFileExists($dir1 . '/target.txt');
    $this->assertFileExists($dir2 . '/target.txt');
    $this->assertFileDoesNotExist($legacyFile1);
    $this->assertFileDoesNotExist($legacyFile2);

    $this->assertEquals('new content', file_get_contents($dir1 . '/target.txt'));
    $this->assertEquals('new content', file_get_contents($dir2 . '/target.txt'));
  }
}
