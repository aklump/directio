<?php

namespace AKlump\Directio\Tests\FixtureFramework;

use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use AKlump\FixtureFramework\Runtime\RunOptions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \AKlump\FixtureFramework\Runtime\RunOptions
 * @covers \AKlump\Directio\FixtureFramework\AbstractFixture
 *
 * @uses \AKlump\Directio\IO\GetShortPath
 */
class RunOptionsTest extends TestCase {

  use TestWithFilesTrait;

  public function testSetRunOptionsMergesLocalOptions() {
    $tempDir = $this->getTestFilePath('project/', TRUE);

    // options.yml
    $runOptionsFile = $tempDir . DIRECTORY_SEPARATOR . 'options.yml';
    file_put_contents($runOptionsFile, "scalar: original\narray: [1, 2]\n");

    // options.local.yml
    $localOptionsFile = $tempDir . DIRECTORY_SEPARATOR . 'options.local.yml';
    file_put_contents($localOptionsFile, "scalar: overridden\narray: [3, 4]\n");

    $input = $this->createMock(InputInterface::class);
    $output = $this->createMock(OutputInterface::class);

    $fixture = new class($input, $output) extends AbstractFixture {

      public function __invoke(): void {
      }
    };

    $options = new RunOptions([
      'directio_directory' => $tempDir,
      'logs_directory' => $tempDir . '/logs',
    ]);
    $fixture->setRunOptions($options);

    $options = $fixture->options();

    // Scalars should be replaced
    $this->assertEquals('overridden', $options->get('scalar'));

    // Arrays should be merged
    $array = $options->get('array');
    $this->assertContains(1, $array);
    $this->assertContains(2, $array);
    $this->assertContains(3, $array);
    $this->assertContains(4, $array);
    $this->assertCount(4, $array);

    // Deep merge check (optional but good to know)
    // Based on my implementation, it's a shallow merge of the first level arrays.
  }

  public function testDeepMerge() {
    $tempDir = $this->getTestFilePath('project_deep/', TRUE);

    // options.yml
    $runOptionsFile = $tempDir . DIRECTORY_SEPARATOR . 'options.yml';
    file_put_contents($runOptionsFile, "nested:\n  a: 1\n");

    // options.local.yml
    $localOptionsFile = $tempDir . DIRECTORY_SEPARATOR . 'options.local.yml';
    file_put_contents($localOptionsFile, "nested:\n  b: 2\n");

    $input = $this->createMock(InputInterface::class);
    $output = $this->createMock(OutputInterface::class);

    $fixture = new class($input, $output) extends AbstractFixture {

      public function __invoke(): void {
      }
    };

    $options = new RunOptions([
      'directio_directory' => $tempDir,
      'logs_directory' => $tempDir . '/logs',
    ]);
    $fixture->setRunOptions($options);

    $options = $fixture->options();
    $nested = $options->get('nested');

    // Current implementation: arrays are merged.
    // array_merge(['a' => 1], ['b' => 2]) -> ['a' => 1, 'b' => 2]
    $this->assertEquals(['a' => 1, 'b' => 2], $nested);
  }

  public function testNumericArrayMerge() {
    $tempDir = $this->getTestFilePath('project_numeric/', TRUE);

    // options.yml
    $runOptionsFile = $tempDir . DIRECTORY_SEPARATOR . 'options.yml';
    file_put_contents($runOptionsFile, "list: [a, b]\n");

    // options.local.yml
    $localOptionsFile = $tempDir . DIRECTORY_SEPARATOR . 'options.local.yml';
    file_put_contents($localOptionsFile, "list: [c, d]\n");

    $input = $this->createMock(InputInterface::class);
    $output = $this->createMock(OutputInterface::class);

    $fixture = new class($input, $output) extends AbstractFixture {

      public function __invoke(): void {
      }
    };

    $options = new RunOptions([
      'directio_directory' => $tempDir,
      'logs_directory' => $tempDir . '/logs',
    ]);
    $fixture->setRunOptions($options);

    $options = $fixture->options();
    $list = $options->get('list');

    // array_merge(['a', 'b'], ['c', 'd']) -> ['a', 'b', 'c', 'd']
    $this->assertEquals(['a', 'b', 'c', 'd'], $list);
  }
}
