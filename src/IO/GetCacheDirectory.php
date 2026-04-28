<?php

namespace AKlump\Directio\IO;

class GetCacheDirectory {

  protected string $directioDir;

  /**
   * @var int|mixed
   */
  protected mixed $mode;

  public function __construct(string $directio_directory, $mode = 0755) {
    if (empty($directio_directory)) {
      throw new \InvalidArgumentException('$directio_directory cannot be empty');
    }
    $this->directioDir = $directio_directory;
    $this->mode = $mode;
  }

  public function __invoke(): string {
    $cache_directory = $this->directioDir . DIRECTORY_SEPARATOR . '.cache';
    if (!is_dir($cache_directory) && !@mkdir($cache_directory, $this->mode, TRUE)) {
      throw new \RuntimeException("Failed to create cache directory: $cache_directory");
    }

    return $cache_directory;
  }
}
