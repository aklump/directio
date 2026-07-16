<?php

namespace AKlump\Directio\Tests\Command;

use AKlump\Directio\Command\UpdateCommand;
use AKlump\Directio\IO\InitializeDirectory;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \AKlump\Directio\Command\UpdateCommand
 * @covers \AKlump\Directio\Command\InitializedDirCommandTrait
 *
 * @uses \AKlump\Directio\IO\InitializeDirectory
 * @uses \AKlump\Directio\IO\GetDirectioRoot
 * @uses \AKlump\Directio\IO\GetShortPath
 * @uses \AKlump\Directio\IO\ReadDocument
 * @uses \AKlump\Directio\IO\WriteDocument
 * @uses \AKlump\Directio\IO\WriteState
 * @uses \AKlump\Directio\Config\Names
 * @uses \AKlump\Directio\Config\SpecialAttributes
 * @uses \AKlump\Directio\Model\Document
 * @uses \AKlump\Directio\Model\TaskState
 * @uses \AKlump\Directio\TextProcessor\ScanForCompletedTasks
 * @uses \AKlump\Directio\TextProcessor\ValidateTaskSyntax
 * @uses \AKlump\Directio\IO\GetLogsDirectory
 * @uses \AKlump\Directio\Lexer\TaskLexer
 * @uses \AKlump\Directio\Lexer\AttributesLexer
 * @uses \AKlump\Directio\TextProcessor\ParseAttributes
 * @uses \AKlump\Directio\Traits\HasStyleTrait
 * @uses \AKlump\Directio\HTMLElementStyle
 * @uses \AKlump\Directio\Command\InitializeCommand
 */
class UpdateCommandTest extends TestCase {

  use TestWithFilesTrait;

  private string $projectRoot;

  protected function setUp(): void {
    $this->projectRoot = $this->getTestFilePath('project/', TRUE);
    chdir($this->projectRoot);
  }

  protected function tearDown(): void {
    $this->deleteAllTestFiles();
  }

  public function testUpdateCommandWithCompletedTasks() {
    (new InitializeDirectory())($this->projectRoot);
    $importedDir = $this->projectRoot . '/.directio/imported';
    mkdir($importedDir);

    $docPath = $importedDir . '/tasks.md';
    $content = '## Tasks' . PHP_EOL . '<directio id="task1" done>Task 1</directio>' . PHP_EOL . '<directio id="task2">Task 2</directio>';
    file_put_contents($docPath, $content);

    $application = new Application();
    $application->add(new UpdateCommand());

    $command = $application->find('update');
    $commandTester = new CommandTester($command);
    $commandTester->execute([]);

    $this->assertStringContainsString('✅ task1', $commandTester->getDisplay());
    $this->assertStringNotContainsString('task2', $commandTester->getDisplay());

    $updatedContent = file_get_contents($docPath);
    $this->assertStringNotContainsString('task1', $updatedContent);
    $this->assertStringContainsString('task2', $updatedContent);

    $stateFile = $this->projectRoot . '/.directio/state.sqlite';
    $this->assertFileExists($stateFile);
    // Check if state is written - we can use sqlite3 to check or just assume if it didn't throw it's okay for now,
    // but better to verify.
    $db = new \PDO('sqlite:' . $stateFile);
    $stmt = $db->query("SELECT * FROM task_state WHERE id = 'task1'");
    $row = $stmt->fetch();
    $this->assertNotEmpty($row);
    $this->assertEquals('task1', $row['id']);
  }

  public function testUpdateCommandWithNoDocuments() {
    (new InitializeDirectory())($this->projectRoot);

    $application = new Application();
    $application->add(new UpdateCommand());

    $command = $application->find('update');
    $commandTester = new CommandTester($command);
    $commandTester->execute([]);

    $this->assertStringContainsString('No documents in', $commandTester->getDisplay());
    $this->assertEquals(1, $commandTester->getStatusCode());
  }

  public function testUpdateCommandWithExpiresAttribute() {
    (new InitializeDirectory())($this->projectRoot);
    $importedDir = $this->projectRoot . '/.directio/imported';
    mkdir($importedDir);

    $docPath = $importedDir . '/tasks.md';
    // Use an ISO 8601 duration
    $content = '## Tasks' . PHP_EOL . '<directio id="task_exp" done redo="P1D">Task with expiry</directio>';
    file_put_contents($docPath, $content);

    $application = new Application();
    $application->add(new UpdateCommand());

    $command = $application->find('update');
    $commandTester = new CommandTester($command);
    $commandTester->execute([]);

    $this->assertStringContainsString('✅ task_exp', $commandTester->getDisplay());

    $stateFile = $this->projectRoot . '/.directio/state.sqlite';
    $db = new \PDO('sqlite:' . $stateFile);
    $stmt = $db->query("SELECT * FROM task_state WHERE id = 'task_exp'");
    $row = $stmt->fetch();
    $this->assertNotEmpty($row);
    $this->assertNotEmpty($row['redo']);

    $redoDate = new \DateTime($row['redo']);
    $now = new \DateTime();
    $this->assertGreaterThan($now, $redoDate);
  }
}
