<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\Config\Names;
use AKlump\Directio\Config\SpecialAttributes;
use AKlump\Directio\IO\GetShortPath;
use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\IO\WriteDocument;
use AKlump\Directio\Model\Task;
use AKlump\Directio\TextProcessor\ParseTasks;
use AKlump\Directio\TextProcessor\ValidateTaskSyntax;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes every imported task that has none of the given attributes.
 *
 * Nothing is written to the state database; the removed tasks come back
 * the next time their source document is imported.
 */
#[AsCommand(name: 'only', description: 'Remove imported tasks that lack any of the given attributes')]
class OnlyCommand extends Command {

  use InitializedDirCommandTrait;

  protected static $defaultName = 'only';

  protected static $defaultDescription = 'Remove imported tasks that lack any of the given attributes';

  private const NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_-]*$/';

  private InputInterface $input;

  private OutputInterface $output;

  private SymfonyStyle $io;

  private function io(): SymfonyStyle {
    return $this->io ??= new SymfonyStyle($this->input, $this->output);
  }

  protected function configure() {
    $this
      ->setAliases(['with'])
      ->addArgument('attributes', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Keep tasks with any of these attributes: "name" (any value) or "name=value".');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;

    $filters = [];
    foreach ($input->getArgument('attributes') as $argument) {
      [$name, $value] = array_pad(explode('=', $argument, 2), 2, NULL);
      if (!preg_match(self::NAME_PATTERN, $name)) {
        $this->io()->error(sprintf('Not an attribute name: "%s"', $argument));

        return Command::FAILURE;
      }
      $filters[] = [$name, $value];
    }

    $base_dir = $this->getBaseDirOrInitializeCurrent($input, $output);
    if (!$base_dir) {
      return Command::FAILURE;
    }
    $directio_directory = $base_dir . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;
    $paths = glob($directio_directory . DIRECTORY_SEPARATOR . Names::FILENAME_IMPORTED . DIRECTORY_SEPARATOR . '*');
    if (empty($paths)) {
      $this->io()->error(sprintf('No documents in "%s"', (new GetShortPath())($directio_directory)));
      $this->io()
        ->info(sprintf('Try the "%s" command first.', ImportCommand::getDefaultName()));

      return Command::FAILURE;
    }

    $is_removed = fn(Task $task) => !$this->matchesAny($task->getAttributes(), $filters);

    // Read and check every document before writing any of them.
    $plan = [];
    $total_kept = 0;
    $total_removed = 0;
    foreach ($paths as $path) {
      $label = (new GetShortPath())($path);
      $document = (new ReadDocument())($path);
      try {
        (new ValidateTaskSyntax())($document->getContent());
      }
      catch (Exception $exception) {
        $this->io()->error(sprintf('%s: %s', $label, $exception->getMessage()));

        return Command::FAILURE;
      }
      $tasks = (new ParseTasks())($document->getContent());
      $removed = array_filter($tasks, $is_removed);
      $kept = count($tasks) - count($removed);
      $total_kept += $kept;
      $total_removed += count($removed);
      $plan[] = [$path, $label, $document, $removed, $kept];
    }

    $filter_list = implode(', ', $input->getArgument('attributes'));
    if ($total_kept === 0) {
      $this->io()
        ->error(sprintf('No imported task has %s; nothing was removed.', $filter_list));

      return Command::FAILURE;
    }
    if ($total_removed === 0) {
      $this->io()
        ->info(sprintf('Every imported task has %s; nothing to remove.', $filter_list));

      return Command::SUCCESS;
    }

    $unsaved_done = [];
    foreach ($plan as [, $label, , $removed, $kept]) {
      if (empty($removed)) {
        continue;
      }
      $this->io()
        ->writeln(sprintf('%s: keep %d, remove %d', $label, $kept, count($removed)));
      foreach ($removed as $task) {
        $id = SpecialAttributes::extractId($task->getAttributes());
        $this->io()->writeln('  ✂️ ' . $id);
        if (SpecialAttributes::extractDone($task->getAttributes())) {
          $unsaved_done[] = $id;
        }
      }
    }
    if ($unsaved_done) {
      $this->io()->warning(sprintf(
        'Marked done but not yet saved by "%s": %s. Removing them drops that completion; run "%s" first to keep it.',
        UpdateCommand::getDefaultName(),
        implode(', ', $unsaved_done),
        UpdateCommand::getDefaultName()
      ));
    }

    if ($input->isInteractive() && !$this->io()
        ->confirm(sprintf('Remove %d task(s)?', $total_removed), FALSE)) {
      $this->io()->writeln('Nothing was removed.');

      return Command::FAILURE;
    }

    foreach ($plan as [$path, , $document, $removed]) {
      if ($removed) {
        (new WriteDocument())($path, $document->withoutTasksMatching($is_removed));
      }
    }
    $this->io()->success(sprintf(
      'Removed %d task(s), kept %d. Import the source documents again to restore them.',
      $total_removed,
      $total_kept
    ));

    return Command::SUCCESS;
  }

  /**
   * @param array $attributes A task's attributes.
   * @param array $filters Pairs of [name, value]; a NULL value matches any.
   */
  private function matchesAny(array $attributes, array $filters): bool {
    foreach ($filters as [$name, $value]) {
      if (!array_key_exists($name, $attributes)) {
        continue;
      }
      if ($value === NULL || $attributes[$name] === $value) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
