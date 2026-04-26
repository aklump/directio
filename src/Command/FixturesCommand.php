<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\Config\Names;
use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\Directio\FixtureFramework\Runtime\FixtureInstantiator;
use AKlump\Directio\IO\GetLogsDirectory;
use AKlump\Directio\IO\GetShortPath;
use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\Lexer\TaskLexer;
use AKlump\Directio\TextProcessor\ParseAttributes;
use AKlump\FixtureFramework\Discovery\DiscoverFixtureDefinitions;
use AKlump\FixtureFramework\Runtime\FixtureCollectionBuilder;
use AKlump\FixtureFramework\Runtime\FixtureRunner;
use AKlump\Directio\Config\SpecialAttributes;
use AKlump\FixtureFramework\Runtime\RunOptions;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class FixturesCommand extends Command {

  use InitializedDirCommandTrait;

  protected static $defaultName = 'fixtures';

  protected static $defaultDescription = 'Run fixtures defined in document tags';

  protected function configure() {
    $this->addOption('filter', NULL, InputOption::VALUE_REQUIRED, 'Filter fixtures by ID.');
    $this->addOption('flush', NULL, InputOption::VALUE_NONE, 'Flush the fixture cache.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $base_dir = $this->getBaseDirOrInitializeCurrent($input, $output);
    if (!$base_dir) {
      return Command::FAILURE;
    }
    $directio_directory = $base_dir . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;
    if (!$this->validateInitialized($directio_directory, $output)) {
      return Command::FAILURE;
    }

    $total_found = 0;
    $fixture_mappings = $this->scanForFixtureMappings($directio_directory, $output, $total_found);
    if ($fixture_mappings === NULL) {
      return Command::FAILURE;
    }

    if (empty($fixture_mappings)) {
      if ($total_found > 0) {
        $output->writeln('<info>All fixtures have been marked as done. Nothing more to do.</info>');
      }
      else {
        $output->writeln('<info>No fixtures found in documents.</info>');
      }

      return Command::SUCCESS;
    }

    $fixture_ids = array_keys($fixture_mappings);
    $filter = $input->getOption('filter') ?: '';
    $flush = $input->getOption('flush');

    return $this->runFixtures($base_dir, $directio_directory, $fixture_ids, $fixture_mappings, $input, $output, $filter, $flush);
  }

  private function validateInitialized(string $directio_directory, OutputInterface $output): bool {
    if (!file_exists($directio_directory)) {
      $output->writeln('<error>Current directory is not initialized.</error>');
      $output->writeln(sprintf('<info>Try the "%s" command first.</info>', InitializeCommand::getDefaultName()));

      return FALSE;
    }

    return TRUE;
  }

  private function scanForFixtureMappings(string $directio_directory, OutputInterface $output, int &$total_fixtures_found = 0): ?array {
    $files_to_scan = glob($directio_directory . DIRECTORY_SEPARATOR . Names::FILENAME_IMPORTED . DIRECTORY_SEPARATOR . '*');
    $shortpath_directio = (new GetShortPath())($directio_directory);
    if (empty($files_to_scan)) {
      $output->writeln(sprintf('<error>No documents in "%s"</error>', $shortpath_directio));
      $output->writeln(sprintf('<info>Try the "%s" command first.</info>', ImportCommand::getDefaultName()));

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

  private function runFixtures(string $project_directory, string $directio_directory, array $fixture_ids, array $fixture_mappings, InputInterface $input, OutputInterface $output, string $filter = '', bool $flush = FALSE): int {
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

      if (empty($definitions)) {
        $output->writeln('<info>No fixture definitions found for the referenced IDs.</info>');

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

        /** @var $logs_directory string File path to the directory where logs are to be stored. */
        'logs_directory' => (new GetLogsDirectory($directio_directory))(),
      ]);
      // </snippet>

      $instantiator = new FixtureInstantiator($options, $input, $output);

      $fixtures = (new FixtureCollectionBuilder($instantiator))($definitions);

      $skipped_count = 0;

      // Because we want a confirmation of each one, we will itereate on the
      // array rather than letting the fixture runner do so.
      while (($fixture = array_shift($fixtures)) !== NULL) {
        if (($description = $fixture->description())) {
          $output->writeln(sprintf('<info>%s</info>', $description));
        }

        // We can only be sure io() exists on fixtures that extend
        // AbstractFixture.  Therefor the skip option will only apply to those.
        if ($fixture instanceof AbstractFixture && !$fixture->io()
            ->confirm(sprintf('Run fixture "%s"?', $fixture->id()))) {
          $output->writeln('<info>Fixture skipped.</info>');
          ++$skipped_count;
          continue;
        }

        $runner = new FixtureRunner([$fixture]);
        $runner->run(FALSE, $project_directory);
      }

      if ($skipped_count > 0) {
        $output->writeln('<info>Fixtures completed successfully or skipped.</info>');
      }
      else {
        $output->writeln('<info>Fixtures completed successfully.</info>');
      }
    }
    catch (Exception $e) {
      $output->writeln(sprintf('<error>Error running fixtures: %s</error>', $e->getMessage()));

      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

}
