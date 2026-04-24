<?php

namespace AKlump\Directio\IO;

/**
 * @code
 * // Print a shortened, nice-to-read path when possible:
 * echo (new GetShortPath('/some/base/path')($long_path)
 *
 * // Or relative to the CWD:
 * echo (new GetShortPath()($long_path)
 * @endcode
 */
class GetShortPath {

  private string $basepath;

  public function __construct(string $basepath = NULL) {
    $this->basepath = $basepath ?? getcwd();
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
