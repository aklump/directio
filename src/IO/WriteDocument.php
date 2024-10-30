<?php

namespace AKlump\Directio\IO;

use RuntimeException;

class WriteDocument {

  /**
   * @param string $path
   * @param \AKlump\Directio\Model\DocumentInterface $document
   *
   * @return void
   *
   */
  public function __invoke(string $path, \AKlump\Directio\Model\DocumentInterface $document): void {
    if (!file_put_contents($path, $document->getContent())) {
      throw new RuntimeException(sprintf('Failed to write: %s', $path));
    }
  }
}
