<?php

namespace AKlump\Directio\Tests\Unit\Command;

use AKlump\Directio\Command\FixturesCommand;
use AKlump\Directio\IO\InitializeDirectory;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \AKlump\Directio\Command\FixturesCommand
 * @covers \AKlump\Directio\IO\InitializeDirectory
 */
class FixturesCommandTest extends TestCase {

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

  public function testFixturesCommandUpdatesDocumentMarkup() {
    // 1. Create a document in imported/
    $importedDir = $this->projectRoot . '/.directio/imported';
    if (!is_dir($importedDir)) {
      mkdir($importedDir, 0755, TRUE);
    }
    $docPath = $importedDir . '/tasks.md';
    $content = '## Tasks' . PHP_EOL . '<directio id="t1" fixture="f1"></directio>';
    file_put_contents($docPath, $content);

    // 2. Create a dummy fixture class that can be discovered
    // We'll put it in .directio/src/Fixture
    $fixtureDir = $this->projectRoot . '/.directio/src/Fixture';
    if (!is_dir($fixtureDir)) {
      mkdir($fixtureDir, 0755, TRUE);
    }
    $fixturePath = $fixtureDir . '/F1.php';
    $fixtureContent = <<<'EOD'
<?php
namespace AKlump\Directio\Fixture;
use AKlump\FixtureFramework\AbstractFixture;
use AKlump\FixtureFramework\Fixture;

#[Fixture(id: 'f1')]
class F1 extends AbstractFixture {
  public function __invoke(): void {
    // Do nothing
  }
}
EOD;
    file_put_contents($fixturePath, $fixtureContent);

    // 3. Setup fake vendor and autoloader
    $this->setupFakeVendor($this->projectRoot);

    // Force include the fixture so class_exists returns true and it's discoverable
    require_once $fixturePath;

    // To make it discoverable by FixtureDiscovery, we'd need it in the autoloader.
    // However, FixturesCommand uses DiscoverFixtureDefinitions which scans vendor/composer/autoload_psr4.php.
    // This is hard to mock in a unit test without more setup.

    // Instead of a full integration test that relies on the real discovery,
    // I will mock the runFixtures method or parts of the discovery if possible.
    // But FixturesCommand doesn't have many injection points.

    // Actually, I can mock the Application and the Command.
    $command = $this->getMockBuilder(FixturesCommand::class)
      ->onlyMethods(['runFixtures'])
      ->getMock();

    // We want to simulate that runFixtures was called and it "succeeded".
    // But we need to make sure the document update logic (which is inside runFixtures) runs.

    // Wait, the logic is IN runFixtures. If I mock it, I skip the logic.
    // I should probably move the logic to a separate method or class if I want it testable without full fixture framework.

    // Actually, let's see if I can use a real run but with a fake vendor dir?
    // FixturesCommand:115: $vendor_dir = $base_dir . DIRECTORY_SEPARATOR . 'vendor';

    // If I create a fake vendor dir in project/ with the necessary composer files.
    $this->setupFakeVendor($this->projectRoot);

    $application = new Application();
    $application->add(new FixturesCommand());
    $command = $application->find('fixtures');
    $commandTester = new CommandTester($command);

    ob_start();
    $commandTester->execute(['--flush' => TRUE], ['capture_stderr_separately' => TRUE]);
    ob_end_clean();

    $this->assertStringContainsString('Marked "t1" as done in ./.directio/imported/tasks.md', $commandTester->getDisplay());

    $updatedContent = file_get_contents($docPath);
    $this->assertStringContainsString('<directio done id="t1" fixture="f1">', $updatedContent);

    // 4. Verify state database
    $statePath = $this->projectRoot . '/.directio/state.sqlite';
    $this->assertFileExists($statePath);
    $db = new \SQLite3($statePath);
    $result = $db->query("SELECT * FROM task_state WHERE id = 't1'");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $this->assertNotEmpty($row);
    $this->assertEquals('t1', $row['id']);
    $this->assertNotEmpty($row['completed']);
  }

  public function testFixturesWithDoneAttributeAreSkipped() {
    // 1. Create a document with a fixture that is already marked as done
    $importedDir = $this->projectRoot . '/.directio/imported';
    if (!is_dir($importedDir)) {
      mkdir($importedDir, 0755, TRUE);
    }
    $docPath = $importedDir . '/tasks.md';
    $content = '## Tasks' . PHP_EOL . '<directio done id="t1" fixture="f1"></directio>';
    file_put_contents($docPath, $content);

    // 2. Setup application and command
    $application = new Application();
    $application->add(new FixturesCommand());
    $command = $application->find('fixtures');
    $commandTester = new CommandTester($command);

    // 3. Execute the command
    $commandTester->execute([]);

    // 4. Verify output
    $this->assertStringContainsString('All fixtures have been marked as done. Nothing more to do.', $commandTester->getDisplay());
    $this->assertStringNotContainsString('Marked "t1" as done', $commandTester->getDisplay());
  }

  /**
   * @dataProvider provideDoneAttributes
   */
  public function testFixturesWithAlternativeDoneAttributesAreSkipped(string $attribute) {
    $importedDir = $this->projectRoot . '/.directio/imported';
    if (!is_dir($importedDir)) {
      mkdir($importedDir, 0755, TRUE);
    }
    $docPath = $importedDir . '/tasks.md';
    $content = "## Tasks\n<directio $attribute id=\"t1\" fixture=\"f1\"></directio>";
    file_put_contents($docPath, $content);

    $application = new Application();
    $application->add(new FixturesCommand());
    $command = $application->find('fixtures');
    $commandTester = new CommandTester($command);

    $commandTester->execute([]);

    $this->assertStringContainsString('All fixtures have been marked as done. Nothing more to do.', $commandTester->getDisplay());
  }

  public function provideDoneAttributes(): array {
    return [
      ['done'],
      ['complete'],
      ['x'],
    ];
  }

  public function testFixturesMixedDoneAndNotDone() {
    // 1. Create a document with mixed done/not-done fixtures
    $importedDir = $this->projectRoot . '/.directio/imported';
    if (!is_dir($importedDir)) {
      mkdir($importedDir, 0755, TRUE);
    }
    $docPath = $importedDir . '/tasks.md';
    $content = '## Tasks' . PHP_EOL
      . '<directio done id="t1" fixture="f1"></directio>' . PHP_EOL
      . '<directio id="t2" fixture="f2"></directio>';
    file_put_contents($docPath, $content);

    // 2. Setup fixtures
    $fixtureDir = $this->projectRoot . '/.directio/src/Fixture';
    if (!is_dir($fixtureDir)) {
      mkdir($fixtureDir, 0755, TRUE);
    }
    file_put_contents($fixtureDir . '/F2.php', <<<'EOD'
<?php
namespace AKlump\Directio\Fixture;
use AKlump\FixtureFramework\AbstractFixture;
use AKlump\FixtureFramework\Fixture;

#[Fixture(id: 'f2')]
class F2 extends AbstractFixture {
  public function __invoke(): void {}
}
EOD
    );
    require_once $fixtureDir . '/F2.php';

    $this->setupFakeVendor($this->projectRoot);

    // 3. Execute
    $application = new Application();
    $application->add(new FixturesCommand());
    $command = $application->find('fixtures');
    $commandTester = new CommandTester($command);

    ob_start();
    $commandTester->execute(['--flush' => TRUE]);
    ob_end_clean();

    // 4. Verify
    $display = $commandTester->getDisplay();
    $this->assertStringNotContainsString('Marked "t1"', $display);
    $this->assertStringContainsString('Marked "t2"', $display);

    $updatedContent = file_get_contents($docPath);
    $this->assertStringContainsString('<directio done id="t1" fixture="f1">', $updatedContent);
    $this->assertStringContainsString('<directio done id="t2" fixture="f2">', $updatedContent);
  }

  public function testMessageWhenNoFixturesAtAll() {
    $importedDir = $this->projectRoot . '/.directio/imported';
    if (!is_dir($importedDir)) {
      mkdir($importedDir, 0755, TRUE);
    }
    $docPath = $importedDir . '/tasks.md';
    $content = '## Tasks' . PHP_EOL . 'No directio tags here.';
    file_put_contents($docPath, $content);

    $application = new Application();
    $application->add(new FixturesCommand());
    $command = $application->find('fixtures');
    $commandTester = new CommandTester($command);

    $commandTester->execute([]);

    $this->assertStringContainsString('No fixtures found in documents.', $commandTester->getDisplay());
    $this->assertStringNotContainsString('All fixtures have been marked as done.', $commandTester->getDisplay());
  }

  private function setupFakeVendor(string $root) {
    $vendor = $root . '/vendor';
    $composer = $vendor . '/composer';
    if (!is_dir($composer)) {
      mkdir($composer, 0755, TRUE);
    }

    // We need autoload_psr4.php to point to our fixture
    $psr4 = <<<EOD
<?php
return [
    'AKlump\\\\Directio\\\\Fixture\\\\' => ['$root/.directio/src/Fixture'],
];
EOD;
    file_put_contents($composer . '/autoload_psr4.php', $psr4);
    file_put_contents($composer . '/autoload_classmap.php', "<?php return [];");
    file_put_contents($vendor . '/autoload.php', "<?php // Dummy");
  }
}
