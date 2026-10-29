<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Model;

interface DocumentInterface {

  /**
   * @param string $id The task ID to remove.
   *
   * @return \AKlump\Directio\DocumentInterface A new instance without the task.
   */
  public function withoutTask(string $id): DocumentInterface;

  /**
   * @param callable(\AKlump\Directio\Model\Task): bool $predicate Returns
   * TRUE for each task to remove.
   *
   * @return \AKlump\Directio\Model\DocumentInterface A new instance without
   * the matching tasks.
   */
  public function withoutTasksMatching(callable $predicate): DocumentInterface;

  /**
   * @return string[] An array of all directio ids in the document.
   */
  public function getIds(): array;

  public function setContent($content): self;

  public function getContent(): string;

}
