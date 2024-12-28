<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\TextProcessor;

use AKlump\Directio\Exception\BadWhitespaceException;
use AKlump\Directio\Exception\NestedTagsException;
use AKlump\Directio\Exception\NoClosingException;
use AKlump\Directio\Exception\NoIDException;
use AKlump\Directio\Exception\NoOpeningException;
use AKlump\Directio\Lexer\TaskLexer;

class ValidateTaskSyntax {

  /**
   * @var \AKlump\Directio\Lexer\TaskLexer
   */
  private TaskLexer $lexer;

  /**
   * @param string $content
   *
   * @return void
   *
   */
  public function __invoke(string $content): void {
    $this->checkWhitespace($content);
    $this->lexer = new TaskLexer();
    $this->lexer->setInput($content);
    $this->lexer->moveNext();

    /** @var array $tags_stack Used to track nested tags and their attributes. */
    $tags_stack = [];

    /** @var array $open_tags Used to detect if we have had an open tag. */
    $open_tags = [];

    /** @var bool $tag_is_open Holds current tag open/close state. */
    $tag_is_open = FALSE;

    while (TRUE) {
      if (NULL === $this->lexer->lookahead) {
        break;
      }
      $this->lexer->moveNext();
      $position_message = 'At position: ' . $this->lexer->token->position;
      $token = $this->lexer->token;
      if ($this->lexer->isA($token->value, TaskLexer::T_OPEN_TAG)) {
        $tag_is_open = TRUE;
        $tags_stack[] = $token->value;
        $open_tags[] = $token->value;

        $attributes = (new ParseAttributes())($token->value);
        if (empty($attributes['id'])) {
          throw new NoIDException($position_message);
        }
      }
      elseif ($this->lexer->isA($token->value, TaskLexer::T_CLOSE_TAG)) {
        if (FALSE === $tag_is_open) {
          throw new NoOpeningException($position_message);
        }
        array_pop($tags_stack);
        $tag_is_open = FALSE;
      }
      if (count($tags_stack) > 1) {
        $ids = array_map(function ($attributes) {
          return (new ParseAttributes())($attributes)['id'] ?? 'NULL';
        }, $tags_stack);
        $ids = implode('>', $ids);
        throw new NestedTagsException(sprintf('Nesting directio tags (%s) is not supported.', $ids));
      }
    }
    if (empty($open_tags)) {
      throw new NoOpeningException();
    }
    if (TRUE === $tag_is_open) {
      throw new NoClosingException();
    }
  }

  private function checkWhitespace(string $content) {
    $result = preg_match_all('#<!--\s?directio.*?[^\s]-->#', $content, $matches);
    if ($result) {
      throw new BadWhitespaceException(implode(PHP_EOL, $matches[0]));
    }
  }

}
