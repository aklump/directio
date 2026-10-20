<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Model;

/**
 * A Markdown heading as found by {@see \AKlump\Directio\TextProcessor\ScanHeadings}.
 *
 * Every heading in a document is one of these, whether or not it carries a
 * "{ … }" attribute block. Only the ones that do become a {@see Task}.
 */
final class Heading {

  public function __construct(
    private int $level,
    private string $title,
    private int $start,
    private int $end,
    private ?string $block,
    private ?int $blockOffset,
  ) {
  }

  /**
   * @return int The heading level, 1 through 6.
   */
  public function getLevel(): int {
    return $this->level;
  }

  /**
   * @return string The heading text with any closing "#" run and any
   *   "{ … }" block removed.
   */
  public function getTitle(): string {
    return $this->title;
  }

  /**
   * @return int The first byte of the heading's first line. For a setext
   *   heading that is the title line, not the underline.
   */
  public function getStart(): int {
    return $this->start;
  }

  /**
   * @return int The byte just past the heading's last line, line ending
   *   included. For a setext heading that is past the underline.
   */
  public function getEnd(): int {
    return $this->end;
  }

  public function hasBlock(): bool {
    return NULL !== $this->block;
  }

  /**
   * @return string|null The trailing "{ … }" block, braces included, or
   *   NULL when the heading has none.
   */
  public function getBlock(): ?string {
    return $this->block;
  }

  /**
   * @return int|null The byte offset of the block's "{" from the start of
   *   the document, or NULL when the heading has no block.
   */
  public function getBlockOffset(): ?int {
    return $this->blockOffset;
  }

}
