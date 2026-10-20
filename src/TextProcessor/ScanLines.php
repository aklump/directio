<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\TextProcessor;

/**
 * Splits a document into lines and marks the ones inside a fenced code
 * block, so every reader agrees on what is and isn't inside a fence.
 *
 * A fence opens with three or more backticks or tildes (any leading
 * indent) and closes with a line of the same character at least as long;
 * an unclosed fence runs to the end of the document. Both delimiter lines
 * count as inside the fence.
 */
final class ScanLines {

  /**
   * @param string $content
   *
   * @return array<array{offset: int, raw: string, text: string, inFence: bool}>
   *   One entry per line, in order. `raw` keeps the line ending so the
   *   entries concatenate back to $content exactly; `text` has it removed.
   */
  public function __invoke(string $content): array {
    if ('' === $content) {
      return [];
    }

    $lines = [];
    $fence = NULL;
    $offset = 0;
    foreach (preg_split('/(?<=\n)/', $content, -1, PREG_SPLIT_NO_EMPTY) as $raw) {
      $text = rtrim($raw, "\r\n");
      $inFence = NULL !== $fence;
      if ($inFence) {
        if ($this->closesFence($text, $fence)) {
          $fence = NULL;
        }
      }
      else {
        $fence = $this->matchFenceOpen($text);
        $inFence = NULL !== $fence;
      }
      $lines[] = [
        'offset' => $offset,
        'raw' => $raw,
        'text' => $text,
        'inFence' => $inFence,
      ];
      $offset += strlen($raw);
    }

    return $lines;
  }

  /**
   * @return array{char: string, len: int}|null
   */
  private function matchFenceOpen(string $line): ?array {
    if (preg_match('/^[ \t]*(`{3,}|~{3,})/', $line, $m)) {
      return ['char' => $m[1][0], 'len' => strlen($m[1])];
    }

    return NULL;
  }

  /**
   * @param array{char: string, len: int} $fence
   */
  private function closesFence(string $line, array $fence): bool {
    $pattern = sprintf('/^[ \t]*%s{%d,}[ \t]*$/', preg_quote($fence['char'], '/'), $fence['len']);

    return 1 === preg_match($pattern, $line);
  }

}
