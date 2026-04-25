<?php

namespace AKlump\Directio\IO;

class GetLogsDirectory {

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
    $logs_directory = $this->directioDir . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logs_directory) && !@mkdir($logs_directory, $this->mode, TRUE)) {
      throw new \RuntimeException("Failed to create logs directory: $logs_directory");
    }

    return $logs_directory;
  }
}
