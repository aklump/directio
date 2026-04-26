<?php

namespace AKlump\Directio\FixtureFramework;

use AKlump\Directio\IO\GetShortPath;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public function __construct(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
  }

  public function initialize(): void {
    $this->handleRunOptionsInYaml();
  }

  /**
   * Returns the input instance for collecting data from the user.
   *
   * @return \Symfony\Component\Console\Input\InputInterface
   */
  public function input(): InputInterface {
    return $this->input;
  }

  /**
   * Returns output instance for user messaging.
   *
   * @return OutputInterface The output interface instance.
   *
   * @url https://symfony.com/doc/current/components/console/helpers/index.html
   */
  public function output(): OutputInterface {
    return $this->output;
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
    return $this->options->require('directio_directory');
  }

  public function logsDirectory(): string {
    return $this->options->require('logs_directory');
  }

  private function handleRunOptionsInYaml(): void {
    $config_path = $this->directioDirectory() . '/fixture_run_options.yml';
    if (file_exists($config_path)) {
      $options = Yaml::parseFile($config_path);
      if (!is_array($options)) {
        throw new \InvalidArgumentException('Config file must be an array.');
      }
      $this->options = $this->options->withAddedOptions($options);
    }
  }

}
