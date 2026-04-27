<?php

namespace AKlump\Directio\Tests\Unit\Command;

use AKlump\Directio\Command\ImportCommand;
use AKlump\Directio\IO\InitializeDirectory;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \AKlump\Directio\Command\ImportCommand
 */
class ImportCommandSkipOnMatchTest extends TestCase {

  use TestWithFilesTrait;

  protected string $projectRoot;

  protected function setUp(): void {
    $this->projectRoot = $this->getTestFileFilepath('project_root/', TRUE);
    (new InitializeDirectory())($this->projectRoot);
  }

  protected function tearDown(): void {
    $this->deleteAllTestFiles();
  }

  public function testImportSingleFixtureSkipsPromptOnMatchingContent() {
    $fixturePath = $this->getTestFileFilepath('SourceFixture.php');
    $content = <<<'EOD'
<?php
namespace AKlump\Directio\Fixture;
use AKlump\FixtureFramework\Fixture;
#[Fixture(id: 'test_fixture')]
class SourceFixture {}
EOD;
    file_put_contents($fixturePath, $content);

    // First import
    $this->executeCommand(['source document' => $fixturePath]);
    $this->assertFileExists($this->projectRoot . '/.directio/src/Fixture/SourceFixture.php');

    // Second import with SAME content.
    // It should NOT prompt and should succeed without needing input.
    // If it prompts, it will wait for input and fail because no input is provided,
    // OR if we provide 'no' and it still prompts, it will fail the status code.

    // We expect it NOT to contain the overwrite prompt in the output.
    $commandTester = $this->executeCommand(['source document' => $fixturePath]);
    $this->assertStringNotContainsString('already exists. Overwrite?', $commandTester->getDisplay());
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertStringContainsString('Fixture ./.directio/src/Fixture/SourceFixture.php skipped as contents are identical.', $commandTester->getDisplay());
  }

  public function testImportSingleDocumentSkipsPromptOnMatchingContent() {
    $docPath = $this->getTestFileFilepath('document.md');
    $content = '## My Tasks' . PHP_EOL . '<directio id="task1">Do something</directio>';
    file_put_contents($docPath, $content);

    // First import
    $this->executeCommand(['source document' => $docPath]);

    // Second import with SAME content.
    $commandTester = $this->executeCommand(['source document' => $docPath]);
    $this->assertStringNotContainsString('already exists. Overwrite?', $commandTester->getDisplay());
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertStringMatchesFormat('%AFile %s skipped as contents are identical.%A', $commandTester->getDisplay());
  }

  public function testImportOptionsFileSkipsPromptOnMatchingContent() {
    $optionsPath = $this->getTestFileFilepath('options.yml');
    $content = 'foo: bar';
    file_put_contents($optionsPath, $content);

    // First import
    $this->executeCommand(['source document' => $optionsPath]);
    $this->assertFileExists($this->projectRoot . '/.directio/options.yml');

    // Second import with SAME content.
    $commandTester = $this->executeCommand(['source document' => $optionsPath]);
    $this->assertStringNotContainsString('already exists. Overwrite?', $commandTester->getDisplay());
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertStringContainsString('Options ./.directio/options.yml skipped as contents are identical.', $commandTester->getDisplay());
  }

  public function testImportDirectoryWithIdenticalSkipsShowsNuancedSummary() {
    $sourceDir = $this->getTestFileFilepath('source_dir_identical/', TRUE);

    // 1. Fixture
    file_put_contents($sourceDir . '/Fixture1.php', <<<'EOD'
<?php
namespace AKlump\Directio\Fixture;
use AKlump\FixtureFramework\Fixture;
#[Fixture(id: 'f1')]
class Fixture1 {}
EOD
    );

    // 2. Document with Markup
    file_put_contents($sourceDir . '/markup.md', '<directio id="m1"></directio>');

    // First import all
    $this->executeCommand(['source document' => $sourceDir]);

    // Second import, all should be identical
    $commandTester = $this->executeCommand(['source document' => $sourceDir]);

    $display = $commandTester->getDisplay();
    $this->assertStringContainsString('Imported 0, skipped 2 identical of 2 items found', $display);
  }

  private function executeCommand(array $arguments, array $inputs = []): CommandTester {
    $application = new Application();
    $application->add(new ImportCommand());
    $command = $application->find('import');
    $commandTester = new CommandTester($command);
    $commandTester->setInputs($inputs);
    // Ensure we are in the project root for the command to find .directio
    chdir($this->projectRoot);
    $commandTester->execute($arguments);

    return $commandTester;
  }
}
