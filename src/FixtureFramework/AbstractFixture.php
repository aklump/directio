<?php

namespace AKlump\Directio\FixtureFramework;

use AKlump\Directio\Config\Names;
use AKlump\Directio\Config\SpecialAttributes;
use AKlump\Directio\Helper\MarkTaskDoneInDocument;
use AKlump\Directio\IO\GetShortPath;
use AKlump\Directio\IO\ReadDocument;
use AKlump\Directio\IO\WriteDocument;
use AKlump\Directio\IO\WriteState;
use AKlump\Directio\Model\TaskState;
use AKlump\LocalTimezone\LocalTimezone;
use AKlump\FixtureFramework\Runtime\RunOptions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use AKlump\FixtureFramework\AbstractFixture as BaseFixture;
use Symfony\Component\Yaml\Yaml;

/**
 * Represents an abstract fixture for handling direct input/output operations.
 * Provides utility methods for managing console output and file path resolution.
 */
abstract class AbstractFixture extends BaseFixture {

  public const YAML_OPTIONS_FILENAME = 'options.yml';

  public const YAML_LOCAL_OPTIONS_FILENAME = 'options.local.yml';

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

    $files = [
      self::YAML_OPTIONS_FILENAME,
      self::YAML_LOCAL_OPTIONS_FILENAME,
    ];
    foreach ($files as $filename) {
      $file_options_path = $this->directioDirectory() . DIRECTORY_SEPARATOR . $filename;
      if (file_exists($file_options_path)) {
        $file_provided_options = Yaml::parseFile($file_options_path);
        if (!is_array($file_provided_options)) {
          throw new \InvalidArgumentException(sprintf('Config file "%s" must be an array.', $filename));
        }

        $merged_options = $this->options()->all();
        foreach ($file_provided_options as $key => $value) {
          if (is_array($value) && isset($merged_options[$key]) && is_array($merged_options[$key])) {
            $merged_options[$key] = array_merge($merged_options[$key], $value);
          }
          else {
            $merged_options[$key] = $value;
          }
        }
        parent::setRunOptions(new RunOptions($merged_options));
      }
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

  public function cacheDirectory(): string {
    return $this->options()->require('cache_directory');
  }

  public function logsDirectory(): string {
    return $this->options()->require('logs_directory');
  }

  /**
   * Determines whether the fixture should be run based on some criteria.
   *
   * @return bool True if the fixture should run, false otherwise.
   */
  public function shouldRun(): bool {
    $filter_value = '';
    if ($this->input->hasArgument('filter')) {
      $filter_value = $this->input->getArgument('filter');
    }
    if (empty($filter_value) && $this->input->hasOption('filter')) {
      $filter_value = $this->input->getOption('filter');
    }

    if (!empty($filter_value) && str_contains($this->id(), $filter_value)) {
      return TRUE;
    }

    return $this->io()
      ->confirm(sprintf('Run fixture "%s"?', $this->id()));
  }

  /**
   * {@inheritdoc}
   */
  public function onSuccess(bool $silent = FALSE, bool $mark_as_done_default = TRUE) {
    if (!$silent && $this->io()
        ->confirm('Success. Mark as done? ', $mark_as_done_default)) {
      $this->markDone();
    }
  }

  public function markDone(): void {
    $mappings = $this->fixture()['mappings'] ?? [];
    if (empty($mappings)) {
      return;
    }

    $mark_done = new MarkTaskDoneInDocument();
    $read_document = new ReadDocument();
    $write_document = new WriteDocument();
    $write_state = new WriteState();
    $state_path = $this->directioDirectory() . DIRECTORY_SEPARATOR . Names::FILENAME_STATE . '.' . Names::EXTENSION_STATE;
    $now = date_create('now', LocalTimezone::get());

    foreach ($mappings as $mapping) {
      $path = $mapping['path'];
      $task_id = $mapping['id'];
      $document = $read_document($path);
      $document = $mark_done($task_id, $document);
      $write_document($path, $document);
      $this->io()
        ->writeln(sprintf('Marked "%s" as done in %s', $task_id, $this->shortPath($path)));

      $task = (new TaskState())
        ->setId($task_id)
        ->setEnv(exec('echo "$(hostname)"'))
        ->setCompleted($now->format(\DateTimeInterface::ATOM))
        ->setUser(exec('whoami'));

      $expires = SpecialAttributes::extractExpires($mapping['attributes']);
      if ($expires) {
        $duration = new \DateInterval($expires);
        if ($duration) {
          $expiry = (clone $now)->add($duration);
          $task->setRedo($expiry->format(\DateTimeInterface::ATOM));
        }
      }
      $write_state->writeOne($state_path, $task);
    }
  }

}
