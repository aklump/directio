<?php

namespace AKlump\Directio\Command;

use AKlump\Directio\Config\Names;
use AKlump\Directio\IO\GetResultFilename;
use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\IO\ReadState;
use AKlump\Directio\IO\WriteDocument;
use AKlump\Directio\Model\DocumentInterface;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NewCommand extends Command {

  protected static $defaultName = 'new';

  protected static $defaultDescription = 'Create a new document based on the current state';

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
    $this->directioDirectory = getcwd() . '/' . Names::FILENAME_INIT;
    if (!file_exists($this->directioDirectory)) {
      $output->writeln('<error>Current directory is not initialized.</error>');
      $output->writeln('<info>Try the init command first.</info>');

      return Command::FAILURE;
    }

    try {
      $document_path = $input->getArgument('source document');
      $document = (new ReadDocument())($document_path);
      $state = (new ReadState())($this->directioDirectory . '/' . Names::FILENAME_STATE . '.yml');
      $document = $this->applyStateToDocument($state, $document);
      $this->createNewDocument($document_path, $document);
    }
    catch (Exception $exception) {
      $output->writeln(sprintf("<error>%s</error>", $exception->getMessage()));

      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  private function applyStateToDocument(array $state, DocumentInterface $document): DocumentInterface {
    foreach ($state as $task) {
      if ($task->isComplete()) {
        $document = $document->withoutTask($task->getId());
      }
    }

    return $document;
  }

  private function createNewDocument($document_path, DocumentInterface $document): void {
    $filtered_doc_path = $this->directioDirectory . '/' . (new GetResultFilename())($document_path);
    if (file_exists($filtered_doc_path)) {
      throw new RuntimeException(sprintf('"%s" already exists.', basename($filtered_doc_path)));
    }
    (new WriteDocument())($filtered_doc_path, $document);
    $this->output->writeln(sprintf('<info>File created %s</info>', $filtered_doc_path));
  }
}
