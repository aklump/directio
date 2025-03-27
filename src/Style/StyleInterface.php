<?php

namespace AKlump\Directio\Style;

interface StyleInterface {

  /**
   * @return string The REGEX PATTERN to capture the open tag.  It must contain
   * a non-capturing, optional, greedy group to indicate the attributes section.
   * Here are some correct examples:
   * - <directio(?: .+?)?>
   * - <!-- directio(?: .+?)? -->
   */
  public function getOpenTagPattern(): string;

  /**
   * @return string The literal string of the close tag.  Not a regex pattern.
   */
  public function getCloseTagConstant(): string;
}
