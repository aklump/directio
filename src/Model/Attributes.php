<?php

namespace AKlump\Directio\Model;

use AKlump\Directio\Config\SpecialAttributes;
use InvalidArgumentException;
use Stringable;

/**
 * Renders an attribute array as the inner text of a heading's "{ … }"
 * attribute block (without the braces themselves), in a form
 * {@see \AKlump\Directio\TextProcessor\ParseAttributes} reads back.
 *
 * An id/name value is written as the "#value" shorthand when its characters
 * fit the "#name" grammar (`[A-Za-z0-9_-]+`); otherwise it falls back to
 * `id=value`. A boolean TRUE value is a bare flag, written under whatever
 * key it was given (`x`, `done`, `complete`, …), unchanged; a boolean FALSE
 * value is omitted. Any other value is quoted only when it is empty or
 * contains whitespace, `{`, or `}`. The grammar has no escape for `"`, so
 * a value containing one cannot be rendered and is rejected.
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
      if (str_contains($value, '"')) {
        throw new InvalidArgumentException(sprintf('The value of "%s" contains a double quote, which a { … } attribute block cannot carry.', $key));
      }

      if (isset($id_keys[$key]) && preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
        $nodes[] = '#' . $value;
        continue;
      }

      if ('' === $value || preg_match('/[\s{}]/', $value)) {
        $nodes[] = sprintf('%s="%s"', $key, $value);
      }
      else {
        $nodes[] = sprintf('%s=%s', $key, $value);
      }
    }

    return implode(' ', $nodes);
  }
}
