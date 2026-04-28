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
use AKlump\Directio\Lexer\TaskLexer;
use AKlump\Directio\TextProcessor\ParseAttributes;
use AKlump\FixtureFramework\Discovery\DiscoverFixtureDefinitions;
use AKlump\FixtureFramework\Runtime\FixtureCollectionBuilder;
use AKlump\FixtureFramework\Runtime\FixtureRunner;
use AKlump\Directio\Config\SpecialAttributes;
use AKlump\FixtureFramework\Runtime\RunContextStoreSqLite;
use AKlump\FixtureFramework\Runtime\RunOptions;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
    $fixture_mappings = $this->scanForFixtureMappings($directio_directory, $total_found);
    if ($fixture_mappings === NULL) {
      return Command::FAILURE;
    }

    if (empty($fixture_mappings)) {
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
    $filter = $input->getOption('filter') ?: '';
    $flush = $input->getOption('flush');

    return $this->runFixtures($base_dir, $directio_directory, $fixture_ids, $fixture_mappings, $filter, $flush);
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

  private function scanForFixtureMappings(string $directio_directory, int &$total_fixtures_found = 0): ?array {
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
    $parse_attributes = new ParseAttributes();
    foreach ($files_to_scan as $document_path) {
      $document = (new ReadDocument())($document_path);
      $lexer = new TaskLexer();
      $lexer->setInput($document->getContent());
      $lexer->moveNext();
      while (TRUE) {
        $lexer->skipUntil(TaskLexer::T_OPEN_TAG);
        if (NULL === $lexer->lookahead) {
          break;
        }
        $lexer->moveNext();
        $attributes = $parse_attributes($lexer->token->value);
        $is_done = SpecialAttributes::extractDone($attributes);
        $fixture_id = SpecialAttributes::extractFixture($attributes);
        if ($fixture_id) {
          $total_fixtures_found++;
          if (!$is_done) {
            $task_id = SpecialAttributes::extractId($attributes);
            if ($task_id) {
              $mappings[$fixture_id][] = [
                'path' => $document_path,
                'id' => $task_id,
                'attributes' => $attributes,
              ];
            }
          }
        }
      }
    }

    return $mappings;
  }

  private function runFixtures(string $project_directory, string $directio_directory, array $fixture_ids, array $fixture_mappings, string $filter = '', bool $flush = FALSE): int {
    // Prepare fixture framework
    $vendor_dir = $project_directory . DIRECTORY_SEPARATOR . 'vendor';
    // If we're running from within the app directory in the package itself
    if (!file_exists($vendor_dir)) {
      $vendor_dir = dirname($project_directory, 2) . DIRECTORY_SEPARATOR . 'vendor';
    }

    require_once $vendor_dir . '/autoload.php';

    $fixture_namespaces = [
      'AKlump\Directio\Fixture',
    ];

    try {
      $discover = new DiscoverFixtureDefinitions();
      $definitions = $discover($vendor_dir, $fixture_namespaces, $flush, FALSE, $filter);

      // Filter definitions by the IDs found in documents
      $definitions = array_filter($definitions, function (array $def) use ($fixture_ids) {
        return in_array($def['id'], $fixture_ids);
      });
      foreach ($definitions as &$definition) {
        $definition['mappings'] = $fixture_mappings[$definition['id']] ?? [];
      }

      if (empty($definitions)) {
        $this->io()
          ->info('No fixture definitions found for the referenced IDs.');

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

}
