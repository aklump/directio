<?php

namespace AKlump\Directio\Helper;

use AKlump\Directio\Model\DocumentInterface;
use AKlump\Directio\Model\TaskStateInterface;
use DateTimeInterface;

final class ApplyStateToDocument {

  private DateTimeInterface $now;

  public function __construct(DateTimeInterface $now) {
    $this->now = $now;
  }

  /**
   * @param \AKlump\Directio\Model\TaskStateInterface[] $state
   * @param \AKlump\Directio\Model\DocumentInterface $document
   *
   * @return \AKlump\Directio\Model\DocumentInterface
   */
  public function __invoke(array $state, DocumentInterface $document): DocumentInterface {
    foreach ($state as $task) {
      if ($this->isTaskCompleteOrIsItTimeToRedo($task)) {
        $document = $document->withoutTask($task->getId());
      }
    }

    return $document;
  }

  private function isTaskCompleteOrIsItTimeToRedo(TaskStateInterface $task): bool {
    if (!$task->getCompleted()) {
      return FALSE;
    }
    $redo = $task->getRedo();
    $redo = date_create($redo);

    return $redo >= $this->now;
  }

}
