<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\TextProcessor;

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
   *
   */
  public function __invoke(string $content): void {
    $this->lexer = new TaskLexer();
    $this->lexer->setInput($content);
    $this->lexer->moveNext();

    $this->validateOpenTag();
    $this->validateID();
    $this->validateCloseTag();

  }

  private function validateOpenTag() {
    $this->lexer->skipUntil(TaskLexer::T_OPEN_TAG);
    if (empty($this->lexer->lookahead)) {
      throw new NoOpeningException();
    }
  }

  private function validateID() {
    $this->lexer->skipUntil(TaskLexer::T_ATTRIBUTES);
    if (NULL === $this->lexer->lookahead) {
      throw new NoIDException();
    }
    $attributes = (new ParseAttributes())($this->lexer->lookahead->value);
    if (empty($attributes['id'])) {
      throw new NoIDException();
    }
  }

  private function validateCloseTag() {
    $this->lexer->skipUntil(TaskLexer::T_CLOSE_TAG);
    if (empty($this->lexer->lookahead)) {
      throw new NoClosingException();
    }
  }

}
