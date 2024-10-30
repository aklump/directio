<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Lexer;

use Doctrine\Common\Lexer\AbstractLexer;

/**
 * Responsible for parsing attribute strings from open tags or attribute lists.
 *
 * Example inputs:
 * - <!-- directio id=foo -->
 * - id=foo
 * - <!-- directio id="lorem ipsum" -->
 * - id="lorem ipsum"
 * - <!-- directio id=foo done -->
 * - id=foo done
 *
 * @see \AKlump\Directio\Lexer\TaskLexer::T_OPEN_TAG
 * @see \AKlump\Directio\Lexer\TaskLexer::T_ATTRIBUTES
 */
class AttributesLexer extends AbstractLexer {

  const T_NONE = 1;

  const T_ATTRIBUTE_NAME = 2;

  const T_ATTRIBUTE_VALUE = 3;

  private bool $assignment = FALSE;

  private bool $assignValue = FALSE;

  /**
   * @inheritDoc
   */
  protected function getCatchablePatterns() {
    return [
      '\[[x ]?\]',
      '(\w+)="(.+?)"',
      '(\w+)=([^\W]+)',
      '\b\w+\b',
    ];
  }

  /**
   * @inheritDoc
   */
  protected function getNonCatchablePatterns() {
    return [
      '<!-- directio ',
      ' -->',
      ' ',
    ];
  }

  /**
   * @inheritDoc
   */
  protected function getType(&$value) {
    if (strstr($value, '=')) {
      $this->assignment = TRUE;

      return self::T_NONE;
    }
    if (!$this->assignment) {
      return self::T_ATTRIBUTE_NAME;
    }
    if (FALSE === $this->assignValue) {
      $this->assignValue = TRUE;

      return self::T_ATTRIBUTE_NAME;
    }
    $this->assignValue = FALSE;
    $this->assignment = FALSE;

    return self::T_ATTRIBUTE_VALUE;
  }

}
