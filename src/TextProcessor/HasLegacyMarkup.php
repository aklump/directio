<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\TextProcessor;

/**
 * Detects the retired "<directio …>" element markup outside fenced code.
 *
 * A fenced example of the old syntax (say, in a document's own notes) is
 * not markup, and neither is "<directiofoo>"; the tag name must be followed
 * by whitespace, ">" or "/".
 */
final class HasLegacyMarkup {

  public function __invoke(string $content): bool {
    foreach ((new ScanLines())($content) as $line) {
      if (!$line['inFence'] && preg_match('/<directio(?=[\s>\/])/i', $line['text'])) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
