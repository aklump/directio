<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\TextProcessor;

use AKlump\Directio\Config\SpecialAttributes;
use AKlump\Directio\Exception\InvalidRedoException;
use AKlump\Directio\Exception\LegacySyntaxException;
use AKlump\Directio\Exception\NestedTasksException;
use AKlump\Directio\Exception\NoIDException;
use AKlump\Directio\Model\Task;

/**
 * Validates every task read by {@see ParseTasks}: each one must carry an
 * id, its "redo" (if any) must be a valid period, and no task may nest
 * inside another (Q5).
 *
 * A document that still contains the retired "<directio" element markup
 * fails with {@see LegacySyntaxException}, naming `directio convert` as
 * the fix.
 */
class ValidateTaskSyntax {

  /**
   * @param string $content
   *
   * @return void
   */
  public function __invoke(string $content): void {
    if (FALSE !== strpos($content, '<directio')) {
      throw new LegacySyntaxException('Old <directio> markup found. Run `directio convert` on this file.');
    }

    /** @var \AKlump\Directio\Model\Task[] $validated Tasks already checked, used to detect nesting. */
    $validated = [];

    foreach ((new ParseTasks())($content) as $task) {
      $attributes = $task->getAttributes();
      $position_message = 'At position: ' . $task->getStart();

      if (NULL === SpecialAttributes::extractId($attributes)) {
        throw new NoIDException($position_message);
      }

      $redo = SpecialAttributes::extractExpires($attributes);
      if (!SpecialAttributes::isValidExpires($redo)) {
        throw new InvalidRedoException(sprintf('%s: "%s"', $position_message, $redo));
      }

      foreach ($validated as $previous) {
        if ($previous->getStart() >= $task->getStart() && $previous->getEnd() <= $task->getEnd()) {
          throw new NestedTasksException(sprintf(
            'Nesting tasks (%s>%s) is not supported.',
            SpecialAttributes::extractId($attributes),
            SpecialAttributes::extractId($previous->getAttributes())
          ));
        }
      }

      $validated[] = $task;
    }
  }

}
