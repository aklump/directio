<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Exception;

class BadWhitespaceException extends \InvalidArgumentException {

  /**
   * @param string $message One or more snippets containing whitespace misuse, separated by PHP_EOL.
   * @param $code
   * @param \AKlump\Directio\Exception\Throwable|NULL $previous
   */
  public function __construct($message, $code = 0, Throwable $previous = NULL) {
    $message = "Bad whitespace:" . PHP_EOL . $message;
    parent::__construct($message, $code, $previous);
  }

}
