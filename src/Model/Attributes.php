<?php

namespace AKlump\Directio\Model;

use Stringable;

class Attributes implements Stringable {

  private array $value = [];

  public function __construct(array $attributes = []) {
    $this->value = $attributes;
  }

  public function __toString() {

    $foo = [];
    foreach ($this->value as $key => $value) {
      if (FALSE === $value) {
        continue;
      }
      if (TRUE === $value) {
        $foo[] = $key;
        continue;
      }
      $value = htmlspecialchars((string) $value, ENT_QUOTES);
      if (strstr($value, ' ')) {
        $value = '"' . $value . '"';
      }
      $foo[] = sprintf('%s=%s', $key, $value);
    }

    return implode(' ', $foo);
  }
}
