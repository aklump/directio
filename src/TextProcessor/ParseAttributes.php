<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\TextProcessor;

use AKlump\Directio\Exception\InvalidAttributesException;

/**
 * Parses a heading's "{ … }" attribute block into an attribute array.
 *
 * Items inside the block are whitespace-separated. Each item is one of:
 * - "#name": shorthand for the id, stored under the "id" key;
 * - "name=value": an unquoted value, which may not contain whitespace,
 *   `"`, `{`, or `}`;
 * - `name="quoted value"`: a value that may contain whitespace;
 * - a bare "name": a boolean flag.
 *
 * A name matches `[A-Za-z_][A-Za-z0-9_-]*`. Names keep their case, a later
 * duplicate key wins, and item order is free. Anything else — a
 * single-quoted value, "name = value" with spaces around "=", a stray `"`,
 * or an unrecognized item such as a Pandoc `.class` — raises
 * {@see InvalidAttributesException} naming the offending text.
 */
class ParseAttributes {

  /**
   * @param string $block The full "{ … }" block, braces included, e.g.
   *   '{#foo redo="P1M"}'.
   *
   * @return array<string, string|true>
   */
  public function __invoke(string $block): array {
    $inner = $this->stripBraces($block);
    $attributes = [];
    $length = strlen($inner);
    $pos = 0;

    while ($pos < $length) {
      $pos = $this->skipWhitespace($inner, $pos, $length);
      if ($pos >= $length) {
        break;
      }

      $char = $inner[$pos];

      if ('#' === $char) {
        $start = ++$pos;
        $pos = $this->consumeNameChars($inner, $pos, $length);
        $name = substr($inner, $start, $pos - $start);
        if ('' === $name) {
          throw new InvalidAttributesException(sprintf('Empty "#" id shorthand in attribute block: "%s"', $block));
        }
        $attributes['id'] = $name;
      }
      elseif ($this->isNameStartChar($char)) {
        $start = $pos;
        $pos = $this->consumeNameChars($inner, $pos, $length);
        $name = substr($inner, $start, $pos - $start);

        if ($pos < $length && '=' === $inner[$pos]) {
          $pos++;
          if ($pos < $length && '\'' === $inner[$pos]) {
            throw new InvalidAttributesException(sprintf('Single-quoted values are not supported: "%s"', $block));
          }
          if ($pos < $length && '"' === $inner[$pos]) {
            $pos++;
            $valueStart = $pos;
            while ($pos < $length && '"' !== $inner[$pos]) {
              $pos++;
            }
            if ($pos >= $length) {
              throw new InvalidAttributesException(sprintf('Unterminated quoted value in attribute block: "%s"', $block));
            }
            $value = substr($inner, $valueStart, $pos - $valueStart);
            $pos++;
          }
          else {
            $valueStart = $pos;
            while ($pos < $length && !ctype_space($inner[$pos]) && '"' !== $inner[$pos] && '{' !== $inner[$pos] && '}' !== $inner[$pos]) {
              $pos++;
            }
            $value = substr($inner, $valueStart, $pos - $valueStart);
            if ('' === $value) {
              throw new InvalidAttributesException(sprintf('Empty value for "%s" in attribute block: "%s"', $name, $block));
            }
          }
          $attributes[$name] = $value;
        }
        else {
          $attributes[$name] = TRUE;
        }
      }
      else {
        throw new InvalidAttributesException(sprintf('Unrecognized attribute syntax at "%s" in: "%s"', substr($inner, $pos), $block));
      }

      if ($pos < $length && !ctype_space($inner[$pos])) {
        throw new InvalidAttributesException(sprintf('Unrecognized attribute syntax at "%s" in: "%s"', substr($inner, $pos), $block));
      }
    }

    return $attributes;
  }

  private function stripBraces(string $block): string {
    $trimmed = trim($block);
    if (strlen($trimmed) < 2 || '{' !== $trimmed[0] || '}' !== substr($trimmed, -1)) {
      throw new InvalidAttributesException(sprintf('Malformed attribute block: "%s"', $block));
    }

    return substr($trimmed, 1, -1);
  }

  private function skipWhitespace(string $inner, int $pos, int $length): int {
    while ($pos < $length && ctype_space($inner[$pos])) {
      $pos++;
    }

    return $pos;
  }

  private function consumeNameChars(string $inner, int $pos, int $length): int {
    while ($pos < $length && $this->isNameChar($inner[$pos])) {
      $pos++;
    }

    return $pos;
  }

  private function isNameStartChar(string $char): bool {
    return 1 === preg_match('/[A-Za-z_]/', $char);
  }

  private function isNameChar(string $char): bool {
    return 1 === preg_match('/[A-Za-z0-9_-]/', $char);
  }

}
