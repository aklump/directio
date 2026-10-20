<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Model;

use AKlump\Directio\Config\SpecialAttributes;
use AKlump\Directio\TextProcessor\ParseTasks;

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

    foreach ((new ParseTasks())($content) as $task) {
      if (SpecialAttributes::extractId($task->getAttributes()) === $id) {
        $cuts[] = [$task->getStart(), $task->getEnd()];
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
      $content = rtrim($pre, PHP_EOL) . rtrim(PHP_EOL . PHP_EOL . ltrim($post, PHP_EOL));
      if (mb_strlen($content) > 0) {
        $content .= PHP_EOL;
      }
    }

    // A cut starting at byte 0 leaves a leading blank line; WriteDocument's
    // trim used to erase it by accident.
    $content = ltrim($content, PHP_EOL);

    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return array_map(
      fn(Task $task) => SpecialAttributes::extractId($task->getAttributes()),
      (new ParseTasks())($this->getContent())
    );
  }

}
