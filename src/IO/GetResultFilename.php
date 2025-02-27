<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\IO;

use DateTimeInterface;

/**
 * Create the filename to be used for the filtered document.
 */
class GetResultFilename {

  private DateTimeInterface $date;

  /**
   * @param \DateTimeInterface|null $date (Optional) The date document was filtered.
   * This will be stamped into the name if provided.
   */
  public function __construct(DateTimeInterface $date = NULL) {
    if (isset($date)) {
      $this->date = $date;
    }
  }

  /**
   * @param string $source_path The path or basename of the original document.
   * The directory will be discorded, if included.
   *
   * @return string The basename to be used for the filtered documment.
   */
  public function __invoke(string $source_path): string {
    if (isset($this->date)) {
      return sprintf("%s_%s.%s",
        $this->date->format('Y-m-d'),
        pathinfo($source_path, PATHINFO_FILENAME),
        pathinfo($source_path, PATHINFO_EXTENSION)
      );
    }

    return basename($source_path);
  }
}
