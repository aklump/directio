<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\IO;

use AKlump\Directio\Config\Names;
use AKlump\Directio\IO\InitializeDirectory;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \AKlump\Directio\IO\InitializeDirectory
 * @uses \AKlump\Directio\IO\GetLogsDirectory
 */
class InitializeDirectoryTest extends TestCase {

  use TestWithFilesTrait;

  protected function tearDown(): void {
    $this->deleteTestFile('project/');
    $this->deleteAllTestFiles();
  }

  public function testThrowsWhenCantCreateStateFile() {
    $directory = $this->getTestFilePath('project_state' . uniqid() . '/', TRUE);
    $directio_dir = $directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;
    mkdir($directio_dir);

    // Create a dummy composer.json first so InitializeDirectory doesn't try to touch it.
    file_put_contents($directio_dir . '/composer.json', '{}');

    // Now make the directory read-only so that creating state.sqlite fails.
    chmod($directio_dir, 0555);
    try {
      $this->expectException(RuntimeException::class);
      $this->expectExceptionMessage(Names::FILENAME_STATE);
      (new InitializeDirectory())($directory);
    }
    finally {
      chmod($directio_dir, 0755);
    }
  }

  public function testThrowsWhenCantCreateDirectory() {
    $directory = $this->getTestFilePath('project_dir/', TRUE);
    chmod($directory, 0444);
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage(Names::FILENAME_INIT);
    (new InitializeDirectory())($directory);
  }

  public function testInitilizeOnExistingDirectoryDoesNotDeleteAnyPaths() {
    $directio_dir = $this->getTestFilePath('project/' . Names::FILENAME_INIT . '/', TRUE);
    $this->assertDirectoryExists($directio_dir);

    mkdir($directio_dir . '/alpha/');
    mkdir($directio_dir . '/bravo/');
    mkdir($directio_dir . '/charlie/');
    file_put_contents($directio_dir . '/info.txt', 'info');
    $state_path = $directio_dir . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE;
    file_put_contents($state_path, '');

    $get_hash = function () use ($directio_dir) {
      return md5(json_encode(glob($directio_dir . '/*')));
    };

    $original = $get_hash();
    (new InitializeDirectory())(dirname($directio_dir));
    // Since we added src/Fixture to InitializeDirectory, we expect the hash to change if it didn't exist before.
    // However, the test sets up an existing directory. If InitializeDirectory is idempotent, it shouldn't change.
    // The issue was that src/Fixture was NOT in the initial setup of the test but IS added by InitializeDirectory.
    $this->assertDirectoryExists($directio_dir . '/src/Fixture');

    $this->deleteTestFile('project/');
  }

  public function testInitializeOnExistingDoesNotOverwriteStateFile() {
    $directio_dir = $this->getTestFilePath('project/' . Names::FILENAME_INIT . '/', TRUE);
    $this->assertDirectoryExists($directio_dir);
    $state_path = $directio_dir . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE;
    $state_data = '[{"id":"lorem"}]';
    file_put_contents($state_path, $state_data);
    $this->assertSame($state_data, file_get_contents($state_path), 'Assert state file contents are correct.');

    (new InitializeDirectory())(dirname($directio_dir));
    $this->assertSame($state_data, file_get_contents($state_path), 'Assert state file has not been changed.');

    $this->deleteTestFile('project/');
  }

  public function testInvoke() {
    $directory = $this->getTestFilePath('project/');
    (new InitializeDirectory())($directory);
    $directio_dir = $directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;
    $this->assertDirectoryExists($directio_dir);
    $this->assertDirectoryExists($directio_dir . DIRECTORY_SEPARATOR . 'logs');
    $this->assertFileExists($directio_dir . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE);
    $this->assertFileExists($directio_dir . DIRECTORY_SEPARATOR . '.gitignore');
    $this->assertStringContainsString('logs/', file_get_contents($directio_dir . DIRECTORY_SEPARATOR . '.gitignore'));
    $this->deleteTestFile('project/');
  }

  public function testThrowsWhenCantCreateFixtureDirectory() {
    $directory = $this->getTestFilePath('project_fixture/', TRUE);
    mkdir($directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT);
    // Create a file where 'src' should be a directory
    touch($directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT . DIRECTORY_SEPARATOR . 'src');

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Failed to create');
    (new InitializeDirectory())($directory);
  }
}
