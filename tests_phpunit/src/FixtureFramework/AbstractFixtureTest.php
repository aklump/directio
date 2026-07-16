<?php

namespace AKlump\Directio\Tests\FixtureFramework;

use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\FixtureFramework\Runtime\RunOptions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \AKlump\Directio\FixtureFramework\AbstractFixture
 *
 * @uses \AKlump\Directio\IO\GetShortPath
 * @uses \AKlump\Directio\Helper\MarkTaskDoneInDocument
 * @uses \AKlump\Directio\IO\ReadDocument
 * @uses \AKlump\Directio\IO\WriteDocument
 * @uses \AKlump\Directio\IO\WriteState
 * @uses \AKlump\Directio\Model\TaskState
 * @uses \AKlump\Directio\Config\SpecialAttributes
 * @uses \AKlump\Directio\Lexer\TaskLexer
 * @uses \AKlump\Directio\TextProcessor\ParseAttributes
 * @uses \AKlump\Directio\Model\Document
 * @uses \AKlump\Directio\HTMLElementStyle
 * @uses \AKlump\Directio\Lexer\AttributesLexer
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

  public function testShouldRunReturnsTrueIfFilterMatches() {
    $input = $this->createMock(InputInterface::class);
    $input->method('hasOption')->with('filter')->willReturn(TRUE);
    $input->method('getOption')->with('filter')->willReturn('foo');
    $output = $this->createMock(OutputInterface::class);

    $fixture = new class($input, $output) extends AbstractFixture {

      public function __invoke(): void {
      }
    };
    $fixture->setFixtureDefinition(['id' => 'fixture_foo_test']);

    $this->assertTrue($fixture->shouldRun());
  }

  public function testShouldRunReturnsTrueIfFilterMatchesArgument() {
    $input = $this->createMock(InputInterface::class);
    $input->method('hasArgument')->with('filter')->willReturn(TRUE);
    $input->method('getArgument')->with('filter')->willReturn('foo');
    $output = $this->createMock(OutputInterface::class);

    $fixture = new class($input, $output) extends AbstractFixture {

      public function __invoke(): void {
      }
    };
    $fixture->setFixtureDefinition(['id' => 'fixture_foo_test']);

    $this->assertTrue($fixture->shouldRun());
  }

  public function testShouldRunPromptsIfFilterDoesNotMatch() {
    $input = $this->createMock(InputInterface::class);
    $input->method('hasOption')->with('filter')->willReturn(TRUE);
    $input->method('getOption')->with('filter')->willReturn('foo');
    $output = $this->createMock(OutputInterface::class);

    $styleMock = $this->createMock(\Symfony\Component\Console\Style\SymfonyStyle::class);
    $styleMock->expects($this->once())
      ->method('confirm')
      ->with($this->stringContains('Run fixture "bar"?'))
      ->willReturn(TRUE);

    $fixture = new class($input, $output, $styleMock) extends AbstractFixture {

      private $mockIo;

      public function __construct($input, $output, $mockIo) {
        parent::__construct($input, $output);
        $this->mockIo = $mockIo;
      }

      public function __invoke(): void {
      }

      public function io(): \Symfony\Component\Console\Style\SymfonyStyle {
        return $this->mockIo;
      }
    };
    $fixture->setFixtureDefinition(['id' => 'bar']);

    $this->assertTrue($fixture->shouldRun());
  }

  public function testSetRunOptionsMergesFileOptions() {
    $tempDir = $this->getTestFilePath('project/', TRUE);
    $runOptionsFile = $tempDir . DIRECTORY_SEPARATOR . AbstractFixture::YAML_OPTIONS_FILENAME;
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

  public function testMarkDone() {
    $tempDir = $this->getTestFilePath('project/', TRUE);
    $docPath = $tempDir . '/tasks.md';
    file_put_contents($docPath, '<directio id="t1" fixture="f1"></directio>');

    $input = $this->createMock(InputInterface::class);
    $output = $this->createMock(OutputInterface::class);

    $fixture = new class($input, $output) extends AbstractFixture {

      public function __invoke(): void {
      }
    };
    $fixture->setFixtureDefinition([
      'id' => 'f1',
      'mappings' => [
        [
          'path' => $docPath,
          'id' => 't1',
          'attributes' => ['id' => 't1', 'fixture' => 'f1'],
        ],
      ],
    ]);
    $fixture->setRunOptions(new RunOptions([
      'directio_directory' => $tempDir,
      'logs_directory' => $tempDir . '/logs',
    ]));

    $fixture->markDone();

    $content = file_get_contents($docPath);
    $this->assertStringContainsString('<directio done id="t1" fixture="f1">', $content);

    // Check state file
    $statePath = $tempDir . '/state.sqlite';
    $this->assertFileExists($statePath);
  }

}
