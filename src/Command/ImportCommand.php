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
use Exception;
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
      $state = (new ReadState())($this->directioDirectory . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE);
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
    $filtered_doc_path = $this->directioDirectory . DIRECTORY_SEPARATOR . (new GetResultFilename($now))($document_path);
    $shortpath = (new GetShortPath(getcwd()))($filtered_doc_path);
    if (file_exists($filtered_doc_path)) {
      $this->output->writeln(sprintf('<error>Cannot create a new document as "%s".</error>', basename($filtered_doc_path)));
      $this->output->writeln(sprintf('<info>Move %s and try again.</info>', $shortpath));

      return FALSE;
    }
    (new WriteDocument())($filtered_doc_path, $document);
    $this->output->writeln(sprintf('<info>File imported to %s</info>', $shortpath));

    return TRUE;
  }
}
