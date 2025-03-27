<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Lexer;

use AKlump\Directio\HTMLElementStyle;
use AKlump\Directio\Traits\HasStyleTrait;
use Doctrine\Common\Lexer\AbstractLexer;

/**
 * TaskLexer is responsible for tokenizing task-related markup.
 *
 * It inherits from AbstractLexer and defines specific token types relevant to task processing.
 */
class TaskLexer extends AbstractLexer {

  use HasStyleTrait;

  const T_NONE = 1;

  const T_OPEN_TAG = 2;

  const T_CONTENT = 4;

  const T_CLOSE_TAG = 5;

  private bool $tagIsOpen = FALSE;

  private array $nested = [];

  public function __construct() {
    $this->setStyle(new HTMLElementStyle());
  }

  /**
   * @inheritDoc
   */
  protected function getCatchablePatterns() {
    return [
      $this->openTagPattern,
      $this->closeTagPattern,
    ];
  }

  /**
   * @inheritDoc
   */
  protected function getNonCatchablePatterns() {
    return [];
  }

  /**
   * @inheritDoc
   */
  protected function getType(&$value) {
    if (strpos($value, $this->openTagStart) === 0) {
      $this->nested[] = $value;
      $this->tagIsOpen = TRUE;

      return self::T_OPEN_TAG;
    }
    elseif ($this->style->getCloseTagConstant() === $value) {
      array_pop($this->nested);
      $this->tagIsOpen = FALSE;

      return self::T_CLOSE_TAG;
    }

    return $this->tagIsOpen ? self::T_CONTENT : self::T_NONE;
  }

}
