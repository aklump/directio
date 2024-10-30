<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\TextProcessor;

use AKlump\Directio\Lexer\TaskLexer;
use AKlump\Directio\Config\SpecialAttributes;

class ScanForCompletedTasks {

  /**
   * @param string $content
   *
   * @return array[] An array of completed task attributes.
   */
  public function __invoke(string $content): array {
    $lexer = new TaskLexer();
    $lexer->setInput($content);
    $lexer->moveNext();

    // Save some time by moving to the first task.
    $lexer->skipUntil(TaskLexer::T_ATTRIBUTES);

    $completed_tasks = [];
    while (TRUE) {
      if (NULL === $lexer->lookahead) {
        break;
      }
      $lexer->moveNext();
      if ($lexer->token->isA(TaskLexer::T_ATTRIBUTES)) {
        $attributes = (new ParseAttributes())($lexer->token->value);
        if (array_intersect_key($attributes, SpecialAttributes::doneKeys())) {
          $completed_tasks[] = $attributes;
        }
      }
    }

    return $completed_tasks;
  }

}
