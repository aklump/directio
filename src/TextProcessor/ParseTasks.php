<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\TextProcessor;

use AKlump\Directio\Model\Task;

/**
 * Reads a document's tasks: every heading found by {@see ScanHeadings}
 * whose last token is a "{ … }" attribute block.
 *
 * A task's body runs from its heading to the next heading of the same or
 * a higher level, or to the end of the document. Any heading closes open
 * tasks, whether or not it is itself a task.
 *
 * Nesting is allowed at this layer: a task may open while a shallower
 * task is still open. {@see ValidateTaskSyntax} is the one place that
 * rejects it.
 */
class ParseTasks {

  /**
   * @param string $content
   *
   * @return \AKlump\Directio\Model\Task[] Closed tasks, innermost first
   *   within any group that closes at the same heading.
   */
  public function __invoke(string $content): array {
    $tasks = [];
    /** @var \AKlump\Directio\Model\Task[] $open */
    $open = [];

    foreach ((new ScanHeadings())($content) as $heading) {
      while (!empty($open) && end($open)->getLevel() >= $heading->getLevel()) {
        $task = array_pop($open);
        $task->setEnd($heading->getStart());
        $tasks[] = $task;
      }

      if ($heading->hasBlock()) {
        $open[] = new Task(
          $heading->getLevel(),
          $heading->getTitle(),
          (new ParseAttributes())($heading->getBlock()),
          $heading->getStart(),
          $heading->getBlockOffset(),
          strlen($heading->getBlock()),
        );
      }
    }

    while (!empty($open)) {
      $task = array_pop($open);
      $task->setEnd(strlen($content));
      $tasks[] = $task;
    }

    return $tasks;
  }

}
