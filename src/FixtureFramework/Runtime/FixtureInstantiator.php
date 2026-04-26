<?php

namespace AKlump\Directio\FixtureFramework\Runtime;

use AKlump\FixtureFramework\FixtureInterface;
use AKlump\FixtureFramework\Runtime\RunContextValidator;
use AKlump\FixtureFramework\Runtime\RunOptions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixtureInstantiator extends \AKlump\FixtureFramework\Runtime\FixtureInstantiator {

  protected InputInterface $input;

  protected OutputInterface $output;

  public function __construct(
    array|RunOptions $global_options,
    InputInterface $input,
    OutputInterface $output,
    ?RunContextValidator $validator = NULL,
  ) {
    parent::__construct($global_options, $validator);
    $this->input = $input;
    $this->output = $output;
  }

  protected function createFixture(array $definition): FixtureInterface {
    return new $definition['class']($this->input, $this->output);
  }


}
