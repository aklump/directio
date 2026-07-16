<?php

namespace AKlump\Directio\Tests\TestingTraits;

/**
 * Provides methods for handling files in tests.
 *
 * This trait distinguishes between two types of files:
 * - Fixtures: Read-only files that exist in the repository and are used as
 *   static input or reference for tests.
 * - Test Files: Temporary files or directories created during a test's
 *   execution and typically deleted during tearDown.
 */
trait TestWithFilesTrait {

  /**
   * Delete all the files in the test files directory.
   *
   * This can be added to the tearDown method as appropriate.
   */
  public function deleteAllTestFiles(): void {
    $basepath = $this->getTestFilesDirectory();
    if (is_dir($basepath)) {
      $all_files = array_diff(scandir($basepath), ['.', '..']);
      foreach ($all_files as $file) {
        $this->deleteTestFile("$basepath/$file");
      }
    }
  }

  /**
   * Delete a file or directory in the test files directory.
   *
   * @param string $test_file
   *   An absolute or relative path to a file in the test files directory to be deleted.
   *   Absolute files must be in the test directory.
   *
   * @throws \InvalidArgumentException If the path is empty or outside the sandbox.
   *
   * @see ::getTestFilesDirectory
   */
  public function deleteTestFile(string $test_file): void {
    if (empty($test_file)) {
      throw new \InvalidArgumentException('$test_file cannot be empty');
    }
    if (substr($test_file, 0, 1) !== DIRECTORY_SEPARATOR) {
      $test_file = $this->getTestFilePath($test_file);
    }
    if (!$this->isTestFile($test_file)) {
      throw new \InvalidArgumentException(sprintf('You cannot delete paths outside of the sandbox: %s', $test_file));
    }
    if (file_exists($test_file)) {
      chmod($test_file, 0777);
      if (is_dir($test_file)) {
        $files = array_diff(scandir($test_file), ['.', '..']);
        foreach ($files as $file) {
          $this->deleteTestFile("$test_file/$file");
        }
        rmdir($test_file);
      }
      else {
        unlink($test_file);
      }
    }
  }

  /**
   * Check if a path is within the test files sandbox.
   *
   * @param string $path
   *   The absolute path to check.
   *
   * @return bool
   *   TRUE if the path is in the sandbox.
   */
  private function isTestFile(string $path): bool {
    $test_dir = $this->getTestFilesDirectory();

    return strpos($path, $test_dir) === 0 || strpos($path, realpath($test_dir)) === 0;
  }

  /**
   * Get the directory for temporary test files.
   *
   * Test files are created and deleted during the test lifetime.
   *
   * @return string
   *   The path to the test files directory.
   */
  private function getTestFilesDirectory(): string {
    return $this->resolveDirectory(__DIR__ . '/../../files/tmp/');
  }

  /**
   * Get the directory for test fixtures.
   *
   * Fixtures are read-only and already exist in the repository.
   *
   * @return string
   *   The path to the test fixtures directory.
   */
  private function getTestFixturesDirectory(): string {
    return $this->resolveDirectory(__DIR__ . '/../../files/fixtures/');
  }

  /**
   * Resolve and ensure a directory exists and is writable.
   *
   * @param string $basepath
   *   The path to resolve.
   *
   * @return string
   *   The canonical path to the directory, ending with a directory separator.
   *
   * @throws \RuntimeException If the directory cannot be established.
   */
  private function resolveDirectory(string $basepath): string {
    if ($basepath && !file_exists($basepath)) {
      mkdir($basepath, 0755, TRUE);
    }
    elseif ($basepath) {
      chmod($basepath, 0755);
    }
    if (!$basepath || !is_writable($basepath)) {
      throw new \RuntimeException(sprintf('Failed to establish a sandbox base directory: %s', $basepath));
    }

    return rtrim(realpath($basepath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
  }

  /**
   * Get the absolute path to a fixture file.
   *
   * Fixtures are read-only files that must already exist in the fixtures
   * directory.
   *
   * @param string $relative
   *   The relative path to the fixture.
   *
   * @return string
   *   The absolute path to the fixture.
   *
   * @throws \InvalidArgumentException If the fixture does not exist.
   */
  public function getTestFixturePath(string $relative): string {
    $basedir = $this->getTestFixturesDirectory();
    if (empty($relative)) {
      throw new \InvalidArgumentException(sprintf('Filepath must not be empty'));
    }
    $path = $basedir . ltrim($relative, DIRECTORY_SEPARATOR);
    if (!file_exists($path)) {
      throw new \InvalidArgumentException(sprintf('Test fixture does not exist: %s', $path));
    }
    if (is_dir($path)) {
      $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    return $path;
  }

  /**
   * Get the absolute path to a test file, optionally creating it.
   *
   * Test files are temporary files created and deleted during the test
   * lifetime.
   *
   * @param string $relative
   *   The relative path to the test file. If it ends in a slash, it's treated as a directory.
   * @param bool $create
   *   Whether to create the file or directory if it doesn't exist.
   *
   * @return string
   *   The absolute path to the test file or directory.
   *
   * @throws \InvalidArgumentException If the relative path is empty.
   */
  public function getTestFilePath(string $relative, bool $create = FALSE): string {
    $basedir = $this->getTestFilesDirectory();
    if (empty($relative)) {
      throw new \InvalidArgumentException(sprintf('Filepath must not be empty'));
    }
    $path = $basedir . ltrim($relative, DIRECTORY_SEPARATOR);
    $is_dir = substr($path, -1) === DIRECTORY_SEPARATOR;

    if ($create) {
      if ($is_dir) {
        if (!file_exists($path)) {
          mkdir($path, 0755, TRUE);
        }
      }
      else {
        $parent = dirname($path);
        if (!file_exists($parent)) {
          mkdir($parent, 0755, TRUE);
        }
        if (!file_exists($path)) {
          touch($path);
        }
      }
    }

    if (file_exists($path)) {
      $path = realpath($path);
    }
    if (is_dir($path)) {
      $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    return $path;
  }

  /**
   * Get the canonical path, resolving symlinks for directories only.
   *
   * @param string $path
   *   The path to resolve.
   *
   * @return string
   *   The canonical path.
   *
   * @throws \InvalidArgumentException If the path does not exist.
   */
  public function getCanonicalPath(string $path): string {
    if (!file_exists($path)) {
      throw new \InvalidArgumentException(sprintf('$path does not exist: %s', $path));
    }

    $suffix = '';
    if (is_file($path)) {
      $suffix = DIRECTORY_SEPARATOR . basename($path);
      $path = dirname($path);
    }

    $canonical_dir = exec(sprintf('cd %s && pwd -L', escapeshellarg($path)));

    return $canonical_dir . $suffix;
  }

}
