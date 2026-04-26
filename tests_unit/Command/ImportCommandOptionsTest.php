<?php

namespace AKlump\Directio\Tests\Unit\Command;

use AKlump\Directio\Command\ImportCommand;
use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\Directio\IO\InitializeDirectory;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \AKlump\Directio\Command\ImportCommand
 * @uses \AKlump\Directio\FixtureFramework\AbstractFixture
 */
class ImportCommandOptionsTest extends TestCase {

  use TestWithFilesTrait;

  private string $projectRoot;

  protected function setUp(): void {
    $this->projectRoot = $this->getTestFileFilepath('project/', TRUE);
    (new InitializeDirectory())($this->projectRoot);
    chdir($this->projectRoot);
  }

  protected function tearDown(): void {
    $this->deleteAllTestFiles();
  }

  public function testImportDirectoryWithOptionsImportsOptionsFile() {
    $sourceDir = $this->getTestFileFilepath('source_dir/', TRUE);
    $optionsFilename = AbstractFixture::YAML_OPTIONS_FILENAME;
    $optionsPath = $sourceDir . '/' . $optionsFilename;
    file_put_contents($optionsPath, 'foo: bar');

    // Create a fixture so the directory isn't empty of importable items
    file_put_contents($sourceDir . '/MyFixture.php', <<<'EOD'
<?php
namespace AKlump\Directio\Fixture;
use AKlump\FixtureFramework\Fixture;
#[Fixture(id: 'my_fixture')]
class MyFixture {}
EOD
    );

    $commandTester = $this->executeCommand(['source document' => $sourceDir]);

    $this->assertStringContainsString('Options imported', $commandTester->getDisplay());
    $this->assertFileExists($this->projectRoot . '/.directio/' . $optionsFilename);
    $this->assertEquals('foo: bar', file_get_contents($this->projectRoot . '/.directio/' . $optionsFilename));
  }

  public function testImportSingleFixtureImportsOptionsFileFromSameDirectory() {
    $sourceDir = $this->getTestFileFilepath('source_dir_single/', TRUE);
    $optionsFilename = AbstractFixture::YAML_OPTIONS_FILENAME;
    $optionsPath = $sourceDir . '/' . $optionsFilename;
    file_put_contents($optionsPath, 'baz: qux');

    $fixturePath = $sourceDir . '/MyFixture.php';
    file_put_contents($fixturePath, <<<'EOD'
<?php
namespace AKlump\Directio\Fixture;
use AKlump\FixtureFramework\Fixture;
#[Fixture(id: 'my_fixture')]
class MyFixture {}
EOD
    );

    $commandTester = $this->executeCommand(['source document' => $fixturePath]);

    $this->assertStringContainsString('Options imported', $commandTester->getDisplay());
    $this->assertFileExists($this->projectRoot . '/.directio/' . $optionsFilename);
    $this->assertEquals('baz: qux', file_get_contents($this->projectRoot . '/.directio/' . $optionsFilename));
  }

  public function testImportOptionsFileDirectly() {
    $optionsFilename = AbstractFixture::YAML_OPTIONS_FILENAME;
    $optionsPath = $this->getTestFileFilepath($optionsFilename);
    file_put_contents($optionsPath, 'hello: world');

    $commandTester = $this->executeCommand(['source document' => $optionsPath]);

    $this->assertStringContainsString('Options imported', $commandTester->getDisplay());
    $this->assertFileExists($this->projectRoot . '/.directio/' . $optionsFilename);
    $this->assertEquals('hello: world', file_get_contents($this->projectRoot . '/.directio/' . $optionsFilename));
  }

  private function executeCommand(array $arguments, array $inputs = []): CommandTester {
    $application = new Application();
    $application->add(new ImportCommand());
    $command = $application->find('import');
    $commandTester = new CommandTester($command);
    $commandTester->setInputs($inputs);
    $commandTester->execute($arguments);

    return $commandTester;
  }
}
