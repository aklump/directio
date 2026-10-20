<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\Config\SpecialAttributes;
use AKlump\Directio\IO\GetShortPath;
use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\IO\WriteDocument;
use AKlump\Directio\Model\Attributes;
use AKlump\Directio\Model\Document;
use AKlump\Directio\Model\Heading;
use AKlump\Directio\TextProcessor\ScanHeadings;
use AKlump\Directio\TextProcessor\ScanLines;
use AKlump\Directio\TextProcessor\ValidateTaskSyntax;
use Exception;
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
 * This command owns the only reader of the old markup left in the
 * codebase; nothing else needs it.
 *
 * Under the new syntax a task's body runs to the next heading of the same
 * or a higher level, so the converter refuses — listing lines — wherever
 * the old close tag ended a task somewhere the new rule would not: content
 * after a close tag before such a heading, a headless task whose body has
 * a level-1 heading, or a task whose own heading is deeper than the task
 * before it. Every refusal is reported and nothing is written.
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

    try {
      $document = (new ReadDocument())($path);
      $result = $this->convert($document->getContent());
      if (NULL !== $result['content']) {
        (new ValidateTaskSyntax())($result['content']);
      }
    }
    catch (Exception $exception) {
      $this->io()->error(sprintf('Cannot convert %s: %s', $shortpath, $exception->getMessage()));
      $this->io()->writeln('Fix the source document and run convert again.');

      return Command::FAILURE;
    }

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

    $headings = (new ScanHeadings())($content);

    // "Nearest heading above", used to level an invented heading, is
    // computed from headings that sit OUTSIDE every legacy task's own span:
    // a genuine document-structure heading, never another task's own
    // (promoted or invented) heading. Otherwise two sibling tasks with
    // nothing but blank lines between them would nest the second inside
    // the first's new heading.
    $outside_headings = array_values(array_filter(
      $headings,
      function (Heading $h) use ($legacy_tasks): bool {
        foreach ($legacy_tasks as $task) {
          if ($h->getStart() >= $task['openStart'] && $h->getStart() < $task['closeEnd']) {
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
    // The previous converted task's heading level, while no outside
    // heading has closed it; the next task's heading may not go deeper.
    $open_task_level = NULL;

    foreach ($legacy_tasks as $i => $task) {
      while ($heading_index < count($outside_headings) && $outside_headings[$heading_index]->getStart() < $task['openStart']) {
        $cursor_level = $outside_headings[$heading_index]->getLevel();
        $open_task_level = NULL;
        $heading_index++;
      }

      $id = SpecialAttributes::extractId($task['attributes']) ?? '(no id)';
      $body = substr($content, $task['openEnd'], $task['closeStart'] - $task['openEnd']);
      $body_headings = array_values(array_filter(
        $headings,
        fn(Heading $h) => $h->getStart() >= $task['openEnd'] && $h->getStart() < $task['closeStart']
      ));
      $block = '{' . (string) new Attributes($task['attributes']) . '}';

      // Case 1: the body opens with a heading that no later body heading
      // of the same or a higher level would cut short.
      $own_heading = NULL;
      $first_line_offset = $this->firstNonBlankLineOffset($body);
      if (NULL !== $first_line_offset) {
        foreach ($body_headings as $h) {
          if ($h->getStart() === $task['openEnd'] + $first_line_offset) {
            $own_heading = $h;
            break;
          }
        }
      }
      if (NULL !== $own_heading) {
        foreach ($body_headings as $h) {
          if ($h->getStart() > $own_heading->getStart() && $h->getLevel() <= $own_heading->getLevel()) {
            $own_heading = NULL;
            break;
          }
        }
      }

      if (NULL !== $own_heading) {
        $level = $own_heading->getLevel();
        $title = $own_heading->getTitle();
        if (NULL !== $open_task_level && $level > $open_task_level) {
          $refusals[] = [
            'line' => $this->lineNumber($content, $own_heading->getStart()),
            'reason' => sprintf('The heading of "%s" is deeper than the task before it, so it would nest inside that task.', $id),
          ];
        }
        $rest = substr($content, $own_heading->getEnd(), $task['closeStart'] - $own_heading->getEnd());
      }
      else {
        $level = $cursor_level + 1;
        if (!empty($body_headings)) {
          $shallowest = min(array_map(fn(Heading $h) => $h->getLevel(), $body_headings));
          if ($shallowest <= 1) {
            $refusals[] = [
              'line' => $this->lineNumber($content, $task['openStart']),
              'reason' => sprintf('"%s" has no heading of its own and its body contains a level-1 heading, so none can be invented above it.', $id),
            ];
          }
          $level = min($level, $shallowest - 1);
        }
        if (NULL !== $open_task_level) {
          $level = min($level, $open_task_level);
        }
        $level = max(1, min($level, 6));
        $title = $this->humanize($id);
        $invented++;
        $rest = $body;
      }
      $open_task_level = $level;

      $new_body = str_repeat('#', $level) . ' ' . trim($title) . ' ' . $block . "\n";
      $rest = trim($rest);
      if ('' !== $rest) {
        $new_body .= "\n" . $rest . "\n";
      }
      $replacements[] = [
        'start' => $task['openStart'],
        'end' => $task['closeEnd'],
        'text' => $new_body,
      ];

      // Case 3: once the close tag is gone, everything up to the next
      // heading at this task's level or higher (or the next task's open
      // tag) becomes part of this task's body.
      $boundary = $legacy_tasks[$i + 1]['openStart'] ?? strlen($content);
      foreach ($headings as $h) {
        if ($h->getStart() >= $task['closeEnd'] && $h->getLevel() <= $level) {
          $boundary = min($boundary, $h->getStart());
          break;
        }
      }
      $gap = substr($content, $task['closeEnd'], $boundary - $task['closeEnd']);
      if ('' !== trim($gap)) {
        $pos = $task['closeEnd'] + strlen($gap) - strlen(ltrim($gap));
        $refusals[] = [
          'line' => $this->lineNumber($content, $pos),
          'reason' => sprintf('Content follows the close tag for "%s" before the next heading at its level.', $id),
        ];
      }
    }

    if (!empty($refusals)) {
      usort($refusals, fn(array $a, array $b) => $a['line'] <=> $b['line']);

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
   * Finds every `<directio …>…</directio>` task outside fenced code, in
   * document order. Tasks are assumed not to nest or overlap, which
   * matches every surveyed document.
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
    $fenced = [];
    foreach ((new ScanLines())($content) as $line) {
      if ($line['inFence']) {
        $fenced[] = [$line['offset'], $line['offset'] + strlen($line['raw'])];
      }
    }
    $in_fence = function (int $offset) use ($fenced): bool {
      foreach ($fenced as [$start, $end]) {
        if ($offset >= $start && $offset < $end) {
          return TRUE;
        }
      }

      return FALSE;
    };

    $tasks = [];
    $cursor = 0;
    $length = strlen($content);

    while ($cursor < $length) {
      $open_start = stripos($content, self::OPEN_TAG, $cursor);
      if (FALSE === $open_start) {
        break;
      }

      $remainder = substr($content, $open_start);
      if ($in_fence($open_start) || !preg_match('/^<directio(?:[\s][^>]*)?>/i', $remainder, $tag_match)) {
        $cursor = $open_start + strlen(self::OPEN_TAG);
        continue;
      }
      $open_tag_text = $tag_match[0];
      $open_end = $open_start + strlen($open_tag_text);

      $close_start = stripos($content, self::CLOSE_TAG, $open_end);
      if (FALSE === $close_start) {
        throw new RuntimeException(sprintf(
          'The "%s" tag on line %d has no closing "%s".',
          self::OPEN_TAG,
          $this->lineNumber($content, $open_start),
          self::CLOSE_TAG
        ));
      }
      $close_end = $close_start + strlen(self::CLOSE_TAG);

      $tag_inner = substr($open_tag_text, strlen(self::OPEN_TAG), -1);

      $tasks[] = [
        'openStart' => $open_start,
        'openEnd' => $open_end,
        'closeStart' => $close_start,
        'closeEnd' => $close_end,
        'attributes' => $this->parseLegacyAttributes($tag_inner),
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
   * @return int|null The byte offset, relative to $text, of the first line
   *   that isn't blank; NULL if $text is entirely blank.
   */
  private function firstNonBlankLineOffset(string $text): ?int {
    foreach ((new ScanLines())($text) as $line) {
      if ('' !== trim($line['text'])) {
        return $line['offset'];
      }
    }

    return NULL;
  }

  private function lineNumber(string $content, int $offset): int {
    return substr_count($content, "\n", 0, $offset) + 1;
  }

  /**
   * Humanizes a task id into an invented heading's title, e.g.
   * "bump_unbuild" -> "Bump unbuild".
   */
  private function humanize(string $id): string {
    return ucfirst(trim(str_replace(['_', '-'], ' ', $id)));
  }

}
