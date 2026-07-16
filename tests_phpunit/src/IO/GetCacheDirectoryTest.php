<?php

namespace AKlump\Directio\Tests\IO;

use AKlump\Directio\IO\GetCacheDirectory;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\IO\GetCacheDirectory
 */
class GetCacheDirectoryTest extends TestCase {

  use TestWithFilesTrait;

  protected function setUp(): void {
    $this->deleteAllTestFiles();
  }

  protected function tearDown(): void {
    $this->deleteAllTestFiles();
  }

  public function testInvokeCreatesDirectoryAndReturnsPath() {
    $directioDir = $this->getTestFilePath('.directio/', TRUE);
    $getCacheDirectory = new GetCacheDirectory($directioDir);
    $cacheDir = $getCacheDirectory();

    $this->assertEquals($directioDir . DIRECTORY_SEPARATOR . '.cache', $cacheDir);
    $this->assertDirectoryExists($cacheDir);
  }

  public function testInvokeWithCustomMode() {
    $directioDir = $this->getTestFilePath('.directio_custom/', TRUE);
    $getCacheDirectory = new GetCacheDirectory($directioDir, 0700);
    $cacheDir = $getCacheDirectory();

    $this->assertDirectoryExists($cacheDir);
    $this->assertEquals('0700', substr(sprintf('%o', fileperms($cacheDir)), -4));
  }

  public function testInvokeThrowsExceptionOnFailure() {
    $directioDir = $this->getTestFilePath('readonly_dir/', TRUE);
    chmod($directioDir, 0555);

    $this->expectException(\RuntimeException::class);
    try {
      $getCacheDirectory = new GetCacheDirectory($directioDir);
      $getCacheDirectory();
    } finally {
      chmod($directioDir, 0755);
    }
  }

  public function testInvokeThrowsIfCachePathIsAFile() {
    $directioDir = $this->getTestFilePath('cache_is_file/', TRUE);
    touch($directioDir . DIRECTORY_SEPARATOR . '.cache');

    $this->expectException(\RuntimeException::class);
    $getCacheDirectory = new GetCacheDirectory($directioDir);
    $getCacheDirectory();
  }

  public function testInvokeWithEmptyDirectioDirThrows() {
    $this->expectException(\InvalidArgumentException::class);
    $getCacheDirectory = new GetCacheDirectory('');
    $getCacheDirectory();
  }
}
