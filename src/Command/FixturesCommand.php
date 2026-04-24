<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\Config\Names;
use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\Lexer\TaskLexer;
use AKlump\Directio\TextProcessor\ParseAttributes;
use AKlump\FixtureFramework\Discovery\DiscoverFixtureDefinitions;
use AKlump\FixtureFramework\Runtime\FixtureCollectionBuilder;
use AKlump\FixtureFramework\Runtime\FixtureRunner;
use AKlump\FixtureFramework\Runtime\RunContextValidator;
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

    $fixture_ids = $this->scanForFixtureIds($directio_directory, $output);
    if ($fixture_ids === NULL) {
      return Command::FAILURE;
    }

    if (empty($fixture_ids)) {
      $output->writeln('<info>No fixtures found in documents.</info>');

      return Command::SUCCESS;
    }

    $filter = $input->getOption('filter') ?: '';
    $flush = $input->getOption('flush');

    return $this->runFixtures($base_dir, $fixture_ids, $output, $filter, $flush);
  }

  private function validateInitialized(string $directio_directory, OutputInterface $output): bool {
    if (!file_exists($directio_directory)) {
      $output->writeln('<error>Current directory is not initialized.</error>');
      $output->writeln(sprintf('<info>Try the "%s" command first.</info>', InitializeCommand::getDefaultName()));

      return FALSE;
    }

    return TRUE;
  }

  private function scanForFixtureIds(string $directio_directory, OutputInterface $output): ?array {
    $files_to_scan = glob($directio_directory . DIRECTORY_SEPARATOR . Names::FILENAME_IMPORTED . DIRECTORY_SEPARATOR . '*');
    if (empty($files_to_scan)) {
      $output->writeln(sprintf('<error>No documents in "%s"</error>', $directio_directory));
      $output->writeln(sprintf('<info>Try the "%s" command first.</info>', ImportCommand::getDefaultName()));

      return NULL;
    }

    $fixture_ids = [];
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
          $fixture_ids[] = $attributes['fixture'];
        }
      }
    }

    return $fixture_ids;
  }

  private function runFixtures(string $base_dir, array $fixture_ids, OutputInterface $output, string $filter = '', bool $flush = FALSE): int {
    // Prepare fixture framework
    $vendor_dir = $base_dir . DIRECTORY_SEPARATOR . 'vendor';
    // If we're running from within the app directory in the package itself
    if (!file_exists($vendor_dir)) {
      $vendor_dir = dirname($base_dir, 2) . DIRECTORY_SEPARATOR . 'vendor';
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
        'env' => 'test',
      ];
      $validator = new RunContextValidator();
      $fixtures = (new FixtureCollectionBuilder($options, $validator))($ordered_definitions);
      $runner = new FixtureRunner($fixtures);
      $runner->run();
      $output->writeln('<info>Fixtures completed successfully.</info>');
    }
    catch (Exception $e) {
      $output->writeln(sprintf('<error>Error running fixtures: %s</error>', $e->getMessage()));

      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }
}
