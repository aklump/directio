<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\Config\Names;
use AKlump\Directio\Config\SpecialAttributes;
use AKlump\Directio\IO\GetShortPath;
use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\IO\WriteDocument;
use AKlump\Directio\IO\WriteState;
use AKlump\Directio\Model\TaskState;
use AKlump\Directio\TextProcessor\ScanForCompletedTasks;
use AKlump\Directio\TextProcessor\ValidateTaskSyntax;
use AKlump\LocalTimezone\LocalTimezone;
use DateInterval;
use DateTimeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateCommand extends Command {

  use InitializedDirCommandTrait;

  protected static $defaultName = 'update';

  protected static $defaultDescription = 'Remove completed tasks from all project documents';

  /**
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  private InputInterface $input;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private OutputInterface $output;

  private string $directioDirectory;

  private SymfonyStyle $io;

  private function io(): SymfonyStyle {
    return $this->io ??= new SymfonyStyle($this->input, $this->output);
  }

  protected function configure() {
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
    $base_dir = $this->getBaseDirOrInitializeCurrent($input, $output);
    if (!$base_dir) {
      return Command::FAILURE;
    }
    $this->directioDirectory = $base_dir . DIRECTORY_SEPARATOR . Names::FILENAME_INIT;
    $now = date_create('now', LocalTimezone::get());
    if (!file_exists($this->directioDirectory)) {
      $this->io()->error('Current directory is not initialized.');
      $this->io()->info(sprintf('Try the "%s" command first.', InitializeCommand::getDefaultName()));

      return Command::FAILURE;
    }

    $files_to_update = glob($this->directioDirectory . DIRECTORY_SEPARATOR . Names::FILENAME_IMPORTED . DIRECTORY_SEPARATOR . '*');
    $shortpath_directio = (new GetShortPath())($this->directioDirectory);
    if (empty($files_to_update)) {
      $this->io()->error(sprintf('No documents in "%s"', $shortpath_directio));
      $this->io()->info(sprintf('Try the "%s" command first.', ImportCommand::getDefaultName()));

      return Command::FAILURE;
    }

    $write_state = new WriteState();
    $state_path = $this->directioDirectory . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE;

    foreach ($files_to_update as $document_path) {
      $document_label = (new GetShortPath())($document_path);
      $this->io()->writeln($document_label);
      $document = (new ReadDocument())($document_path);
      (new ValidateTaskSyntax())($document->getContent());
      $completed_tasks = (new ScanForCompletedTasks())($document->getContent());

      if (empty($completed_tasks)) {
        continue;
      }

      foreach ($completed_tasks as $task_data) {
        $task_id = SpecialAttributes::extractId($task_data);
        $this->io()->writeln('✅ ' . $task_id);
        $document = $document->withoutTask($task_id);

        $task = (new TaskState())
          ->setId($task_id)
          ->setEnv(exec('echo "$(hostname)"'))
          ->setCompleted($now->format(DateTimeInterface::ATOM))
          ->setUser(exec('whoami'));

        $expires = SpecialAttributes::extractExpires($task_data);
        if ($expires) {
          $duration = new DateInterval($expires);
          if ($duration) {
            $expiry = (clone $now)->add($duration);
          }
          $task->setRedo($expiry->format(DateTimeInterface::ATOM));
        }
        $write_state->writeOne($state_path, $task);
      }
      (new WriteDocument())($document_path, $document);
    }

    return Command::SUCCESS;
  }
}
