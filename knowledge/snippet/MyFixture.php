<?php

namespace AKlump\Directio\Fixture;

use AKlump\Directio\FixtureFramework\AbstractFixture;

#[\AKlump\FixtureFramework\Fixture(id: 'my_fixture')]
class MyFixture extends AbstractFixture {

  public function __invoke(): void {
    $log_file = $this->logsDirectory() . '/my_log.log';
    file_put_contents($log_file, 'MyFixture invoked');

    $this->output()->writeln('<info>MyFixture invoked</info>');
  }
}
