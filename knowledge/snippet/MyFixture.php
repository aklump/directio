<?php

namespace AKlump\Directio\Fixture;

use AKlump\FixtureFramework\AbstractFixture;

#[\AKlump\FixtureFramework\Fixture(id: 'my_fixture')]
class MyFixture extends AbstractFixture {

  public function __invoke(): void {
    $logfile = $this->options->require('logs_directory');
    file_put_contents($logfile, 'MyFixture invoked');
  }
}
