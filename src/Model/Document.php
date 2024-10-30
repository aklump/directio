<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Model;

use AKlump\Directio\Lexer\TaskLexer;
use AKlump\Directio\TextProcessor\ParseAttributes;

class Document implements DocumentInterface {

  protected string $content = '';

  public function getContent(): string {
    return $this->content;
  }

  public function setContent($content): self {
    $this->content = $content;

    return $this;
  }

  /**
   * @param string $id The task attribute ID to remove.
   *
   * @return \AKlump\Directio\Model\DocumentInterface A new document without the
   * task(s) indicated by matching the id attribute with $id.
   */
  public function withoutTask(string $id): DocumentInterface {
    $content = $this->getContent();
    $cuts = [];

    $lexer = new TaskLexer();
    $lexer->setInput($content);
    $lexer->moveNext();

    // Save some time by moving to the first task.
    $lexer->skipUntil(TaskLexer::T_OPEN_TAG);

    while (TRUE) {
      if (NULL === $lexer->lookahead) {
        break;
      }
      $lexer->moveNext();

      if ($lexer->token->isA(TaskLexer::T_OPEN_TAG)) {
        $start_position = $lexer->token->position;
      }
      elseif ($lexer->token->isA(TaskLexer::T_ATTRIBUTES)) {
        $attributes = (new ParseAttributes())($lexer->token->value);
        // Check the id of this task against the method argument, and discard
        // the start position (preventing the cut) if the id doesn't match.
        if ($attributes['id'] != $id) {
          unset($start_position);
        }
      }
      elseif (isset($start_position) && $lexer->token->isA(TaskLexer::T_CLOSE_TAG)) {
        $cuts[] = [
          $start_position,
          $lexer->token->position + strlen($lexer->token->value),
        ];
      }
    }
    $content = $this->applyCuts($cuts, $content);

    return (new Document())->setContent($content);
  }

  private function applyCuts(array $cuts, string $content): string {
    // We have to do this from right to left, otherwise the indexes do not work.
    $cuts = array_reverse($cuts);
    foreach ($cuts as $cut) {
      $pre = substr($content, 0, $cut[0]);
      $post = substr($content, $cut[1]);
      $content = rtrim($pre, PHP_EOL) . PHP_EOL . PHP_EOL . ltrim($post, PHP_EOL);
    }

    return $content;
  }

}
