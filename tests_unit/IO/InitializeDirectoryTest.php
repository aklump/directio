<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit\IO;

use AKlump\Directio\Config\Names;
use AKlump\Directio\IO\InitializeDirectory;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * @covers \AKlump\Directio\IO\InitializeDirectory
 */
class InitializeDirectoryTest extends TestCase {

  use TestWithFilesTrait;

  public function testThrowsWhenCantCreateStateFile() {
    $directory = $this->getTestFileFilepath('.cache/project/', TRUE);
    mkdir($directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT);
    chmod($directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT, 0444);
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage(Names::FILENAME_STATE);
    try {
      (new InitializeDirectory())($directory);
    }
    catch (RuntimeException $exception) {
      $this->deleteTestFile('.cache/project/');
      throw $exception;
    }
  }

  public function testThrowsWhenCantCreateDirectory() {
    $directory = $this->getTestFileFilepath('.cache/project/', TRUE);
    chmod($directory, 0444);
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage(Names::FILENAME_INIT);
    try {
      (new InitializeDirectory())($directory);
    }
    catch (RuntimeException $exception) {
      $this->deleteTestFile('.cache/project/');
      throw $exception;
    }
  }

  public function testInitilizeOnExistingDirectoryDoesNotDeleteAnyPaths() {
    $directio_dir = $this->getTestFileFilepath('.cache/project/' . Names::FILENAME_INIT . '/', TRUE);
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
    $this->assertSame($original, $get_hash());

    $this->deleteTestFile('.cache/project/');
  }

  public function testInitializeOnExistingDoesNotOverwriteStateFile() {
    $directio_dir = $this->getTestFileFilepath('.cache/project/' . Names::FILENAME_INIT . '/', TRUE);
    $this->assertDirectoryExists($directio_dir);
    $state_path = $directio_dir . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE;
    $state_data = [];
    $state_data[] = ['id' => 'lorem'];
    file_put_contents($state_path, json_encode($state_data));
    $this->assertSame('[{"id":"lorem"}]', file_get_contents($state_path), 'Assert state file contents are correct.');

    (new InitializeDirectory())(dirname($directio_dir));
    $this->assertSame('[{"id":"lorem"}]', file_get_contents($state_path), 'Assert state file has not been changed.');

    $this->deleteTestFile('.cache/project/');
  }

  public function testInvoke() {
    $directory = $this->getTestFileFilepath('.cache/project/');
    (new InitializeDirectory())($directory);
    $directio_dir = $directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;
    $this->assertDirectoryExists($directio_dir);
    $this->assertFileExists($directio_dir . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE);
    $this->deleteTestFile('.cache/project/');

  }
}
