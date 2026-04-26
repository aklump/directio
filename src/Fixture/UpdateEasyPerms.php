<?php

namespace AKlump\Directio\Fixture;

use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\FixtureFramework\Exception\FixtureException;

class UpdateEasyPerms extends AbstractFixture {

  public function __invoke(): void {
    $output = [];
    $return_var = 0;
    exec('composer show aklump/easy-perms 2>&1', $output, $return_var);
    if ($return_var === 0) {
      $this->output()->writeln('<info>Easy Perms is installed via top-level composer. No action needed.</info>');

      return;
    }

    $easy_perms_dir = getcwd() . '/easy-perms';
    if (!is_dir($easy_perms_dir)) {
      $this->output()->writeln('<info>Subdirectory "easy-perms/" not found. Nothing to update.</info>');

      return;
    }

    $this->output()->writeln('<info>Updating Easy Perms in easy-perms/ directory...</info>');
    $commands = [
      sprintf('cd %s', escapeshellarg($easy_perms_dir)),
      'composer update',
      'git add .',
      "git commit -m 'Update easy-perms' --trailer \"Co-authored-by: Junie <junie@jetbrains.com>\"",
    ];

    $full_command = implode(' && ', $commands);
    $output = [];
    $return_var = 0;
    exec($full_command, $output, $return_var);

    if ($return_var !== 0) {
      $this->output()->writeln(implode(PHP_EOL, $output));
      throw new FixtureException('Update Easy Perms failed.');
    }

    $this->output()->writeln('<info>Easy Perms updated successfully in easy-perms/.</info>');
  }
}
