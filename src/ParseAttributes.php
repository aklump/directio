<?php

namespace AKlump\Directio;

use AKlump\Directio\Lexer\AttributesLexer;

class ParseAttributes {

  /**
   * @param string $open_tag , e.g., "<!-- directio id=foo -->".
   *
   * @return array
   */
  public function __invoke(string $open_tag): array {
    $lexer = new AttributesLexer();
    $lexer->setInput($open_tag);
    $lexer->moveNext();

    $attributes = [];
    while (TRUE) {
      if (!$lexer->lookahead) {
        break;
      }
      $lexer->moveNext();
      if ($lexer->token->isA(AttributesLexer::T_ATTRIBUTE_NAME)) {
        $name = $lexer->token->value;
        $attributes[$name] = TRUE;
      }
      elseif (!empty($name)
        && $lexer->token->isA(AttributesLexer::T_ATTRIBUTE_VALUE)) {
        $attributes[$name] = $lexer->token->value;
        unset($name);
      }
    }

    return $attributes;
  }

}
