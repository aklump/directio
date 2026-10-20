<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\TextProcessor;

use AKlump\Directio\Model\Heading;

/**
 * Finds every Markdown heading in a document, fence-aware.
 *
 * Both ATX headings (`#` through `######`, with CommonMark's optional
 * closing "#" run) and setext headings (a paragraph line underlined with
 * "===" or "---") are recognized. A setext underline only counts when the
 * line directly above it is paragraph text: not blank, not a list item,
 * not an HTML line, not already a heading, not a block quote, and not
 * itself a bare "==="/"---" run. Nothing inside a fenced code block is a
 * heading.
 *
 * A heading whose last token is a "{ … }" block has that block split off
 * from its title and reported separately.
 */
final class ScanHeadings {

  /**
   * @param string $content
   *
   * @return \AKlump\Directio\Model\Heading[] In document order.
   */
  public function __invoke(string $content): array {
    $headings = [];

    /**
     * @var array{text: string, offset: int, isParagraph: bool}|null $prevLine
     *   The most recent line that was neither a heading nor inside a fence,
     *   used to test whether the current line is a setext underline for it.
     */
    $prevLine = NULL;

    foreach ((new ScanLines())($content) as $line) {
      if ($line['inFence']) {
        $prevLine = NULL;
        continue;
      }

      $atx = $this->matchAtxHeading($line['text']);
      if (NULL !== $atx) {
        $headings[] = new Heading(
          $atx['level'],
          $atx['title'],
          $line['offset'],
          $line['offset'] + strlen($line['raw']),
          $atx['block'],
          NULL === $atx['block'] ? NULL : $line['offset'] + $atx['blockOffsetInLine'],
        );
        $prevLine = NULL;
        continue;
      }

      $setext = $this->matchSetextHeading($line['text'], $prevLine);
      if (NULL !== $setext) {
        $headings[] = new Heading(
          $setext['level'],
          $setext['title'],
          $prevLine['offset'],
          $line['offset'] + strlen($line['raw']),
          $setext['block'],
          NULL === $setext['block'] ? NULL : $prevLine['offset'] + $setext['blockOffsetInLine'],
        );
        $prevLine = NULL;
        continue;
      }

      $prevLine = [
        'text' => $line['text'],
        'offset' => $line['offset'],
        'isParagraph' => $this->isParagraphText($line['text']),
      ];
    }

    return $headings;
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
   *
   * @return array{level: int, title: string, block: string|null, blockOffsetInLine: int|null}|null
   *   `blockOffsetInLine` is relative to $prevLine, the title line.
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
   * @param string $rest The heading text after any leading "#" marker (ATX)
   *   or the whole title line (setext).
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
   * @param string $line A line with no trailing line-ending characters.
   */
  private function isParagraphText(string $line): bool {
    if ('' === trim($line)) {
      return FALSE;
    }

    if (preg_match('/^[ ]{0,3}(?:[-*+][ \t]+|\d{1,9}[.)][ \t]+)/', $line)) {
      return FALSE;
    }

    if (preg_match('/^[ ]{0,3}[<>]/', $line)) {
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
