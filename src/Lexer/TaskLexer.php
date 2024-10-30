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

  const T_ATTRIBUTES = 3;

  const T_CONTENT = 4;

  const T_CLOSE_TAG = 5;

  private string $openTag = '';

  private bool $tagIsOpen = FALSE;

  /**
   * @inheritDoc
   */
  protected function getCatchablePatterns() {
    return [
      '<!-- directio (.*?) ?-->',
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
      $this->tagIsOpen = TRUE;
      $this->openTag = $value;

      return self::T_OPEN_TAG;
    }
    elseif ($this->tagIsOpen && strstr($value, '=') && strstr($this->openTag, $value) !== FALSE) {
      return self::T_ATTRIBUTES;
    }
    elseif ('<!-- /directio -->' === $value) {
      $this->tagIsOpen = FALSE;

      return self::T_CLOSE_TAG;
    }
    elseif ($this->tagIsOpen) {
      return self::T_CONTENT;
    }

    return self::T_NONE;
  }

}
