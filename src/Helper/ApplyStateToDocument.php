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
      return FALSE;                                    // never done: keep
    }
    if ('' === $task->getRedo()) {
      return TRUE;                                      // done once, no redo: hidden for good
    }

    return date_create($task->getRedo()) > $this->now;   // recurring: hidden until the period has passed
  }

}
