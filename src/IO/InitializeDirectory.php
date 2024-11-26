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
    $this->initStateFile();
    $this->initGitIgnore();
  }

  private function initStateFile() {
    $target = $this->directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE;
    if (!file_exists($target)) {
      if (FALSE === @file_put_contents($target, '')) {
        throw new RuntimeException(sprintf('Failed to create %s', $target));
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


  private function initGitIgnore() {
    $target = $this->directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT . DIRECTORY_SEPARATOR . '.gitignore';
    if (!file_exists($target)) {
      $gitignore = <<<EOD
      imported/
      EOD;
      if (FALSE === @file_put_contents($target, rtrim($gitignore) . PHP_EOL)) {
        throw new RuntimeException(sprintf('Failed to create %s', $target));
      }
    }
  }

}
