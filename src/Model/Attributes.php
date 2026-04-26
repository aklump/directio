<?php

namespace AKlump\Directio\Model;

use Stringable;

class Attributes implements Stringable {

  private array $value;

  public function __construct(array $attributes = []) {
    $this->value = $attributes;
  }

  public function __toString() {
    $nodes = [];
    foreach ($this->value as $key => $value) {
      if (is_bool($value)) {
        if (TRUE === $value) {
          $nodes[] = $key;
        }
      }
      else {
        $value = htmlspecialchars((string) $value, ENT_QUOTES);
        $nodes[] = sprintf('%s="%s"', $key, $value);
      }
    }

    return implode(' ', $nodes);
  }
}
