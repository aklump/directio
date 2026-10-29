<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\Config\Names;
use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\Directio\FixtureFramework\Runtime\FixtureInstantiator;
use AKlump\Directio\IO\GetCacheDirectory;
use AKlump\Directio\IO\GetLogsDirectory;
use AKlump\Directio\IO\GetShortPath;
use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\TextProcessor\ParseTasks;
use AKlump\Directio\TextProcessor\ValidateTaskSyntax;
use AKlump\FixtureFramework\Discovery\DiscoverFixtureDefinitions;
use AKlump\FixtureFramework\Runtime\FixtureCollectionBuilder;
use AKlump\FixtureFramework\Runtime\FixtureRunner;
use AKlump\Directio\Config\SpecialAttributes;
use AKlump\FixtureFramework\Runtime\RunContextStoreSqLite;
use AKlump\FixtureFramework\Runtime\RunOptions;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'fixtures', description: 'Run fixtures defined in document tags')]
class FixturesCommand extends Command {

  use InitializedDirCommandTrait;

  protected static $defaultName = 'fixtures';

  protected static $defaultDescription = 'Run fixtures defined in document tags';

  private InputInterface $input;

  private OutputInterface $output;

  private SymfonyStyle $io;

  private function io(): SymfonyStyle {
    return $this->io ??= new SymfonyStyle($this->input, $this->output);
  }

  protected function configure() {
    $this->setAliases(['do']);
    $this->addArgument('filter', InputArgument::OPTIONAL, 'Filter fixtures by ID.');
    $this->addOption('filter', NULL, InputOption::VALUE_REQUIRED, 'Filter fixtures by ID.');
    $this->addOption('flush', NULL, InputOption::VALUE_NONE, 'Flush the fixture cache.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
    $base_dir = $this->getBaseDirOrInitializeCurrent($input, $output);
    if (!$base_dir) {
      return Command::FAILURE;
    }
    $directio_directory = $base_dir . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;
    if (!$this->validateInitialized($directio_directory)) {
      return Command::FAILURE;
    }

    $total_found = 0;
    $done_mappings = [];
    try {
      $fixture_mappings = $this->scanForFixtureMappings($directio_directory, $total_found, $done_mappings);
    }
    catch (Exception $exception) {
      $this->io()->error($exception->getMessage());

      return Command::FAILURE;
    }
    if ($fixture_mappings === NULL) {
      return Command::FAILURE;
    }

    $filter = $input->getArgument('filter') ?: ($input->getOption('filter') ?: '');

    // A filtered run continues so it can name the done task it skipped.
    if (empty($fixture_mappings) && ($filter === '' || $total_found === 0)) {
      if ($total_found > 0) {
        $this->io()
          ->info('All fixtures have been marked as done. Nothing more to do.');
      }
      else {
        $this->io()->info('No fixtures found in documents.');
      }

      return Command::SUCCESS;
    }

    $fixture_ids = array_keys($fixture_mappings);
    $flush = $input->getOption('flush');

    return $this->runFixtures($base_dir, $directio_directory, $fixture_ids, $fixture_mappings, $filter, $flush, $done_mappings);
  }

  private function validateInitialized(string $directio_directory): bool {
    if (!file_exists($directio_directory)) {
      $this->io()->error('Current directory is not initialized.');
      $this->io()
        ->info(sprintf('Try the "%s" command first.', InitializeCommand::getDefaultName()));

      return FALSE;
    }

    return TRUE;
  }

  /**
   * @param array $done_mappings Receives the tasks already marked done, keyed
   * by fixture ID, so a filtered run can say why a fixture didn't run.
   */
  private function scanForFixtureMappings(string $directio_directory, int &$total_fixtures_found = 0, array &$done_mappings = []): ?array {
    $files_to_scan = glob($directio_directory . DIRECTORY_SEPARATOR . Names::FILENAME_IMPORTED . DIRECTORY_SEPARATOR . '*');
    $shortpath_directio = (new GetShortPath())($directio_directory);
    if (empty($files_to_scan)) {
      $this->io()->error(sprintf('No documents in "%s"', $shortpath_directio));
      $this->io()
        ->info(sprintf('Try the "%s" command first.', ImportCommand::getDefaultName()));

      return NULL;
    }

    $mappings = [];
    $total_fixtures_found = 0;
    foreach ($files_to_scan as $document_path) {
      $document = (new ReadDocument())($document_path);
      // Never silently skip a bad attribute block here (Q4): a document
      // that fails to parse is refused before any fixture runs.
      (new ValidateTaskSyntax())($document->getContent());
      foreach ((new ParseTasks())($document->getContent()) as $task) {
        $attributes = $task->getAttributes();
        $is_done = SpecialAttributes::extractDone($attributes);
        $fixture_id = SpecialAttributes::extractFixture($attributes);
        if ($fixture_id) {
          $total_fixtures_found++;
          $task_id = SpecialAttributes::extractId($attributes);
          if (!$task_id) {
            continue;
          }
          if ($is_done) {
            $done_mappings[$fixture_id][] = [
              'path' => $document_path,
              'id' => $task_id,
            ];
          }
          else {
            $mappings[$fixture_id][] = [
              'path' => $document_path,
              'id' => $task_id,
              'attributes' => $attributes,
            ];
          }
        }
      }
    }

    return $mappings;
  }

  private function runFixtures(string $project_directory, string $directio_directory, array $fixture_ids, array $fixture_mappings, string $filter = '', bool $flush = FALSE, array $done_mappings = []): int {
    // This command is already running under Directio's Composer autoloader.
    // Do not load the host project's vendor/autoload.php here; doing so can
    // register a second Composer dependency graph in the same PHP process.
    $vendor_dir = realpath($directio_directory) . DIRECTORY_SEPARATOR . 'vendor';
    if (FALSE === $vendor_dir) {
      throw new \RuntimeException('Unable to resolve Composer vendor directory. Try `directio init` to resolve.');
    }

    require_once $vendor_dir . '/autoload.php';

    $fixture_namespaces = [
      'AKlump\Directio\Fixture',
    ];

    try {
      $discover = new DiscoverFixtureDefinitions();
      $definitions = $discover($vendor_dir, $fixture_namespaces, $flush, FALSE, $filter);
      $matched_ids = array_column($definitions, 'id');

      // When a filter names a fixture whose tasks are all marked done, say so,
      // rather than leaving the user to think the fixture can't be found.
      if ($filter !== '') {
        $skipped_done = array_diff_key(array_intersect_key($done_mappings, array_flip($matched_ids)), $fixture_mappings);
        $this->reportFixturesMarkedDone($skipped_done);
      }

      // Filter definitions by the IDs found in documents
      $definitions = array_filter($definitions, function (array $def) use ($fixture_ids) {
        return in_array($def['id'], $fixture_ids);
      });
      foreach ($definitions as &$definition) {
        $definition['mappings'] = $fixture_mappings[$definition['id']] ?? [];
      }

      if (empty($definitions)) {
        $this->reportNothingToRun($filter, $fixture_ids, $matched_ids, $done_mappings);

        return Command::SUCCESS;
      }

      // <snippet id="fixtures_runtime_options">
      /**
       * @var \AKlump\FixtureFramework\Runtime\RunOptions $options These
       * options can be accessed using $this->options() in any fixture.
       */
      $options = new RunOptions([
        /** @var $directio_directory string File path to .directio/ */
        'directio_directory' => $directio_directory,

        /** @var $cache_directory string File path to the directory where cached files are to be stored. */
        'cache_directory' => (new GetCacheDirectory($directio_directory))(),

        /** @var $logs_directory string File path to the directory where logs are to be stored. */
        'logs_directory' => (new GetLogsDirectory($directio_directory))(),
      ]);
      // </snippet>

      $instantiator = new FixtureInstantiator($options, $this->input, $this->output);

      $context_store = new RunContextStoreSqLite($directio_directory . '/' . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE);
      $fixtures = (new FixtureCollectionBuilder($instantiator))($definitions, $context_store);

      $skipped_count = 0;

      // Because we want a confirmation of each one, we will itereate on the
      // array rather than letting the fixture runner do so.
      while (($fixture = array_shift($fixtures)) !== NULL) {
        if (($description = $fixture->description())) {
          $this->io()->title($description);
        }

        // We can only be sure io() exists on fixtures that extend
        // AbstractFixture.  Therefor the skip option will only apply to those.
        if ($fixture instanceof AbstractFixture && !$fixture->shouldRun()) {
          $this->io()->info('Fixture skipped.');
          ++$skipped_count;
          continue;
        }

        $runner = new FixtureRunner([$fixture]);
        $runner->run(FALSE, $project_directory);
      }

      if ($skipped_count > 0) {
        $this->io()->info('Fixtures completed successfully or skipped.');
      }
      else {
        $this->io()->info('Fixtures completed successfully.');
      }
    }
    catch (Exception $e) {
      $this->io()
        ->error(sprintf('Error running fixtures: %s', $e->getMessage()));

      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * @param array $skipped_done Done tasks keyed by fixture ID.
   */
  private function reportFixturesMarkedDone(array $skipped_done): void {
    if (empty($skipped_done)) {
      return;
    }
    $done_keys = array_map(fn(string $key) => sprintf('"%s"', $key), array_keys(SpecialAttributes::doneKeys()));
    $last_key = array_pop($done_keys);
    $done_keys_list = $done_keys ? implode(', ', $done_keys) . ' or ' . $last_key : $last_key;
    foreach ($skipped_done as $fixture_id => $tasks) {
      foreach ($tasks as $task) {
        $this->io()->info(sprintf(
          'Skipped fixture "%s": task "%s" is marked done in %s. Remove %s from its attributes to run it again.',
          $fixture_id,
          $task['id'],
          (new GetShortPath())($task['path']),
          $done_keys_list
        ));
      }
    }
  }

  /**
   * Explain why no fixture definition is left to run.
   */
  private function reportNothingToRun(string $filter, array $fixture_ids, array $matched_ids, array $done_mappings): void {
    if ($filter === '') {
      $this->io()->info(sprintf(
        'No fixture classes define the IDs used in documents: %s',
        implode(', ', $fixture_ids)
      ));

      return;
    }
    if (empty($matched_ids)) {
      $this->io()->info(sprintf('No fixture ID matches "%s".', $filter));

      return;
    }
    $unused_ids = array_diff($matched_ids, array_keys($done_mappings));
    if ($unused_ids) {
      $this->io()->info(sprintf(
        'No task in the imported documents uses fixture: %s',
        implode(', ', $unused_ids)
      ));
    }
  }

}
