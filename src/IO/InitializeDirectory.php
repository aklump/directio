<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\IO;

use AKlump\Directio\Config\Names;
use RuntimeException;

final class InitializeDirectory {

  private string $directory;

  public function __invoke(string $directory) {
    $this->directory = rtrim($directory, '/');
    $this->initDirectory();
    $this->initFixtures();
    $this->initStateFile();
    $this->initFixtureDirectory();
    $this->initLogsDirectory();
    $this->initGitIgnore();
  }

  private function initFixtureDirectory() {
    $target = $this->directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Fixture';
    if (!file_exists($target)) {
      if (FALSE === @mkdir($target, 0755, TRUE)) {
        throw new RuntimeException(sprintf('Failed to create %s', $target));
      }
    }
  }

  private function initStateFile() {
    $target = $this->directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE;
    if (!file_exists($target)) {
      if (FALSE === @file_put_contents($target, '')) {
        throw new RuntimeException(sprintf('Failed to create %s', Names::FILENAME_STATE));
      }
    }
  }

  private function initDirectory() {
    $target = $this->directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;
    if (!file_exists($target)) {
      if (FALSE === @mkdir($target, 0755, TRUE)) {
        throw new RuntimeException(sprintf('Failed to create %s', $target));
      }
    }
  }

  private function initLogsDirectory() {
    $target = $this->directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;
    (new GetLogsDirectory($target))();
  }

  private function initFixtures() {
    // Ensure composer.json exists with the default autoloading.
    $target = $this->directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT . DIRECTORY_SEPARATOR . 'composer.json';
    $namespace = 'AKlump\\Directio\\Fixture\\';
    if (!file_exists($target)) {
      touch($target);
    }
    $content = file_get_contents($target);
    $data = json_decode($content, TRUE) ?? [];
    $data['autoload']['psr-4'][$namespace] ??= [];
    $autoload_item = &$data['autoload']['psr-4'][$namespace];
    $default_path = './src/Fixture/';
    if (!in_array($default_path, $autoload_item)) {
      $autoload_item[] = $default_path;
    }
    unset($autoload_item);
    if (FALSE === @file_put_contents($target, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL)) {
      throw new RuntimeException(sprintf('Failed to create %s', $target));
    }

    // Ensure the default fixture directory exists.
    $target = $this->directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT . DIRECTORY_SEPARATOR . 'src/Fixture';
    if (!file_exists($target)) {
      if (FALSE === @mkdir($target, 0755, TRUE)) {
        throw new RuntimeException(sprintf('Failed to create %s', $target));
      }
    }
  }

  private function initGitIgnore() {
    $target = $this->directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT . DIRECTORY_SEPARATOR . '.gitignore';
    if (!file_exists($target)) {
      $gitignore = <<<EOD
      imported/
      logs/
      EOD;
      if (FALSE === @file_put_contents($target, rtrim($gitignore) . PHP_EOL)) {
        throw new RuntimeException(sprintf('Failed to create %s', $target));
      }
    }
  }

}
