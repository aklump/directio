<?php

namespace AKlump\Directio\Helper;

use AKlump\Directio\Config\SpecialAttributes;
use AKlump\Directio\Model\Document;
use AKlump\Directio\Model\DocumentInterface;
use AKlump\Directio\TextProcessor\ParseTasks;

/**
 * Adds the 'done' attribute to a specific task in a document.
 */
final class MarkTaskDoneInDocument {

  /**
   * @param string $taskId The ID of the task to mark as done.
   * @param DocumentInterface $document The document to modify.
   *
   * @return DocumentInterface A new document with the task marked as done.
   */
  public function __invoke(string $taskId, DocumentInterface $document): DocumentInterface {
    $content = $document->getContent();
    $updates = [];

    foreach ((new ParseTasks())($content) as $task) {
      $attributes = $task->getAttributes();
      if (SpecialAttributes::extractId($attributes) === $taskId && !SpecialAttributes::extractDone($attributes)) {
        $updates[] = [
          'pos' => $task->getBlockOffset() + 1,
          'len' => 0,
          'new' => SpecialAttributes::defaultDoneKey() . ' ',
        ];
      }
    }

    if (empty($updates)) {
      return $document;
    }

    // Apply updates from right to left to maintain positions
    $updates = array_reverse($updates);
    foreach ($updates as $update) {
      $content = substr_replace($content, $update['new'], $update['pos'], $update['len']);
    }

    return (new Document())->setContent($content);
  }

}
