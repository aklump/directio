<?php

namespace AKlump\Directio\IO;

use AKlump\Directio\Config\Names;
use RuntimeException;

class InitializeDirectory {

  public function __invoke(string $directory) {
    $directory = rtrim($directory, '/');
    $target = $directory . '/' . Names::FILENAME_INIT;
    if (!file_exists($target)) {
      if (FALSE === @mkdir($target, 0755, TRUE)) {
        throw new RuntimeException(sprintf('Failed to create %s', $target));
      }
    }

    $target = $directory . '/' . Names::FILENAME_INIT . '/' . Names::FILENAME_STATE . '.yml';
    if (!file_exists($target)) {
      if (FALSE === @file_put_contents($target, '')) {
        throw new RuntimeException(sprintf('Failed to create %s', $target));
      }
    }
  }
}
