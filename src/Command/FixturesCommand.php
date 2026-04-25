<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\Config\Names;
use AKlump\Directio\IO\GetLogsDirectory;
use AKlump\Directio\IO\GetShortPath;
use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\Lexer\TaskLexer;
use AKlump\Directio\TextProcessor\ParseAttributes;
use AKlump\FixtureFramework\Discovery\DiscoverFixtureDefinitions;
use AKlump\FixtureFramework\Runtime\FixtureCollectionBuilder;
use AKlump\FixtureFramework\Runtime\FixtureRunner;
use AKlump\FixtureFramework\Runtime\RunContextValidator;
use AKlump\Directio\Helper\MarkTaskDoneInDocument;
use AKlump\Directio\IO\WriteDocument;
use AKlump\Directio\Config\SpecialAttributes;
use AKlump\Directio\IO\WriteState;
use AKlump\Directio\Model\TaskState;
use AKlump\LocalTimezone\LocalTimezone;
use DateInterval;
use DateTimeInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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

    $fixture_mappings = $this->scanForFixtureMappings($directio_directory, $output);
    if ($fixture_mappings === NULL) {
      return Command::FAILURE;
    }

    if (empty($fixture_mappings)) {
      $output->writeln('<info>No fixtures found in documents.</info>');

      return Command::SUCCESS;
    }

    $fixture_ids = array_keys($fixture_mappings);
    $filter = $input->getOption('filter') ?: '';
    $flush = $input->getOption('flush');

    return $this->runFixtures($base_dir, $directio_directory, $fixture_ids, $fixture_mappings, $output, $filter, $flush);
  }

  private function validateInitialized(string $directio_directory, OutputInterface $output): bool {
    if (!file_exists($directio_directory)) {
      $output->writeln('<error>Current directory is not initialized.</error>');
      $output->writeln(sprintf('<info>Try the "%s" command first.</info>', InitializeCommand::getDefaultName()));

      return FALSE;
    }

    return TRUE;
  }

  private function scanForFixtureMappings(string $directio_directory, OutputInterface $output): ?array {
    $files_to_scan = glob($directio_directory . DIRECTORY_SEPARATOR . Names::FILENAME_IMPORTED . DIRECTORY_SEPARATOR . '*');
    $shortpath_directio = (new GetShortPath())($directio_directory);
    if (empty($files_to_scan)) {
      $output->writeln(sprintf('<error>No documents in "%s"</error>', $shortpath_directio));
      $output->writeln(sprintf('<info>Try the "%s" command first.</info>', ImportCommand::getDefaultName()));

      return NULL;
    }

    $mappings = [];
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
        if (!empty($attributes['fixture'])) {
          $fixture_id = $attributes['fixture'];
          $task_id = $attributes['id'] ?? NULL;
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

    return $mappings;
  }

  private function runFixtures(string $project_directory, string $directio_directory, array $fixture_ids, array $fixture_mappings, OutputInterface $output, string $filter = '', bool $flush = FALSE): int {
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

      // Sort definitions by the order they appear in $fixture_ids
      $ordered_definitions = [];
      foreach ($fixture_ids as $id) {
        foreach ($definitions as $def) {
          if ($def['id'] === $id) {
            $ordered_definitions[] = $def;
            break;
          }
        }
      }

      $options = [
        'directio_directory' => $directio_directory,
        'logs_directory' => (new GetLogsDirectory($directio_directory))(),
      ];
      $validator = new RunContextValidator();
      $fixtures = (new FixtureCollectionBuilder($options, $validator))($ordered_definitions);
      $runner = new FixtureRunner($fixtures);
      $runner->run(FALSE, $project_directory);

      // Mark fixtures as done in documents
      $mark_done = new MarkTaskDoneInDocument();
      $read_document = new ReadDocument();
      $write_document = new WriteDocument();
      $write_state = new WriteState();
      $state_path = $directio_directory . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE;
      $now = date_create('now', LocalTimezone::get());

      $messages = [];
      $get_short_path = new GetShortPath();
      foreach ($ordered_definitions as $def) {
        $fixture_id = $def['id'];
        if (isset($fixture_mappings[$fixture_id])) {
          foreach ($fixture_mappings[$fixture_id] as $mapping) {
            $path = $mapping['path'];
            $task_id = $mapping['id'];
            $document = $read_document($path);
            $document = $mark_done($task_id, $document);
            $write_document($path, $document);
            $messages[] = sprintf('Marked "%s" as done in %s', $task_id, $get_short_path($path));

            $task = (new TaskState())
              ->setId($task_id)
              ->setEnv(exec('echo "$(hostname)"'))
              ->setCompleted($now->format(DateTimeInterface::ATOM))
              ->setUser(exec('whoami'));

            $expires = array_intersect_key($mapping['attributes'], SpecialAttributes::expiresKeys());
            if ($expires) {
              $duration = new DateInterval($mapping['attributes'][key($expires)]);
              if ($duration) {
                $expiry = (clone $now)->add($duration);
              }
              $task->setRedo($expiry->format(DateTimeInterface::ATOM));
            }
            $write_state->writeOne($state_path, $task);
          }
        }
      }

      $output->writeln('<info>Fixtures completed successfully.</info>');
      foreach ($messages as $message) {
        $output->writeln(sprintf('<info>%s</info>', $message));
      }
    }
    catch (Exception $e) {
      $output->writeln(sprintf('<error>Error running fixtures: %s</error>', $e->getMessage()));

      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }
}
