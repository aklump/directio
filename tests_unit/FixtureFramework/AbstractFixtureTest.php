<?php

namespace AKlump\Directio\Tests\Unit\FixtureFramework;

use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use AKlump\FixtureFramework\Runtime\RunOptions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \AKlump\Directio\FixtureFramework\AbstractFixture
 *
 * @uses \AKlump\Directio\IO\GetShortPath
 */
class AbstractFixtureTest extends TestCase {

  use TestWithFilesTrait;

  public function testAbstractFixtureMethods() {
    $input = $this->createMock(InputInterface::class);
    $output = $this->createMock(OutputInterface::class);

    $fixture = new class($input, $output) extends AbstractFixture {
      public function __invoke(): void {
      }
    };

    $this->assertInstanceOf(\Symfony\Component\Console\Style\StyleInterface::class, $fixture->io());
    $this->assertNotEmpty($fixture->shortPath(__FILE__));
  }

  public function testSetRunOptionsMergesFileOptions() {
    $tempDir = $this->getTestFileFilepath('project/', TRUE);
    $runOptionsFile = $tempDir . '/fixture_run_options.yml';
    file_put_contents($runOptionsFile, "extra_option: foo\n");

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

    $this->assertEquals($tempDir, $fixture->directioDirectory());
    $this->assertEquals($tempDir . '/logs', $fixture->logsDirectory());

    // Check if extra_option from file was merged
    $this->assertEquals('foo', $fixture->options()->get('extra_option'));
  }
}
