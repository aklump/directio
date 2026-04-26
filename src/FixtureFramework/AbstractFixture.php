<?php

namespace AKlump\Directio\FixtureFramework;

use AKlump\Directio\IO\GetShortPath;
use AKlump\FixtureFramework\Runtime\RunOptions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use AKlump\FixtureFramework\AbstractFixture as BaseFixture;
use Symfony\Component\Yaml\Yaml;

/**
 * Represents an abstract fixture for handling direct input/output operations.
 * Provides utility methods for managing console output and file path resolution.
 */
abstract class AbstractFixture extends BaseFixture {

  private InputInterface $input;

  private OutputInterface $output;

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
  }

  public function setRunOptions(RunOptions $options): void {
    parent::setRunOptions($options);

    $run_options_filepath = $this->directioDirectory() . '/fixture_run_options.yml';
    if (file_exists($run_options_filepath)) {
      $file_provided_options = Yaml::parseFile($run_options_filepath);
      if (!is_array($file_provided_options)) {
        throw new \InvalidArgumentException('Config file must be an array.');
      }
      parent::setRunOptions($this->options()
        ->withAddedOptions($file_provided_options));
    }
  }

  public function io(): SymfonyStyle {
    return new SymfonyStyle($this->input, $this->output);
  }

  /**
   * Resolves and returns the shortened version of the provided file path.
   *
   * @param string $path The input file path to be shortened.
   *
   * @return string The shortened file path.
   */
  public function shortPath(string $path): string {
    return (new GetShortPath())($path);
  }

  public function directioDirectory(): string {
    return $this->options()->require('directio_directory');
  }

  public function logsDirectory(): string {
    return $this->options()->require('logs_directory');
  }

}
