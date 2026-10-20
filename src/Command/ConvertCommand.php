<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\Config\SpecialAttributes;
use AKlump\Directio\IO\GetShortPath;
use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\IO\WriteDocument;
use AKlump\Directio\Model\Attributes;
use AKlump\Directio\Model\Document;
use AKlump\Directio\TextProcessor\ValidateTaskSyntax;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rewrites a document from the retired `<directio>…</directio>` element
 * markup onto the heading `{ … }` attribute-block syntax, in place.
 *
 * This command owns its own thin, read-only reader for the old markup.
 * `TaskLexer` and the old `ParseAttributes` no longer exist (Phase 7
 * retired them along with `doctrine/lexer`), and nothing else in the
 * codebase needs to read the old syntax, so that reader lives here only,
 * scoped to this command.
 */
#[AsCommand(name: 'convert', description: 'Convert a document from <directio> markup to the heading { … } syntax')]
class ConvertCommand extends Command {

  protected static $defaultName = 'convert';

  protected static $defaultDescription = 'Convert a document from <directio> markup to the heading { … } syntax';

  private const OPEN_TAG = '<directio';

  private const CLOSE_TAG = '</directio>';

  private InputInterface $input;

  private OutputInterface $output;

  private SymfonyStyle $io;

  private function io(): SymfonyStyle {
    return $this->io ??= new SymfonyStyle($this->input, $this->output);
  }

  protected function configure() {
    $this->addArgument('file', InputArgument::REQUIRED, 'The document to convert.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;

    $path = $input->getArgument('file');
    if (!is_file($path)) {
      $this->io()->error(sprintf('File does not exist: %s', $path));

      return Command::FAILURE;
    }
    $shortpath = (new GetShortPath())($path);

    $document = (new ReadDocument())($path);
    $result = $this->convert($document->getContent());

    if ($result['noop']) {
      $this->io()
        ->info(sprintf('%s has no "<directio" markup; nothing to convert.', $shortpath));

      return Command::SUCCESS;
    }

    if (!empty($result['refusals'])) {
      $this->io()
        ->error(sprintf('Refusing to convert %s: a guess here would change what a later cut deletes.', $shortpath));
      foreach ($result['refusals'] as $refusal) {
        $this->io()->writeln(sprintf('  Line %d: %s', $refusal['line'], $refusal['reason']));
      }

      return Command::FAILURE;
    }

    $this->io()->writeln(sprintf(
      'Found %d task(s) to convert in %s (%d invented heading(s)).',
      $result['taskCount'],
      $shortpath,
      $result['invented']
    ));

    // The converter's own output must itself be valid under the new
    // syntax; a failure here is a bug in this command, not bad input.
    (new ValidateTaskSyntax())($result['content']);

    if (!$this->io()
      ->confirm(sprintf('Overwrite "%s" with the converted content?', $shortpath), FALSE)) {
      $this->io()->writeln('Conversion cancelled.');

      return Command::FAILURE;
    }

    $converted_document = (new Document())->setContent($result['content']);
    (new WriteDocument())($path, $converted_document);
    $this->io()->success(sprintf('Converted %s', $shortpath));

    return Command::SUCCESS;
  }

  /**
   * @param string $content The original document content.
   *
   * @return array{
   *   content: string|null,
   *   refusals: array<array{line: int, reason: string}>,
   *   taskCount: int,
   *   invented: int,
   *   noop: bool,
   * }
   */
  private function convert(string $content): array {
    $legacy_tasks = $this->findLegacyTasks($content);
    if (empty($legacy_tasks)) {
      return [
        'content' => NULL,
        'refusals' => [],
        'taskCount' => 0,
        'invented' => 0,
        'noop' => TRUE,
      ];
    }

    $headings = $this->scanAtxHeadings($content);

    // "Nearest heading above", used only to level an invented heading,
    // is computed from headings that sit OUTSIDE every legacy task's own
    // span — a genuine document-structure heading, never another task's
    // own (promoted or invented) heading. Otherwise two sibling tasks with
    // nothing but blank lines between them (the common case: a heading-
    // first task immediately followed by a headless one) would nest the
    // second one inside the first's new heading, which Q5 forbids.
    $outside_headings = array_values(array_filter(
      $headings,
      function (array $h) use ($legacy_tasks): bool {
        foreach ($legacy_tasks as $task) {
          if ($h['position'] >= $task['openStart'] && $h['position'] < $task['closeEnd']) {
            return FALSE;
          }
        }

        return TRUE;
      }
    ));

    $refusals = [];
    $replacements = [];
    $invented = 0;
    $heading_index = 0;
    $cursor_level = 0;

    foreach ($legacy_tasks as $i => $task) {
      while ($heading_index < count($outside_headings) && $outside_headings[$heading_index]['position'] < $task['openStart']) {
        $cursor_level = $outside_headings[$heading_index]['level'];
        $heading_index++;
      }
      $nearest_above_level = $cursor_level;

      $body = substr($content, $task['openEnd'], $task['closeStart'] - $task['openEnd']);

      $body_headings = array_values(array_filter(
        $headings,
        fn(array $h) => $h['position'] >= $task['openEnd'] && $h['position'] < $task['closeStart']
      ));
      $body_heading_levels = array_column($body_headings, 'level');

      $block = '{' . (string) new Attributes($task['attributes']) . '}';

      $first_line_offset = $this->firstNonBlankLineOffset($body);
      $is_case_1 = FALSE;
      $heading_level = NULL;
      $heading_title = NULL;
      $first_line = '';

      if (NULL !== $first_line_offset) {
        $first_line = $this->lineAt($body, $first_line_offset);
        $atx = $this->matchAtxHeadingLine(rtrim($first_line, "\r\n"));
        if (NULL !== $atx) {
          $heading_absolute_start = $task['openEnd'] + $first_line_offset;
          $has_sibling_conflict = FALSE;
          foreach ($body_headings as $h) {
            if ($h['position'] > $heading_absolute_start && $h['level'] <= $atx['level']) {
              $has_sibling_conflict = TRUE;
              break;
            }
          }
          if (!$has_sibling_conflict) {
            $is_case_1 = TRUE;
            $heading_level = $atx['level'];
            $heading_title = $atx['title'];
          }
        }
      }

      if (!$is_case_1) {
        $level = $nearest_above_level + 1;
        if (!empty($body_heading_levels)) {
          $level = min($level, min($body_heading_levels) - 1);
        }
        $level = max(1, min($level, 6));

        $heading_level = $level;
        $heading_title = $this->humanize(SpecialAttributes::extractId($task['attributes']) ?? '');
        $invented++;
      }

      $new_heading_line = str_repeat('#', $heading_level) . ' ' . trim($heading_title) . ' ' . $block;

      if ($is_case_1) {
        $rest = substr($body, $first_line_offset + strlen($first_line));
        $rest_trimmed = rtrim($rest);
        $new_body = $new_heading_line . "\n";
        if ('' !== $rest_trimmed) {
          $new_body .= $rest_trimmed . "\n";
        }
      }
      else {
        $body_trimmed = trim($body);
        $new_body = $new_heading_line . "\n\n";
        if ('' !== $body_trimmed) {
          $new_body .= $body_trimmed . "\n";
        }
      }

      $replacements[] = [
        'start' => $task['openStart'],
        'end' => $task['closeEnd'],
        'text' => $new_body,
      ];

      // Case 3: non-blank content between this task's close tag and the
      // next structural boundary (the next task's open tag, or the next
      // real heading, whichever comes first) would silently become part
      // of this task's body once the close tag is gone.
      $next_task_start = $legacy_tasks[$i + 1]['openStart'] ?? strlen($content);
      $next_heading_position = strlen($content);
      foreach ($headings as $h) {
        if ($h['position'] >= $task['closeEnd']) {
          $next_heading_position = $h['position'];
          break;
        }
      }
      $boundary = min($next_task_start, $next_heading_position);
      $gap = substr($content, $task['closeEnd'], $boundary - $task['closeEnd']);
      if ('' !== trim($gap)) {
        $pos = $task['closeEnd'];
        for ($k = 0; $k < strlen($gap); $k++) {
          if (!ctype_space($gap[$k])) {
            $pos = $task['closeEnd'] + $k;
            break;
          }
        }
        $line = substr_count($content, "\n", 0, $pos) + 1;
        $refusals[] = [
          'line' => $line,
          'reason' => sprintf(
            'Content follows the close tag for "%s" before the next heading.',
            SpecialAttributes::extractId($task['attributes']) ?? '(no id)'
          ),
        ];
      }
    }

    if (!empty($refusals)) {
      return [
        'content' => NULL,
        'refusals' => $refusals,
        'taskCount' => count($legacy_tasks),
        'invented' => $invented,
        'noop' => FALSE,
      ];
    }

    $converted = $content;
    foreach (array_reverse($replacements) as $replacement) {
      $converted = substr_replace($converted, $replacement['text'], $replacement['start'], $replacement['end'] - $replacement['start']);
    }

    return [
      'content' => $converted,
      'refusals' => [],
      'taskCount' => count($legacy_tasks),
      'invented' => $invented,
      'noop' => FALSE,
    ];
  }

  /**
   * Finds every `<directio …>…</directio>` task in $content, in document
   * order. Tasks are assumed not to nest or overlap, which matches every
   * surveyed document.
   *
   * @return array<array{
   *   openStart: int,
   *   openEnd: int,
   *   closeStart: int,
   *   closeEnd: int,
   *   attributes: array<string, string|true>,
   * }>
   */
  private function findLegacyTasks(string $content): array {
    $tasks = [];
    $cursor = 0;
    $length = strlen($content);

    while ($cursor < $length) {
      $open_start = stripos($content, self::OPEN_TAG, $cursor);
      if (FALSE === $open_start) {
        break;
      }

      $remainder = substr($content, $open_start);
      if (!preg_match('/^<directio(?:[\s][^>]*)?>/i', $remainder, $tag_match)) {
        // Not a real open tag (e.g. "<directiofoo>"); keep scanning.
        $cursor = $open_start + strlen(self::OPEN_TAG);
        continue;
      }
      $open_tag_text = $tag_match[0];
      $open_end = $open_start + strlen($open_tag_text);

      $close_start = stripos($content, self::CLOSE_TAG, $open_end);
      if (FALSE === $close_start) {
        throw new RuntimeException(sprintf(
          'Unmatched "%s" with no closing "%s" found (starting at byte offset %d).',
          self::OPEN_TAG,
          self::CLOSE_TAG,
          $open_start
        ));
      }
      $close_end = $close_start + strlen(self::CLOSE_TAG);

      $tag_inner = substr($open_tag_text, strlen(self::OPEN_TAG), -1);
      $attributes = $this->parseLegacyAttributes($tag_inner);

      $tasks[] = [
        'openStart' => $open_start,
        'openEnd' => $open_end,
        'closeStart' => $close_start,
        'closeEnd' => $close_end,
        'attributes' => $attributes,
      ];

      $cursor = $close_end;
    }

    return $tasks;
  }

  /**
   * Parses a legacy open tag's inner text (between "<directio" and ">")
   * into an attribute array: `name="value"` pairs and bare flags, matching
   * every surveyed document (every attribute is double-quoted there).
   *
   * @return array<string, string|true>
   */
  private function parseLegacyAttributes(string $inner): array {
    $attributes = [];
    preg_match_all(
      '/([A-Za-z_][A-Za-z0-9_-]*)\s*=\s*"([^"]*)"|([A-Za-z_][A-Za-z0-9_-]*)/',
      $inner,
      $matches,
      PREG_SET_ORDER
    );
    foreach ($matches as $match) {
      if ('' !== ($match[1] ?? '')) {
        $attributes[$match[1]] = $match[2];
      }
      elseif ('' !== ($match[3] ?? '')) {
        $attributes[$match[3]] = TRUE;
      }
    }

    return $attributes;
  }

  /**
   * Scans $content for every ATX heading (fence-aware), regardless of
   * whether it carries a "{ … }" block, used to compute an invented
   * heading's level and to find case 3's "next heading" boundary.
   *
   * @return array<array{position: int, level: int}>
   */
  private function scanAtxHeadings(string $content): array {
    $headings = [];
    $fence = NULL;
    $offset = 0;

    foreach ($this->splitLines($content) as $line) {
      $line_start = $offset;
      $offset += strlen($line);
      $trimmed = rtrim($line, "\r\n");

      if (NULL !== $fence) {
        if ($this->closesFence($trimmed, $fence)) {
          $fence = NULL;
        }
        continue;
      }

      $opened_fence = $this->matchFenceOpen($trimmed);
      if (NULL !== $opened_fence) {
        $fence = $opened_fence;
        continue;
      }

      $atx = $this->matchAtxHeadingLine($trimmed);
      if (NULL !== $atx) {
        $headings[] = ['position' => $line_start, 'level' => $atx['level']];
      }
    }

    return $headings;
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

  /**
   * @param string $line A line with no trailing line-ending characters.
   *
   * @return array{level: int, title: string}|null
   */
  private function matchAtxHeadingLine(string $line): ?array {
    if (!preg_match('/^[ ]{0,3}(#{1,6})(?:[ \t]+(.*))?$/', $line, $m)) {
      return NULL;
    }

    $level = strlen($m[1]);
    $rest = $m[2] ?? '';
    if (preg_match('/^(.*?)[ \t]+#+[ \t]*$/', $rest, $closing)) {
      $rest = $closing[1];
    }
    else {
      $rest = rtrim($rest, " \t");
    }

    return ['level' => $level, 'title' => $rest];
  }

  /**
   * @return int|null The byte offset, relative to $text, of the start of
   *   the first line that isn't blank; NULL if $text is entirely blank.
   */
  private function firstNonBlankLineOffset(string $text): ?int {
    $offset = 0;
    foreach ($this->splitLines($text) as $line) {
      if ('' !== trim($line)) {
        return $offset;
      }
      $offset += strlen($line);
    }

    return NULL;
  }

  /**
   * @return string The line (terminator included, if any) starting at
   *   byte offset $offset within $text.
   */
  private function lineAt(string $text, int $offset): string {
    $lines = $this->splitLines(substr($text, $offset));

    return $lines[0] ?? '';
  }

  /**
   * @return string[] Each line of $content, with its line ending (if any)
   *   still attached.
   */
  private function splitLines(string $content): array {
    if ('' === $content) {
      return [];
    }

    return preg_split('/(?<=\n)/', $content);
  }

  /**
   * Humanizes a task id into an invented heading's title, e.g.
   * "bump_unbuild" -> "Bump unbuild".
   */
  private function humanize(string $id): string {
    $title = str_replace(['_', '-'], ' ', $id);
    $title = trim($title);
    if ('' === $title) {
      return $title;
    }

    return ucfirst($title);
  }

}
