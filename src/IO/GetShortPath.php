<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\IO;

/**
 * @code
 * // Print a shortened, nice-to-read path when possible.
 * echo (new GetShortPath(getcwd())($long_path)
 * @endcode
 */
class GetShortPath {

  private string $basepath;

  public function __construct(string $basepath) {
    $this->basepath = $basepath;
  }

  public function __invoke(string $path): string {
    if (!str_starts_with($path, $this->basepath)) {
      return $path;
    }

    $short_path = substr($path, strlen($this->basepath) + 1);
    if ($this->basepath === getcwd()) {
      $short_path = "./$short_path";
    }

    return $short_path;
  }
}
