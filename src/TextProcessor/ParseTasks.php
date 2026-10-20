<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\TextProcessor;

use AKlump\Directio\Model\Task;

/**
 * Scans a document for Markdown headings that carry a "{ … }" attribute
 * block, and returns each one as a {@see Task}.
 *
 * A task is any ATX heading (`#` through `######`) whose last token is a
 * "{ … }" block. Its body runs from the heading to the next heading of
 * the same or a higher level, or to the end of the document. A heading
 * inside a fenced code block (``` or ~~~) is never a heading.
 *
 * Nesting is allowed at this layer — a task may open while a shallower
 * task is still open. {@see \AKlump\Directio\TextProcessor\ValidateTaskSyntax}
 * is the one place that rejects it.
 *
 * Setext headings (underlined with "===" or "---") are not recognized
 * yet; that is added in a later phase.
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

    foreach ($this->splitLines($content) as $line) {
      $lineStart = $offset;
      $offset += strlen($line);
      $line = rtrim($line, "\r\n");

      if (NULL !== $fence) {
        if ($this->closesFence($line, $fence)) {
          $fence = NULL;
        }
        continue;
      }

      $openedFence = $this->matchFenceOpen($line);
      if (NULL !== $openedFence) {
        $fence = $openedFence;
        continue;
      }

      $heading = $this->matchAtxHeading($line);
      if (NULL === $heading) {
        continue;
      }

      while (!empty($open) && end($open)->getLevel() >= $heading['level']) {
        $task = array_pop($open);
        $task->setEnd($lineStart);
        $tasks[] = $task;
      }

      if (NULL !== $heading['block']) {
        $attributes = (new ParseAttributes())($heading['block']);
        $open[] = new Task(
          $heading['level'],
          $heading['title'],
          $attributes,
          $lineStart,
          $lineStart + $heading['blockOffsetInLine'],
          strlen($heading['block']),
        );
      }
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

    // CommonMark's optional closing "#" run: text, at least one space, a
    // run of "#", then only spaces/tabs to the end of the line.
    if (preg_match('/^(.*?)[ \t]+#+[ \t]*$/', $rest, $closing)) {
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
      'level' => $level,
      'title' => $title,
      'block' => $block,
      'blockOffsetInLine' => NULL === $block ? NULL : $restOffset + $blockOffsetInRest,
    ];
  }

}
