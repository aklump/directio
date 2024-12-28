<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\IO;

use AKlump\Directio\Model\Document;
use AKlump\Directio\Model\DocumentInterface;
use RuntimeException;

class ReadDocument {

  /**
   * @param string $path
   *
   * @return \AKlump\Directio\Model\DocumentInterface
   *
   * @throws \RuntimeException If the document cannot be read.
   */
  public function __invoke(string $path): DocumentInterface {
    if (!file_exists($path)) {
      throw new RuntimeException(sprintf('Document does not exist: %s', $path));
    }
    $data = file_get_contents($path);

    return (new Document())->setContent($data);
  }
}
