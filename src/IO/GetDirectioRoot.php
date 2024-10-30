<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\IO;

use AKlump\Directio\Config\Names;

/**
 * Starting with the current working directory, locate the initialized basedir.
 */
class GetDirectioRoot {

  public function __invoke(): string {
    $directory = getcwd();
    while ('/' !== $directory) {
      if ($this->isInitialized($directory)) {
        return $directory;
      }
      $directory = dirname($directory);
    }

    return '';
  }

  private function isInitialized(string $directory): bool {
    $directio_dir = $directory . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;

    return file_exists($directio_dir) && is_dir($directio_dir);
  }

}
