<?php

namespace AKlump\Directio\Command;

use AKlump\Directio\DocumentInterface;
use AKlump\Directio\GetResultFilename;
use AKlump\Directio\Names;
use AKlump\Directio\ReadDocument;
use AKlump\Directio\ReadState;
use AKlump\Directio\WriteDocument;
use AKlump\LocalTimezone\LocalTimezone;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command {

  protected static $defaultName = 'update';

  protected static $defaultDescription = 'Remove completed tasks and update state';

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private OutputInterface $output;

  private string $directioDirectory;

  protected function configure() {
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->output = $output;
    $this->directioDirectory = getcwd() . '/' . Names::FILENAME_INIT;
    if (!file_exists($this->directioDirectory)) {
      $output->writeln('<error>Current directory is not initialized.</error>');
      $output->writeln(sprintf('<info>Try the "%s" command first.</info>', InitializeCommand::getDefaultName()));

      return Command::FAILURE;
    }

    $files_to_update = glob($this->directioDirectory . '/*.md');
    if (empty($files_to_update)) {
      $output->writeln(sprintf('<error>No documents in "%s"</error>', $this->directioDirectory));
      $output->writeln(sprintf('<info>Try the "%s" command first.</info>', NewCommand::getDefaultName()));

      return Command::FAILURE;
    }

    foreach ($files_to_update as $path) {
      $output->writeln($path);
      $document = (new ReadDocument())($path);
      $completed_ids = (new \AKlump\Directio\FindCompletedTasks())($document->getContent());
    }

    print '<pre>';
    print __FILE__ . '/' . __FUNCTION__ . '():' . __LINE__ . "\n";
    print_r($completed_ids);
    print '</pre>';
    die;

    try {


      $document_path = $input->getArgument('document');
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
    $now = date_create('now', LocalTimezone::get());
    $filtered_doc_path = $this->directioDirectory . '/' . (new GetResultFilename($now))($document_path);
    (new WriteDocument())($filtered_doc_path, $document);
    $this->output->writeln(sprintf('<info>File created %s</info>', $filtered_doc_path));
  }
}
