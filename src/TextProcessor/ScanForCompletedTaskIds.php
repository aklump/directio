<?php

namespace AKlump\Directio\TextProcessor;

use AKlump\Directio\Lexer\TaskLexer;

class ScanForCompletedTaskIds {

  /**
   * @param string $content
   *
   * @return string[] An array of task IDs that have been marked as complete.
   */
  public function __invoke(string $content): array {
    $lexer = new TaskLexer();
    $lexer->setInput($content);
    $lexer->moveNext();

    // Save some time by moving to the first task.
    $lexer->skipUntil(TaskLexer::T_ATTRIBUTES);

    $completed_task_ids = [];
    while (TRUE) {
      if (NULL === $lexer->lookahead) {
        break;
      }
      if ($lexer->token->isA(TaskLexer::T_ATTRIBUTES)) {
        $attributes = (new ParseAttributes())($lexer->token->value);
        if (array_intersect_key($attributes, SpecialAttributes::doneKeys())) {
          $completed_task_ids[] = $attributes['id'];
        }
      }
      $lexer->moveNext();
    }

    return $completed_task_ids;
  }

}
