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
   * @return string[] An array of all directio ids in the document.
   */
  public function getIds(): array;

  public function setContent($content): self;

  public function getContent(): string;

}
