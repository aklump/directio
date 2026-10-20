<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Model;

/**
 * A single directio task as read by {@see \AKlump\Directio\TextProcessor\ParseTasks}.
 *
 * A task is a Markdown heading whose last token is a "{ … }" attribute
 * block. Its body runs from the heading to the next heading of the same or
 * a higher level, or to the end of the document.
 */
final class Task {

  public function __construct(
    private int $level,
    private string $title,
    private array $attributes,
    private int $start,
    private int $blockOffset,
    private int $blockLength,
    private ?int $end = NULL,
  ) {
  }

  /**
   * @return int The heading level, 1 ("#") through 6 ("######").
   */
  public function getLevel(): int {
    return $this->level;
  }

  /**
   * @return string The heading text, with its "{ … }" block removed.
   */
  public function getTitle(): string {
    return $this->title;
  }

  /**
   * @return array The attributes parsed from the task's "{ … }" block.
   */
  public function getAttributes(): array {
    return $this->attributes;
  }

  /**
   * @return int The first byte of the heading line that opens this task.
   */
  public function getStart(): int {
    return $this->start;
  }

  /**
   * @return int The first byte of the heading that closes this task (the
   *   next heading of the same or a higher level), or the length of the
   *   document when the task runs to the end of the file.
   */
  public function getEnd(): int {
    return $this->end ?? $this->start;
  }

  /**
   * Record where this task ends, once its closing heading (or the end of
   * the document) is found.
   *
   * @param int $end
   *
   * @return void
   */
  public function setEnd(int $end): void {
    $this->end = $end;
  }

  /**
   * @return int The byte offset of the "{" that opens this task's attribute
   *   block, measured from the start of the document.
   */
  public function getBlockOffset(): int {
    return $this->blockOffset;
  }

  /**
   * @return int The byte length of the "{ … }" block, braces included.
   */
  public function getBlockLength(): int {
    return $this->blockLength;
  }

}
