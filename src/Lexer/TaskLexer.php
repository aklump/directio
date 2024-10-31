<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Lexer;

use Doctrine\Common\Lexer\AbstractLexer;

/**
 * TaskLexer is responsible for tokenizing task-related markup.
 *
 * It inherits from AbstractLexer and defines specific token types relevant to task processing.
 */
class TaskLexer extends AbstractLexer {

  const T_NONE = 1;

  const T_OPEN_TAG = 2;

  const T_CONTENT = 4;

  const T_CLOSE_TAG = 5;

  private bool $tagIsOpen = FALSE;

  private array $nested = [];

  /**
   * @inheritDoc
   */
  protected function getCatchablePatterns() {
    return [
      '<!-- directio -->',
      '<!-- directio .+? -->',
      '<!-- \/directio -->',
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
    if (strpos($value, '<!-- directio') === 0) {
      $this->nested[] = $value;
      $this->tagIsOpen = TRUE;

      return self::T_OPEN_TAG;
    }
    elseif ('<!-- /directio -->' === $value) {
      array_pop($this->nested);
      $this->tagIsOpen = FALSE;

      return self::T_CLOSE_TAG;
    }

    return $this->tagIsOpen ? self::T_CONTENT : self::T_NONE;
  }

}
