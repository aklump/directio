<?php

namespace AKlump\Directio\Tests\Unit\Fixture;

use AKlump\Directio\Fixture\AbstractFileSync;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @covers \AKlump\Directio\Fixture\AbstractFileSync
 */
class AbstractFileSyncTest extends TestCase {

  private string $tempDir;

  protected function setUp(): void {
    $this->tempDir = sys_get_temp_dir() . '/directio_test_' . uniqid();
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

  public function testLocalFileSync() {
    $sourceFile = $this->tempDir . '/source.txt';
    $targetFile = $this->tempDir . '/target.txt';

    file_put_contents($sourceFile, 'new content');
    file_put_contents($targetFile, 'old content');

    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();

    $fixture = new class($input, $output, $sourceFile, 'target.txt', $this->tempDir) extends AbstractFileSync {

      private string $source;

      private string $target;

      private string $searchDir;

      public function __construct($input, $output, $source, $target, $searchDir) {
        parent::__construct($input, $output);
        $this->source = $source;
        $this->target = $target;
        $this->searchDir = $searchDir;
      }

      protected function getSource(): string {
        return $this->source;
      }

      protected function getTargetFilename(): string {
        return $this->target;
      }

      protected function getSearchDirectories(): array {
        return [$this->searchDir];
      }

    };

    $fixture();

    $this->assertEquals('new content', file_get_contents($targetFile));
    $output_content = $output->fetch();
    $this->assertStringContainsString('Updated', $output_content);
    $this->assertStringContainsString('Backup:', $output_content);
    $this->assertNotEmpty(glob($targetFile . '.*'));
  }

  public function testRemoteFileSyncSimulatedWithDataUri() {
    $targetFile = $this->tempDir . '/target.txt';
    file_put_contents($targetFile, 'old content');

    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();

    $source = 'data://text/plain,new content';

    $fixture = new class($input, $output, $source, 'target.txt', $this->tempDir) extends AbstractFileSync {

      private string $source;

      private string $target;

      private string $searchDir;

      public function __construct($input, $output, $source, $target, $searchDir) {
        parent::__construct($input, $output);
        $this->source = $source;
        $this->target = $target;
        $this->searchDir = $searchDir;
      }

      protected function getSource(): string {
        return $this->source;
      }

      protected function getTargetFilename(): string {
        return $this->target;
      }

      protected function getSearchDirectories(): array {
        return [$this->searchDir];
      }

    };

    $fixture();

    $this->assertEquals('new content', file_get_contents($targetFile));
    $output_content = $output->fetch();
    $this->assertStringContainsString('Updated', $output_content);
    $this->assertStringContainsString('Backup:', $output_content);
    $this->assertNotEmpty(glob($targetFile . '.*'));
  }
}
