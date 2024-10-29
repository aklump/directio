<?php

namespace AKlump\Directio;

use DateTimeInterface;

/**
 * Create the filename to be used for the filtered document.
 */
class GetResultFilename {

  private DateTimeInterface $date;

  /**
   * @param \DateTimeInterface $date The date document was filtered.
   */
  public function __construct(DateTimeInterface $date) {
    $this->date = $date;
  }

  /**
   * @param string $source_path The path or basename of the original document.
   * The directory will be discorded, if included.
   *
   * @return string The basename to be used for the filtered documment.
   */
  public function __invoke(string $source_path): string {
    return sprintf("%s_%s.%s",
      pathinfo($source_path, PATHINFO_FILENAME),
      $this->date->format('Y-m-d_His'),
      pathinfo($source_path, PATHINFO_EXTENSION)
    );
  }
}
