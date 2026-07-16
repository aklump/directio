<?php

namespace AKlump\Directio\Tests\Command;

use AKlump\Directio\Command\InitializeCommand;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \AKlump\Directio\Command\InitializeCommand
 *
 * @uses \AKlump\Directio\IO\InitializeDirectory
 * @uses \AKlump\Directio\IO\GetShortPath
 * @uses \AKlump\Directio\Config\Names
 * @uses \AKlump\Directio\IO\GetLogsDirectory
 * @uses \AKlump\Directio\IO\GetDirectioRoot
 */
class InitializeCommandTest extends TestCase {

  use TestWithFilesTrait;

  private string $projectRoot;

  protected function setUp(): void {
    $this->projectRoot = $this->getTestFilePath('project/', TRUE);
    chdir($this->projectRoot);
  }

  protected function tearDown(): void {
    $this->deleteAllTestFiles();
  }

  public function testInitCommandCreatesDirectory() {
    $application = new Application();
    $application->add(new InitializeCommand());

    $command = $application->find('init');
    $commandTester = new CommandTester($command);
    $commandTester->execute([]);

    $this->assertStringContainsString('is initialized', $commandTester->getDisplay());
    $this->assertFileExists($this->projectRoot . '/.directio');
    $this->assertFileExists($this->projectRoot . '/.directio/state.sqlite');
    $this->assertFileExists($this->projectRoot . '/.directio/src/Fixture');
    $this->assertFileExists($this->projectRoot . '/.directio/logs');
    $this->assertFileExists($this->projectRoot . '/.directio/.gitignore');
    $this->assertEquals(0, $commandTester->getStatusCode());
  }

  public function testInitCommandHandlesException() {
    // Create a file where the directory should be to cause an error
    $target = $this->projectRoot . '/.directio';
    touch($target);

    $application = new Application();
    $application->add(new InitializeCommand());

    $command = $application->find('init');
    $commandTester = new CommandTester($command);
    $commandTester->execute([]);

    $this->assertEquals(1, $commandTester->getStatusCode());
  }
}
