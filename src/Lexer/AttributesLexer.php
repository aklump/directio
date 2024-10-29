<?php

namespace AKlump\Directio\Lexer;

use Doctrine\Common\Lexer\AbstractLexer;

/**
 * AttributesLexer is responsible for parsing attribute strings (e.g., "foo=lorem ipsum bar=baz")
 * and tokenizing them for further processing.
 */
class AttributesLexer extends AbstractLexer {

  const EOC = "\u{FFFF}";

  const T_NONE = 1;

  const T_ATTRIBUTE_NAME = 2;

  const T_ATTRIBUTE_VALUE = 3;

  private string $value;

  /**
   * @var true
   */
  private bool $assign = FALSE;

  /**
   * @param string $input The attributes string, e.g. "foo=lorem ipsum bar=baz",
   * such as is returned by the TaskLexer as the
   * \AKlump\Directio\Lexer\TaskLexer::T_ATTRIBUTES token.
   *
   * @return void
   *
   * @see \AKlump\Directio\Lexer\TaskLexer::T_ATTRIBUTES
   */
  protected function scan($input) {
    // We are using an End Of Content character to help parse the final
    // attribute value.  For more info see
    // \AKlump\Directio\Lexer\AttributesLexer::getType.
    parent::scan($input . self::EOC);
  }

  /**
   * @inheritDoc
   */
  protected function getCatchablePatterns() {
    return [
      '\s?(\S+)=(.+?)',
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
    if (strstr($value, '=')) {
      $this->assign = TRUE;

      return $this->returnValueOrNone($value);
    }
    elseif ($this->assign && !isset($this->value)) {
      $this->value = '';

      return self::T_ATTRIBUTE_NAME;
    }
    elseif (self::EOC === $value && isset($this->value)) {
      return $this->returnValueOrNone($value);
    }

    if (isset($this->value)) {
      $this->value .= $value;
    }

    return self::T_NONE;
  }

  protected function returnValueOrNone(&$value): int {
    if (isset($this->value)) {
      $value = $this->value;
      unset($this->value);

      return self::T_ATTRIBUTE_VALUE;
    }

    return self::T_NONE;
  }

}
