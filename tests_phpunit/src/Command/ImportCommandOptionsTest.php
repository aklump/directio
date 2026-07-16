<?php

namespace AKlump\Directio\Tests\Command;

use AKlump\Directio\Command\ImportCommand;
use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\Directio\IO\InitializeDirectory;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \AKlump\Directio\Command\ImportCommand
 *
 * @uses \AKlump\Directio\FixtureFramework\AbstractFixture
 * @uses \AKlump\Directio\HTMLElementStyle
 * @uses \AKlump\Directio\IO\GetCacheDirectory
 * @uses \AKlump\Directio\IO\GetDirectioRoot
 * @uses \AKlump\Directio\IO\GetLogsDirectory
 * @uses \AKlump\Directio\IO\GetShortPath
 * @uses \AKlump\Directio\IO\InitializeDirectory
 */
class ImportCommandOptionsTest extends TestCase {

  use TestWithFilesTrait;

  private string $projectRoot;

  protected function setUp(): void {
    $this->projectRoot = $this->getTestFilePath('project/', TRUE);
    (new InitializeDirectory())($this->projectRoot);
    chdir($this->projectRoot);
  }

  protected function tearDown(): void {
    $this->deleteAllTestFiles();
  }

  public function testImportDirectoryWithOptionsImportsOptionsFile() {
    $sourceDir = $this->getTestFilePath('source_dir/', TRUE);
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
    $sourceDir = $this->getTestFilePath('source_dir_single/', TRUE);
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
    $optionsPath = $this->getTestFilePath($optionsFilename);
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
