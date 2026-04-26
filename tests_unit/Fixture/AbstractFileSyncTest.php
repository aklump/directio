<?php

namespace AKlump\Directio\Tests\Unit\Fixture;

use AKlump\Directio\Fixture\AbstractFileSync;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @covers \AKlump\Directio\Fixture\AbstractFileSync
 * @uses \AKlump\Directio\FixtureFramework\AbstractFixture
 * @uses \AKlump\Directio\IO\GetShortPath
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

  public function testNoUpdateRequired() {
    $targetFile = $this->tempDir . '/target.txt';
    file_put_contents($targetFile, 'same content');

    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();
    $source = 'data://text/plain,same content';

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
      protected function getSource(): string { return $this->source; }
      protected function getTargetFilename(): string { return $this->target; }
      protected function getSearchDirectories(): array { return [$this->searchDir]; }
    };

    $fixture();

    $output_content = $output->fetch();
    $this->assertStringContainsString('Up to date', $output_content);
    $this->assertStringContainsString('All files are already up to date', $output_content);
  }

  public function testNoInstancesFound() {
    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();
    $source = 'data://text/plain,content';

    $fixture = new class($input, $output, $source, 'nonexistent.txt', $this->tempDir) extends AbstractFileSync {
      private string $source;
      private string $target;
      private string $searchDir;
      public function __construct($input, $output, $source, $target, $searchDir) {
        parent::__construct($input, $output);
        $this->source = $source;
        $this->target = $target;
        $this->searchDir = $searchDir;
      }
      protected function getSource(): string { return $this->source; }
      protected function getTargetFilename(): string { return $this->target; }
      protected function getSearchDirectories(): array { return [$this->searchDir]; }
    };

    $fixture();

    $output_content = $output->fetch();
    $this->assertStringContainsString('No instances of nonexistent.txt were found', $output_content);
  }

  public function testFetchFailureThrowsException() {
    $this->expectException(\AKlump\FixtureFramework\Exception\FixtureException::class);
    $this->expectExceptionMessage('Unable to fetch the latest content');

    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();
    $source = '/non/existent/path/to/source';

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
      protected function getSource(): string { return $this->source; }
      protected function getTargetFilename(): string { return $this->target; }
      protected function getSearchDirectories(): array { return [$this->searchDir]; }
    };

    $fixture();
  }

  public function testIgnoredDirectoriesAreSkipped() {
    $ignoredDir = $this->tempDir . '/vendor';
    mkdir($ignoredDir);
    $targetFile = $ignoredDir . '/target.txt';
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
      protected function getSource(): string { return $this->source; }
      protected function getTargetFilename(): string { return $this->target; }
      protected function getSearchDirectories(): array { return [$this->searchDir]; }
    };

    $fixture();

    $output_content = $output->fetch();
    $this->assertStringContainsString('No instances of target.txt were found', $output_content);
    $this->assertEquals('old content', file_get_contents($targetFile));
  }
}
