<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\IO;

use AKlump\Directio\Config\Names;
use RuntimeException;

class InitializeDirectory {

  public function __invoke(string $directory) {
    $directory = rtrim($directory, '/');
    $target = $directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;
    if (!file_exists($target)) {
      if (FALSE === @mkdir($target, 0755, TRUE)) {
        throw new RuntimeException(sprintf('Failed to create %s', $target));
      }
    }

    $target = $directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE;
    if (!file_exists($target)) {
      if (FALSE === @file_put_contents($target, '')) {
        throw new RuntimeException(sprintf('Failed to create %s', $target));
      }
    }
  }
}
