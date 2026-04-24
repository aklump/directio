<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\IO\GetShortPath;
use AKlump\Directio\IO\InitializeDirectory;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitializeCommand extends Command {

  protected static $defaultName = 'init';

  protected static $defaultDescription = 'Initialize PWD as task tracking root';

  protected function execute(InputInterface $input, OutputInterface $output) {
    try {
      $pwd = getcwd();
      (new InitializeDirectory())($pwd);
      $shortpath = (new GetShortPath($pwd))($pwd);
      $output->writeln(sprintf('<info>Directory "%s" is initialized.</info>', $shortpath));

      return Command::SUCCESS;
    }
    catch (Exception $e) {
      return Command::FAILURE;
    }

  }

}
