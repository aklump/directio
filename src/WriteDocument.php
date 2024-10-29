<?php

namespace AKlump\Directio;

use RuntimeException;

class WriteDocument {

  /**
   * @param string $path
   * @param \AKlump\Directio\DocumentInterface $document
   *
   * @return void
   *
   */
  public function __invoke(string $path, DocumentInterface $document): void {
    if (!file_put_contents($path, $document->getContent())) {
      throw new RuntimeException(sprintf('Failed to write: %s', $path));
    }
  }
}
