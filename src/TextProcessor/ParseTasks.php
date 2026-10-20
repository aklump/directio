<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\TextProcessor;

use AKlump\Directio\Model\Task;

/**
 * Scans a document for Markdown headings that carry a "{ … }" attribute
 * block, and returns each one as a {@see Task}.
 *
 * A task is any ATX heading (`#` through `######`) or setext heading
 * (underlined with "===" or "---") whose last token is a "{ … }" block.
 * Its body runs from the heading to the next heading of the same or a
 * higher level, or to the end of the document. A heading inside a fenced
 * code block (``` or ~~~) is never a heading.
 *
 * A setext underline ("===" or "---" alone on a line) only counts as a
 * heading when the line directly above it is paragraph text: not blank,
 * not a list item, not an HTML line, not already a heading, not a block
 * quote, and not itself a bare "==="/"---" run.
 *
 * Nesting is allowed at this layer — a task may open while a shallower
 * task is still open. {@see \AKlump\Directio\TextProcessor\ValidateTaskSyntax}
 * is the one place that rejects it.
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
    $fence = NULL;
    $offset = 0;

    /**
     * @var array{text: string, offset: int, isParagraph: bool}|null $prevLine
     *   The most recently seen line that was not itself a heading, used to
     *   test whether the current line is a setext underline for it.
     */
    $prevLine = NULL;

    foreach ($this->splitLines($content) as $line) {
      $lineStart = $offset;
      $offset += strlen($line);
      $line = rtrim($line, "\r\n");

      if (NULL !== $fence) {
        if ($this->closesFence($line, $fence)) {
          $fence = NULL;
        }
        $prevLine = NULL;
        continue;
      }

      $openedFence = $this->matchFenceOpen($line);
      if (NULL !== $openedFence) {
        $fence = $openedFence;
        $prevLine = NULL;
        continue;
      }

      $heading = $this->matchAtxHeading($line);
      $headingStart = $lineStart;
      if (NULL === $heading) {
        $heading = $this->matchSetextHeading($line, $prevLine);
        if (NULL !== $heading) {
          $headingStart = $prevLine['offset'];
        }
      }

      if (NULL === $heading) {
        $prevLine = [
          'text' => $line,
          'offset' => $lineStart,
          'isParagraph' => $this->isParagraphText($line),
        ];
        continue;
      }

      while (!empty($open) && end($open)->getLevel() >= $heading['level']) {
        $task = array_pop($open);
        $task->setEnd($headingStart);
        $tasks[] = $task;
      }

      if (NULL !== $heading['block']) {
        $attributes = (new ParseAttributes())($heading['block']);
        $open[] = new Task(
          $heading['level'],
          $heading['title'],
          $attributes,
          $headingStart,
          $headingStart + $heading['blockOffsetInLine'],
          strlen($heading['block']),
        );
      }

      // The heading line (and, for a setext heading, the title line above
      // it) has been consumed; it can't also serve as the paragraph text
      // for a setext underline on the line that follows.
      $prevLine = NULL;
    }

    while (!empty($open)) {
      $task = array_pop($open);
      $task->setEnd(strlen($content));
      $tasks[] = $task;
    }

    return $tasks;
  }

  /**
   * @param string $content
   *
   * @return string[] Each line of $content, with its line ending (if any)
   *   still attached, so the pieces concatenate back to $content exactly.
   */
  private function splitLines(string $content): array {
    if ('' === $content) {
      return [];
    }

    return preg_split('/(?<=\n)/', $content);
  }

  /**
   * @param string $line A line with no trailing line-ending characters.
   *
   * @return array{char: string, len: int}|null
   */
  private function matchFenceOpen(string $line): ?array {
    if (preg_match('/^[ \t]*(`{3,}|~{3,})/', $line, $m)) {
      return ['char' => $m[1][0], 'len' => strlen($m[1])];
    }

    return NULL;
  }

  /**
   * @param string $line A line with no trailing line-ending characters.
   * @param array{char: string, len: int} $fence
   *
   * @return bool
   */
  private function closesFence(string $line, array $fence): bool {
    $pattern = sprintf('/^[ \t]*%s{%d,}[ \t]*$/', preg_quote($fence['char'], '/'), $fence['len']);

    return 1 === preg_match($pattern, $line);
  }

  /**
   * @param string $line A line with no trailing line-ending characters.
   *
   * @return array{level: int, title: string, block: string|null, blockOffsetInLine: int|null}|null
   */
  private function matchAtxHeading(string $line): ?array {
    if (!preg_match('/^[ ]{0,3}(#{1,6})(?:[ \t]+(.*))?$/', $line, $m, PREG_OFFSET_CAPTURE)) {
      return NULL;
    }

    $level = strlen($m[1][0]);
    $restOffset = isset($m[2]) ? $m[2][1] : $m[1][1] + $level;
    $rest = $m[2][0] ?? '';

    $parsed = $this->parseHeadingBody($rest, TRUE);

    return [
      'level' => $level,
      'title' => $parsed['title'],
      'block' => $parsed['block'],
      'blockOffsetInLine' => NULL === $parsed['block'] ? NULL : $restOffset + $parsed['blockOffsetInRest'],
    ];
  }

  /**
   * @param string $line A line with no trailing line-ending characters,
   *   already confirmed not to be an ATX heading.
   * @param array{text: string, offset: int, isParagraph: bool}|null $prevLine
   *   The line directly above $line, or NULL if there isn't one (or it was
   *   consumed by fence/heading handling).
   *
   * @return array{level: int, title: string, block: string|null, blockOffsetInLine: int|null}|null
   *   `blockOffsetInLine` is relative to $prevLine's own offset, since a
   *   setext heading's title line — not the underline — is where the task
   *   starts.
   */
  private function matchSetextHeading(string $line, ?array $prevLine): ?array {
    if (NULL === $prevLine || !$prevLine['isParagraph']) {
      return NULL;
    }

    if (preg_match('/^[ ]{0,3}=+[ \t]*$/', $line)) {
      $level = 1;
    }
    elseif (preg_match('/^[ ]{0,3}-+[ \t]*$/', $line)) {
      $level = 2;
    }
    else {
      return NULL;
    }

    $parsed = $this->parseHeadingBody($prevLine['text'], FALSE);

    return [
      'level' => $level,
      'title' => $parsed['title'],
      'block' => $parsed['block'],
      'blockOffsetInLine' => NULL === $parsed['block'] ? NULL : $parsed['blockOffsetInRest'],
    ];
  }

  /**
   * Strips an ATX heading's optional closing "#" run (when requested) and
   * extracts a trailing "{ … }" attribute block, shared by both heading
   * kinds.
   *
   * @param string $rest The heading text after any leading "#" marker (ATX)
   *   or the whole line (setext).
   * @param bool $stripClosingHashRun Whether to recognize CommonMark's
   *   optional ATX closing "#" run; setext headings have no such thing.
   *
   * @return array{title: string, block: string|null, blockOffsetInRest: int|null}
   */
  private function parseHeadingBody(string $rest, bool $stripClosingHashRun): array {
    if ($stripClosingHashRun && preg_match('/^(.*?)[ \t]+#+[ \t]*$/', $rest, $closing)) {
      $rest = $closing[1];
    }
    else {
      $rest = rtrim($rest, " \t");
    }

    $title = $rest;
    $block = NULL;
    $blockOffsetInRest = NULL;
    if (preg_match('/^(.*?)[ \t]+(\{(?:[^{}"]|"[^"]*")*\})[ \t]*$/', $rest, $blockMatch, PREG_OFFSET_CAPTURE)) {
      $title = trim($blockMatch[1][0]);
      $block = $blockMatch[2][0];
      $blockOffsetInRest = $blockMatch[2][1];
    }

    return [
      'title' => $title,
      'block' => $block,
      'blockOffsetInRest' => $blockOffsetInRest,
    ];
  }

  /**
   * Tests CommonMark's rule for the line a setext underline sits beneath:
   * not blank, not a list item, not an HTML line, not already a heading,
   * not a block quote, and not itself a bare "==="/"---" run (which can't
   * be a paragraph, so it can't be a setext title either).
   *
   * @param string $line A line with no trailing line-ending characters.
   *
   * @return bool
   */
  private function isParagraphText(string $line): bool {
    if ('' === trim($line)) {
      return FALSE;
    }

    if (preg_match('/^[ ]{0,3}(?:[-*+][ \t]+|\d{1,9}[.)][ \t]+)/', $line)) {
      return FALSE;
    }

    if (preg_match('/^[ ]{0,3}</', $line)) {
      return FALSE;
    }

    if (preg_match('/^[ ]{0,3}>/', $line)) {
      return FALSE;
    }

    if (NULL !== $this->matchAtxHeading($line)) {
      return FALSE;
    }

    if (preg_match('/^[ ]{0,3}(?:=+|-+)[ \t]*$/', $line)) {
      return FALSE;
    }

    return TRUE;
  }

}
