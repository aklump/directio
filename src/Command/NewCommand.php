<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\Config\Names;
use AKlump\Directio\IO\GetDirectioRoot;
use AKlump\Directio\IO\GetResultFilename;
use AKlump\Directio\IO\GetShortPath;
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
use Symfony\Component\Console\Question\ConfirmationQuestion;

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

  protected function getBaseDirOrInitializeCurrent(): string {
    $base_dir = (new GetDirectioRoot())();
    if ($base_dir) {
      return $base_dir;
    }
    $this->output->writeln('<error>Directio has not been initialized.</error>');
    $helper = $this->getHelper('question');
    $question = new ConfirmationQuestion(sprintf('Would you like to initialize %s?', getcwd()), FALSE);
    if (!$helper->ask($this->input, $this->output, $question)) {
      return Command::FAILURE;
    }
    (new InitializeCommand())->execute($this->input, $this->output);

    return (new GetDirectioRoot())();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;

    $base_dir = $this->getBaseDirOrInitializeCurrent();
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
    $filtered_doc_path = $this->directioDirectory . DIRECTORY_SEPARATOR . (new GetResultFilename())($document_path);
    $shortpath = (new GetShortPath(getcwd()))($filtered_doc_path);
    if (file_exists($filtered_doc_path)) {
      $this->output->writeln(sprintf('<error>Cannot create a new document as "%s".</error>', basename($filtered_doc_path)));
      $this->output->writeln(sprintf('<info>Move %s and try again.</info>', $shortpath));

      return FALSE;
    }
    (new WriteDocument())($filtered_doc_path, $document);
    $this->output->writeln(sprintf('<info>File created %s</info>', $shortpath));

    return TRUE;
  }
}
