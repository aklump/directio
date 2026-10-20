<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\TextProcessor;

use AKlump\Directio\Config\SpecialAttributes;
use AKlump\Directio\Model\Task;

class ScanForCompletedTasks {

  /**
   * @param string $content
   *
   * @return array[] An array of completed task attributes.
   */
  public function __invoke(string $content): array {
    $done = array_filter(
      (new ParseTasks())($content),
      fn(Task $task) => SpecialAttributes::extractDone($task->getAttributes())
    );

    return array_values(array_map(fn(Task $task) => $task->getAttributes(), $done));
  }

}
