<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Command;

use AKlump\Directio\IO\InitializeDirectory;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitializeCommand extends Command {

  protected static $defaultName = 'init';

  protected static $defaultDescription = 'Initialize the current directory';

  protected function execute(InputInterface $input, OutputInterface $output) {
    try {
      (new InitializeDirectory())(getcwd());
      $output->writeln('<info>Directory is initialized.</info>');

      return Command::SUCCESS;
    }
    catch (Exception $e) {
      return Command::FAILURE;
    }

  }

}
