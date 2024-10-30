<?php

namespace AKlump\Directio\IO;

use RuntimeException;

class ReadDocument {

  /**
   * @param string $path
   *
   * @return \AKlump\Directio\Model\DocumentInterface
   *
   * @throws \RuntimeException If the document cannot be read.
   */
  public function __invoke(string $path): \AKlump\Directio\Model\DocumentInterface {
    if (!file_exists($path)) {
      throw new RuntimeException(sprintf('Document does not exist: %s', $path));
    }
    $data = file_get_contents($path);

    return (new \AKlump\Directio\Model\Document())->setContent($data);
  }
}
