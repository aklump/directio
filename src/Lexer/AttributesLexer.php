<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Lexer;

use AKlump\Directio\HTMLElementStyle;
use AKlump\Directio\Traits\HasStyleTrait;
use Doctrine\Common\Lexer\AbstractLexer;

/**
 * Responsible for parsing attribute strings from open tags or attribute lists.
 *
 * Example inputs:
 * - <directio id="foo">
 * - id=foo
 * - <directio id="lorem ipsum">
 * - id="lorem ipsum"
 * - <directio id="foo" done>
 * - id=foo done
 *
 * @see \AKlump\Directio\Lexer\TaskLexer::T_OPEN_TAG
 */
class AttributesLexer extends AbstractLexer {

  use HasStyleTrait;

  const T_NONE = 1;

  const T_ATTRIBUTE_NAME = 2;

  const T_ATTRIBUTE_VALUE = 3;

  private bool $assignment = FALSE;

  private bool $assignValue = FALSE;

  public function __construct() {
    $this->setStyle(new HTMLElementStyle());
  }

  /**
   * @inheritDoc
   */
  protected function getCatchablePatterns() {
    return [
      '(\w+)="(.+?)"',
      '\b\w+\b',
    ];
  }

  /**
   * @inheritDoc
   */
  protected function getNonCatchablePatterns() {
    return [
      $this->openTagStart,
      $this->openTagEnd,
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
