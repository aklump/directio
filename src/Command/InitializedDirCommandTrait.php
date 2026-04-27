<?php

namespace AKlump\Directio\Command;

use AKlump\Directio\IO\GetDirectioRoot;
use AKlump\Directio\IO\GetShortPath;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait InitializedDirCommandTrait {

  protected function getBaseDirOrInitializeCurrent(InputInterface $input, OutputInterface $output): string {
    $base_dir = (new GetDirectioRoot())();
    if ($base_dir) {
      return $base_dir;
    }
    $io = new SymfonyStyle($input, $output);
    $io->error('Directio is not tracking your project.');
    $pwd = getcwd();
    $shortpath = (new GetShortPath($pwd))($pwd);
    if (!$io->confirm(sprintf('Set up %s for task tracking?', $shortpath), FALSE)) {
      $io->error('Import cancelled.');

      return '';
    }
    (new InitializeCommand())->execute($input, $output);

    return (new GetDirectioRoot())();
  }

}
