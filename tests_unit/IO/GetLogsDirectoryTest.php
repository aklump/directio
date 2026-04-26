<?php

namespace AKlump\Directio\Tests\Unit\IO;

use AKlump\Directio\IO\GetLogsDirectory;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\IO\GetLogsDirectory
 */
class GetLogsDirectoryTest extends TestCase {

  use TestWithFilesTrait;

  protected function setUp(): void {
    $this->deleteAllTestFiles();
  }

  protected function tearDown(): void {
    $this->deleteAllTestFiles();
  }

  public function testInvokeCreatesDirectoryAndReturnsPath() {
    $directioDir = $this->getTestFileFilepath('.directio/', TRUE);
    $getLogsDirectory = new GetLogsDirectory($directioDir);
    $logsDir = $getLogsDirectory();

    $this->assertEquals($directioDir . DIRECTORY_SEPARATOR . 'logs', $logsDir);
    $this->assertDirectoryExists($logsDir);
  }

  public function testInvokeWithCustomMode() {
    $directioDir = $this->getTestFileFilepath('.directio_custom/', TRUE);
    // Use a mode that is easy to check, e.g., 0700
    $getLogsDirectory = new GetLogsDirectory($directioDir, 0700);
    $logsDir = $getLogsDirectory();

    $this->assertDirectoryExists($logsDir);
    $this->assertEquals('0700', substr(sprintf('%o', fileperms($logsDir)), -4));
  }

  public function testInvokeThrowsExceptionOnFailure() {
    $directioDir = $this->getTestFileFilepath('readonly_dir/', TRUE);
    chmod($directioDir, 0555);

    $this->expectException(\RuntimeException::class);
    try {
      $getLogsDirectory = new GetLogsDirectory($directioDir);
      $getLogsDirectory();
    } finally {
      chmod($directioDir, 0755);
    }
  }

  public function testInvokeThrowsIfLogsPathIsAFile() {
    $directioDir = $this->getTestFileFilepath('logs_is_file/', TRUE);
    touch($directioDir . DIRECTORY_SEPARATOR . 'logs');

    $this->expectException(\RuntimeException::class);
    $getLogsDirectory = new GetLogsDirectory($directioDir);
    $getLogsDirectory();
  }

  public function testInvokeWithEmptyDirectioDirThrows() {
    $this->expectException(\InvalidArgumentException::class);
    $getLogsDirectory = new GetLogsDirectory('');
    $getLogsDirectory();
  }
}
