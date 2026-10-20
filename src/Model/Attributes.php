<?php

namespace AKlump\Directio\Model;

use AKlump\Directio\Config\SpecialAttributes;
use Stringable;

/**
 * Renders an attribute array as the inner text of a heading's "{ … }"
 * attribute block (without the braces themselves).
 *
 * An id/name value is written as the "#value" shorthand when its characters
 * fit the "#name" grammar (`[A-Za-z0-9_-]+`); otherwise it falls back to
 * `id="value"` so an id with a character the shorthand can't carry (e.g. a
 * stray non-ASCII character) still round-trips. A boolean TRUE value is a
 * bare flag, written under whatever key it was given (`x`, `done`,
 * `complete`, …), unchanged; a boolean FALSE value is omitted. Any other
 * value is quoted only when it contains whitespace, `"`, or `}`.
 */
class Attributes implements Stringable {

  private array $value;

  public function __construct(array $attributes = []) {
    $this->value = $attributes;
  }

  public function __toString() {
    $id_keys = SpecialAttributes::idKeys();
    $nodes = [];
    foreach ($this->value as $key => $value) {
      if (is_bool($value)) {
        if (TRUE === $value) {
          $nodes[] = $key;
        }
        continue;
      }

      $value = (string) $value;
      if (isset($id_keys[$key]) && preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
        $nodes[] = '#' . $value;
        continue;
      }

      if (preg_match('/[\s"}]/', $value)) {
        $nodes[] = sprintf('%s="%s"', $key, $value);
      }
      else {
        $nodes[] = sprintf('%s=%s', $key, $value);
      }
    }

    return implode(' ', $nodes);
  }
}
