<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\Config\Names;
use AKlump\Directio\IO\GetResultFilename;
use AKlump\Directio\IO\GetShortPath;
use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\IO\ReadState;
use AKlump\Directio\IO\WriteDocument;
use AKlump\Directio\Model\DocumentInterface;
use AKlump\Directio\TextProcessor\ValidateTaskSyntax;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AKlump\LocalTimezone\LocalTimezone;

class ImportCommand extends Command {

  use InitializedDirCommandTrait;

  protected static $defaultName = 'import';

  protected static $defaultDescription = 'Import document, applying project task state';

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private OutputInterface $output;

  private string $directioDirectory;

  protected function configure() {
    $this->addArgument('source document', InputArgument::REQUIRED, 'The source document to be filtered.');
  }


  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->output = $output;

    $base_dir = $this->getBaseDirOrInitializeCurrent($input, $output);
    if (!$base_dir) {
      return Command::FAILURE;
    }
    $this->directioDirectory = $base_dir . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;

    try {
      $document_path = $input->getArgument('source document');
      $document = (new ReadDocument())($document_path);
      (new ValidateTaskSyntax())($document->getContent());
      $state = (new ReadState())($this->directioDirectory . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE);

      $this->tryValidateIncomingIdsDoNotAlreadyExists($document);
      $document = $this->applyStateToDocument($state, $document);
    }
    catch (Exception $exception) {
      $output->writeln(sprintf("<error>%s</error>", $exception->getMessage()));

      return Command::FAILURE;
    }
    if (!$this->createNewDocument($document_path, $document)) {
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  private function applyStateToDocument(array $state, DocumentInterface $document): DocumentInterface {
    foreach ($state as $task) {
      if ($task->getCompleted()) {
        $document = $document->withoutTask($task->getId());
      }
    }

    return $document;
  }

  private function createNewDocument($document_path, DocumentInterface $document): bool {
    $now = date_create('now', LocalTimezone::get());
    $imported_doc_path = $this->directioDirectory . DIRECTORY_SEPARATOR . Names::FILENAME_IMPORTED . DIRECTORY_SEPARATOR . (new GetResultFilename($now))($document_path);
    $parent = dirname($imported_doc_path);
    if (!file_exists($parent)) {
      mkdir($parent, 0755, TRUE);
    }

    $shortpath = (new GetShortPath(getcwd()))($imported_doc_path);
    if (file_exists($imported_doc_path)) {
      $this->output->writeln(sprintf('<error>Cannot create a new document as "%s".</error>', basename($imported_doc_path)));
      $this->output->writeln(sprintf('<info>Move %s and try again.</info>', $shortpath));

      return FALSE;
    }
    (new WriteDocument())($imported_doc_path, $document);
    $this->output->writeln(sprintf('<info>File imported to %s</info>', $shortpath));

    return TRUE;
  }

  private function tryValidateIncomingIdsDoNotAlreadyExists(DocumentInterface $importing_document): void {
    $new_ids = $importing_document->getIds();
    $read_document = new ReadDocument();
    $imported_files = glob($this->directioDirectory . DIRECTORY_SEPARATOR . Names::FILENAME_IMPORTED . DIRECTORY_SEPARATOR . '*');
    $invalid = FALSE;
    foreach ($imported_files as $imported_path) {
      $existing_document = $read_document($imported_path);
      $existing_ids = $existing_document->getIds();
      $duplicated_ids = array_intersect($new_ids, $existing_ids);
      if ($duplicated_ids) {
        if (!$invalid) {
          $this->output->writeln('<comment>The following tasks have already been imported:</comment>');
        }
        $invalid = TRUE;
        $this->output->writeln(sprintf('<info>├── %s</info>', basename($imported_path)));
        $this->output->write(array_map(function ($line) {
          return "<comment>│   ├── $line</comment>";
        }, $duplicated_ids), TRUE);
        $this->output->writeln('');
      }
    }

    if ($invalid) {
      $message = "Import failed due to ID collision";
      $this->output->writeln(sprintf('<info>Try deleting or moving the imported files if they are no longer relevant.</info>', $message));
      $this->output->writeln('');
      throw new RuntimeException($message);
    }
  }

}
