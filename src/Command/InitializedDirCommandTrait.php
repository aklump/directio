<?php

namespace AKlump\Directio\Command;

use AKlump\Directio\IO\GetDirectioRoot;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

trait InitializedDirCommandTrait {

  protected function getBaseDirOrInitializeCurrent(InputInterface $input, OutputInterface $output): string {
    $base_dir = (new GetDirectioRoot())();
    if ($base_dir) {
      return $base_dir;
    }
    $output->writeln('<error>Directio is not tracking your project.</error>');
    $helper = $this->getHelper('question');
    $question = new ConfirmationQuestion(sprintf('Set up %s for task tracking?', getcwd()), FALSE);
    if (!$helper->ask($input, $output, $question)) {
      $output->writeln('<error>Import cancelled.</error>');

      return '';
    }
    (new InitializeCommand())->execute($input, $output);

    return (new GetDirectioRoot())();
  }

}
