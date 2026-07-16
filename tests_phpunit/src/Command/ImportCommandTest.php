<?php

namespace AKlump\Directio\Tests\Command;

use AKlump\Directio\Command\ImportCommand;
use AKlump\Directio\IO\InitializeDirectory;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \AKlump\Directio\Command\ImportCommand
 * @covers \AKlump\Directio\IO\InitializeDirectory
 *
 * @uses \AKlump\Directio\Config\Names
 * @uses \AKlump\Directio\Config\SpecialAttributes
 * @uses \AKlump\Directio\HTMLElementStyle
 * @uses \AKlump\Directio\Helper\ApplyStateToDocument
 * @uses \AKlump\Directio\IO\GetCacheDirectory
 * @uses \AKlump\Directio\IO\GetDirectioRoot
 * @uses \AKlump\Directio\IO\GetLogsDirectory
 * @uses \AKlump\Directio\IO\GetResultFilename
 * @uses \AKlump\Directio\IO\GetShortPath
 * @uses \AKlump\Directio\IO\ReadDocument
 * @uses \AKlump\Directio\IO\ReadState
 * @uses \AKlump\Directio\IO\WriteDocument
 * @uses \AKlump\Directio\Lexer\AttributesLexer
 * @uses \AKlump\Directio\Lexer\TaskLexer
 * @uses \AKlump\Directio\Model\Document
 * @uses \AKlump\Directio\TextProcessor\ParseAttributes
 * @uses \AKlump\Directio\TextProcessor\ValidateTaskSyntax
 * @uses \AKlump\Directio\Traits\HasStyleTrait
 */
class ImportCommandTest extends TestCase {

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

  public function testImportSingleFixtureSucceeds() {
    $fixturePath = $this->getTestFilePath('SourceFixture.php');
    $content = <<<'EOD'
<?php
namespace AKlump\Directio\Fixture;
use AKlump\FixtureFramework\Fixture;
#[Fixture(id: 'test_fixture')]
class SourceFixture {}
EOD;
    file_put_contents($fixturePath, $content);

    $commandTester = $this->executeCommand(['source document' => $fixturePath]);

    $this->assertStringContainsString('Fixture imported to ./.directio/src/Fixture/SourceFixture.php', $commandTester->getDisplay());
    $this->assertFileExists($this->projectRoot . '/.directio/src/Fixture/SourceFixture.php');
  }

  public function testImportSingleFixtureOverwritePrompt() {
    $fixturePath = $this->getTestFilePath('SourceFixture.php');
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

    // Second import, answer No
    file_put_contents($fixturePath, $content . "\n// Changed");
    $commandTester = $this->executeCommand(['source document' => $fixturePath], ['no']);
    $this->assertStringContainsString('Fixture "SourceFixture.php" already exists. Overwrite?', $commandTester->getDisplay());
    // In this case it returns Command::FAILURE because importFixture returns FALSE
    $this->assertEquals(1, $commandTester->getStatusCode());

    // Third import, answer Yes
    $commandTester = $this->executeCommand(['source document' => $fixturePath], ['yes']);
    $this->assertStringContainsString('Fixture imported', $commandTester->getDisplay());
    $this->assertEquals(0, $commandTester->getStatusCode());
  }

  public function testImportSingleDocumentOverwritePrompt() {
    $docPath = $this->getTestFilePath('document.md');
    $content = '## My Tasks' . PHP_EOL . '<directio id="task1">Do something</directio>';
    file_put_contents($docPath, $content);

    // First import
    $this->executeCommand(['source document' => $docPath]);

    // Modify content so we don't trigger ID collision if it was being checked too early
    // (though in this test it is checking against the file about to be overwritten)
    $content2 = '## My Tasks 2' . PHP_EOL . '<directio id="task2">Do something else</directio>';
    file_put_contents($docPath, $content2);

    // Second import, answer No
    $commandTester = $this->executeCommand(['source document' => $docPath], ['no']);
    $this->assertStringContainsString('already exists. Overwrite?', $commandTester->getDisplay());
    $this->assertEquals(1, $commandTester->getStatusCode());

    // Third import, answer Yes
    $commandTester = $this->executeCommand(['source document' => $docPath], ['yes']);
    $this->assertStringContainsString('File imported', $commandTester->getDisplay());
    $this->assertEquals(0, $commandTester->getStatusCode());
  }

  public function testImportSingleFixtureWithWrongNamespaceDoesNotImportAsFixture() {
    $fixturePath = $this->getTestFilePath('WrongNamespaceFixture.php');
    $content = <<<'EOD'
<?php
namespace Wrong\Namespace;
use AKlump\FixtureFramework\Fixture;
#[Fixture(id: 'test_fixture')]
class WrongNamespaceFixture {}
EOD;
    file_put_contents($fixturePath, $content);

    // It should try to import as a document. Since it has no markup, it's imported as a plain document.
    $commandTester = $this->executeCommand(['source document' => $fixturePath]);

    $this->assertStringContainsString('File imported to ./.directio/imported/', $commandTester->getDisplay());
    $this->assertFileDoesNotExist($this->projectRoot . '/.directio/src/Fixture/WrongNamespaceFixture.php');
  }

  public function testImportSingleDocumentWithMarkupSucceeds() {
    $docPath = $this->getTestFilePath('document.md');
    $content = '## My Tasks' . PHP_EOL . '<directio id="task1">Do something</directio>';
    file_put_contents($docPath, $content);

    $commandTester = $this->executeCommand(['source document' => $docPath]);

    $this->assertStringContainsString('File imported to ./.directio/imported/', $commandTester->getDisplay());
    $importedFiles = glob($this->projectRoot . '/.directio/imported/*_document.md');
    $this->assertCount(1, $importedFiles);
  }

  public function testImportDirectoryMixedContent() {
    $sourceDir = $this->getTestFilePath('source_dir/', TRUE);
    $this->assertDirectoryExists($sourceDir);

    // 1. Valid Fixture
    file_put_contents($sourceDir . '/ValidFixture.php', <<<'EOD'
<?php
namespace AKlump\Directio\Fixture;
use AKlump\FixtureFramework\Fixture;
#[Fixture(id: 'valid')]
class ValidFixture {}
EOD
    );

    // 2. Invalid Fixture (Wrong Namespace)
    file_put_contents($sourceDir . '/InvalidNamespace.php', <<<'EOD'
<?php
namespace Wrong\Namespace;
use AKlump\FixtureFramework\Fixture;
#[Fixture(id: 'invalid')]
class InvalidNamespace {}
EOD
    );

    // 3. Document with Markup
    file_put_contents($sourceDir . '/markup.md', '## Tasks' . PHP_EOL . '<directio id="m1">task</directio>');

    // 4. Plain Document (No markup, no fixture)
    file_put_contents($sourceDir . '/plain.txt', 'Just some text');

    // We need to ensure we are in the project root for the command to find .directio
    chdir($this->projectRoot);

    $commandTester = $this->executeCommand(['source document' => $sourceDir]);

    // Check Valid Fixture
    $this->assertFileExists($this->projectRoot . '/.directio/src/Fixture/ValidFixture.php');

    // Check Invalid Fixture (should NOT be in Fixture dir)
    $this->assertFileDoesNotExist($this->projectRoot . '/.directio/src/Fixture/InvalidNamespace.php');

    // Check Document with Markup
    $importedMarkup = glob($this->projectRoot . '/.directio/imported/*_markup.md');
    $this->assertCount(1, $importedMarkup);

    // Check Plain Document (should be ignored in directory import)
    $importedPlain = glob($this->projectRoot . '/.directio/imported/*_plain.txt');
    $this->assertCount(0, $importedPlain);

    $this->assertStringContainsString('Fixture imported', $commandTester->getDisplay());
    $this->assertStringContainsString('File imported', $commandTester->getDisplay());
    $this->assertStringContainsString('Imported 2 of 2 items found', $commandTester->getDisplay());
  }

  public function testImportDirectoryWithSkips() {
    $sourceDir = $this->getTestFilePath('source_dir_skips/', TRUE);
    file_put_contents($sourceDir . '/Fixture1.php', <<<'EOD'
<?php
namespace AKlump\Directio\Fixture;
use AKlump\FixtureFramework\Fixture;
#[Fixture(id: 'f1')]
class Fixture1 {}
EOD
    );
    file_put_contents($sourceDir . '/markup.md', '<directio id="m1"></directio>');

    // First import all
    $this->executeCommand(['source document' => $sourceDir], ['yes', 'yes']);

    // Second import, skip both
    file_put_contents($sourceDir . '/Fixture1.php', <<<'EOD'
<?php
namespace AKlump\Directio\Fixture;
use AKlump\FixtureFramework\Fixture;
#[Fixture(id: 'f1')]
class Fixture1 {
  // Changed
}
EOD
    );
    file_put_contents($sourceDir . '/markup.md', '<directio id="m1">changed</directio>');
    $commandTester = $this->executeCommand(['source document' => $sourceDir], ['no', 'no']);
    $this->assertStringContainsString('All of the 2 items found', $commandTester->getDisplay());
    $this->assertStringContainsString('were skipped or failed', $commandTester->getDisplay());

    // Third import, skip one
    file_put_contents($sourceDir . '/Fixture1.php', <<<'EOD'
<?php
namespace AKlump\Directio\Fixture;
use AKlump\FixtureFramework\Fixture;
#[Fixture(id: 'f1')]
class Fixture1 {
  // Changed again
}
EOD
    );
    file_put_contents($sourceDir . '/markup.md', '<directio id="m1">changed again</directio>');
    $commandTester = $this->executeCommand(['source document' => $sourceDir], ['yes', 'no']);
    $this->assertStringContainsString('Imported 1 of 2 items found', $commandTester->getDisplay());
  }

  public function testImportAsksToDeleteLogsAndDeletesThemWithMessage() {
    $logDir = $this->projectRoot . '/.directio/logs';
    if (!is_dir($logDir)) {
      mkdir($logDir, 0755, TRUE);
    }
    file_put_contents($logDir . '/test.log', 'some logs');
    $this->assertDirectoryExists($logDir);

    $docPath = $this->getTestFilePath('document.md');
    file_put_contents($docPath, 'some content');

    // Answer 'yes' to the prompt
    $commandTester = $this->executeCommand(['source document' => $docPath], ['yes']);

    $this->assertStringContainsString('Delete existing logs?', $commandTester->getDisplay());
    $this->assertStringContainsString('Logs deleted.', $commandTester->getDisplay());
    $this->assertDirectoryExists($logDir);
    $this->assertCount(0, glob($logDir . '/*'));
  }

  public function testImportAsksToDeleteLogsAndDoesNotDeleteThemNoMessage() {
    $logDir = $this->projectRoot . '/.directio/logs';
    if (!is_dir($logDir)) {
      mkdir($logDir, 0755, TRUE);
    }
    file_put_contents($logDir . '/test.log', 'some logs');
    $this->assertDirectoryExists($logDir);

    $docPath = $this->getTestFilePath('document.md');
    file_put_contents($docPath, 'some content');

    // Answer 'no' to the prompt
    $commandTester = $this->executeCommand(['source document' => $docPath], ['no']);

    $this->assertStringContainsString('Delete existing logs?', $commandTester->getDisplay());
    $this->assertStringNotContainsString('Logs deleted.', $commandTester->getDisplay());
    $this->assertDirectoryExists($logDir);
  }

  public function testImportAsksToFlushCacheAndFlushesItWithMessage() {
    $cacheDir = $this->projectRoot . '/.directio/.cache';
    if (!is_dir($cacheDir)) {
      mkdir($cacheDir, 0755, TRUE);
    }
    file_put_contents($cacheDir . '/test.cache', 'some cache');
    $this->assertDirectoryExists($cacheDir);

    $docPath = $this->getTestFilePath('document.md');
    file_put_contents($docPath, 'some content');

    // Answer 'yes' to the prompt
    $commandTester = $this->executeCommand(['source document' => $docPath], ['yes']);

    $this->assertStringContainsString('Flush the cache?', $commandTester->getDisplay());
    $this->assertStringContainsString('Cache flushed.', $commandTester->getDisplay());
    $this->assertDirectoryExists($cacheDir);
    $this->assertCount(0, glob($cacheDir . '/*'));
  }

  public function testImportAsksToFlushCacheAndDoesNotFlushItNoMessage() {
    $cacheDir = $this->projectRoot . '/.directio/.cache';
    if (!is_dir($cacheDir)) {
      mkdir($cacheDir, 0755, TRUE);
    }
    file_put_contents($cacheDir . '/test.cache', 'some cache');
    $this->assertDirectoryExists($cacheDir);

    $docPath = $this->getTestFilePath('document.md');
    file_put_contents($docPath, 'some content');

    // Answer 'no' to the prompt
    $commandTester = $this->executeCommand(['source document' => $docPath], ['no']);

    $this->assertStringContainsString('Flush the cache?', $commandTester->getDisplay());
    $this->assertStringNotContainsString('Cache flushed.', $commandTester->getDisplay());
    $this->assertDirectoryExists($cacheDir);
    $this->assertFileExists($cacheDir . '/test.cache');
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
