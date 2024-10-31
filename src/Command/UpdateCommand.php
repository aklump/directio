<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\Config\Names;
use AKlump\Directio\Config\SpecialAttributes;
use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\IO\ReadState;
use AKlump\Directio\IO\WriteDocument;
use AKlump\Directio\IO\WriteState;
use AKlump\Directio\Model\TaskState;
use AKlump\Directio\TextProcessor\ScanForCompletedTasks;
use AKlump\LocalTimezone\LocalTimezone;
use DateInterval;
use DateTimeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command {

  use InitializedDirCommandTrait;

  protected static $defaultName = 'update';

  protected static $defaultDescription = 'Remove completed tasks from all project documents';

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private OutputInterface $output;

  private string $directioDirectory;

  protected function configure() {
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $base_dir = $this->getBaseDirOrInitializeCurrent($input, $output);
    if (!$base_dir) {
      return Command::FAILURE;
    }
    $this->directioDirectory = $base_dir . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;
    $now = date_create('now', LocalTimezone::get());

    $this->output = $output;
    if (!file_exists($this->directioDirectory)) {
      $output->writeln('<error>Current directory is not initialized.</error>');
      $output->writeln(sprintf('<info>Try the "%s" command first.</info>', InitializeCommand::getDefaultName()));

      return Command::FAILURE;
    }

    $files_to_update = glob($this->directioDirectory . '/*.md');
    if (empty($files_to_update)) {
      $output->writeln(sprintf('<error>No documents in "%s"</error>', $this->directioDirectory));
      $output->writeln(sprintf('<info>Try the "%s" command first.</info>', ImportCommand::getDefaultName()));

      return Command::FAILURE;
    }

    $state = [];
    $state_path = $this->directioDirectory . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE;
    if (file_exists($state_path)) {
      $state = (new ReadState())($state_path);
    }

    foreach ($files_to_update as $document_path) {
      $output->writeln($document_path);
      $document = (new ReadDocument())($document_path);
      $completed_tasks = (new ScanForCompletedTasks())($document->getContent());
      if (empty($completed_tasks)) {
        continue;
      }

      foreach ($completed_tasks as $task_data) {
        $document = $document->withoutTask($task_data['id']);

        $task = (new TaskState())
          ->setId($task_data['id'])
          ->setEnv(exec('echo "$(hostname)"'))
          ->setCompleted($now->format(DateTimeInterface::ATOM))
          ->setUser(exec('whoami'));

        $expires = array_intersect_key($task_data, SpecialAttributes::expiresKeys());
        if ($expires) {
          $duration = new DateInterval($task_data[key($expires)]);
          if ($duration) {
            $expiry = (clone $now)->add($duration);
          }
          $task->setRedo($expiry->format(DateTimeInterface::ATOM));
        }
        $state[] = $task;
      }
      (new WriteDocument())($document_path, $document);
    }

    $state = $this->dedupeState($state);
    (new WriteState())($state_path, $state);

    return Command::SUCCESS;
  }

  private function dedupeState(array $state): array {
    $deduped_state = [];
    foreach ($state as $task) {
      $deduped_state[$task->getId()] = $task;
    }

    return array_values($deduped_state);
  }
}
