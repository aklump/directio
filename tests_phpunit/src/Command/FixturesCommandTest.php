<?php

namespace AKlump\Directio\Tests\Command;

use AKlump\Directio\Command\FixturesCommand;
use AKlump\Directio\IO\InitializeDirectory;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \AKlump\Directio\Command\FixturesCommand
 * @covers \AKlump\Directio\IO\InitializeDirectory
 *
 * @uses   \AKlump\Directio\Config\SpecialAttributes
 * @uses   \AKlump\Directio\FixtureFramework\AbstractFixture
 * @uses   \AKlump\Directio\FixtureFramework\Runtime\FixtureInstantiator
 * @uses   \AKlump\Directio\HTMLElementStyle
 * @uses   \AKlump\Directio\Helper\MarkTaskDoneInDocument
 * @uses   \AKlump\Directio\IO\GetCacheDirectory
 * @uses   \AKlump\Directio\IO\GetDirectioRoot
 * @uses   \AKlump\Directio\IO\GetLogsDirectory
 * @uses   \AKlump\Directio\IO\GetShortPath
 * @uses   \AKlump\Directio\IO\ReadDocument
 * @uses   \AKlump\Directio\IO\WriteDocument
 * @uses   \AKlump\Directio\IO\WriteState
 * @uses   \AKlump\Directio\Lexer\AttributesLexer
 * @uses   \AKlump\Directio\Lexer\TaskLexer
 * @uses   \AKlump\Directio\Model\Document
 * @uses   \AKlump\Directio\Model\TaskState
 * @uses   \AKlump\Directio\TextProcessor\ParseAttributes
 */
class FixturesCommandTest extends TestCase {

  use TestWithFilesTrait;

  private string $projectRoot;

  protected function setUp(): void {
    $this->projectRoot = $this->getTestFilePath('project' . uniqid() . '/', TRUE);
    $this->projectRoot = realpath($this->projectRoot);
    (new InitializeDirectory())($this->projectRoot);

    // DiscoverFixtureDefinitions requires a 'vendor' directory to exist inside .directio
    $vendorDir = $this->projectRoot . '/.directio/vendor';
    if (!is_dir($vendorDir)) {
      mkdir($vendorDir, 0777, TRUE);
    }

    chdir($this->projectRoot);
  }

  protected function tearDown(): void {
    $this->deleteAllTestFiles();
  }

  public function testFixturesCommandUpdatesDocumentMarkup() {
    // 1. Create a document in imported/
    $docPath = $this->projectRoot . '/.directio/imported/tasks.md';
    if (!is_dir(dirname($docPath))) {
      mkdir(dirname($docPath), 0777, TRUE);
    }
    $content = '## Tasks' . PHP_EOL . '<directio id="t1" fixture="f1"></directio>';
    file_put_contents($docPath, $content);

    // 2. Create a dummy fixture class that can be discovered
    // We'll put it in .directio/src/Fixture
    $className = 'F' . str_replace('.', '', uniqid('', TRUE));
    $fixturePath = $this->projectRoot . "/.directio/src/Fixture/$className.php";
    if (!is_dir(dirname($fixturePath))) {
      mkdir(dirname($fixturePath), 0777, TRUE);
    }
    $fixtureContent = <<<EOD
<?php
namespace AKlump\Directio\Fixture;
use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\FixtureFramework\Fixture;

#[Fixture(id: 'f1')]
class $className extends AbstractFixture {
  public function __invoke(): void {
    // Do nothing
  }
}
EOD;
    file_put_contents($fixturePath, $fixtureContent);

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

    // After setupFakeVendor, we need a real composer/autoload_psr4.php for Discovery to work.
    $psr4Path = $this->projectRoot . '/.directio/vendor/composer/autoload_psr4.php';
    if (!is_dir(dirname($psr4Path))) {
      mkdir(dirname($psr4Path), 0777, TRUE);
    }
    file_put_contents($psr4Path, "<?php return ['AKlump\\\\Directio\\\\Fixture\\\\' => [dirname(dirname(__DIR__)) . '/src/Fixture']];");

    $application = new Application();
    $application->add(new FixturesCommand());
    $command = $application->find('fixtures');
    $commandTester = new CommandTester($command);

    $commandTester->setInputs(['y', 'y']);
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
    $docPath = $this->projectRoot . '/.directio/imported/tasks.md';
    if (!is_dir(dirname($docPath))) {
      mkdir(dirname($docPath), 0777, TRUE);
    }
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
    $docPath = $this->projectRoot . '/.directio/imported/tasks.md';
    if (!is_dir(dirname($docPath))) {
      mkdir(dirname($docPath), 0777, TRUE);
    }
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
    $docPath = $this->projectRoot . '/.directio/imported/tasks.md';
    if (!is_dir(dirname($docPath))) {
      mkdir(dirname($docPath), 0777, TRUE);
    }
    $content = '## Tasks' . PHP_EOL
      . '<directio done id="t1" fixture="f1"></directio>' . PHP_EOL
      . '<directio id="t2" fixture="f2"></directio>';
    file_put_contents($docPath, $content);

    // 2. Setup fixtures
    $className = 'F' . str_replace('.', '', uniqid('', TRUE));
    $fixturePath = $this->projectRoot . "/.directio/src/Fixture/$className.php";
    if (!is_dir(dirname($fixturePath))) {
      mkdir(dirname($fixturePath), 0777, TRUE);
    }
    file_put_contents($fixturePath, <<<EOD
<?php
namespace AKlump\Directio\Fixture;
use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\FixtureFramework\Fixture;

#[Fixture(id: 'f2')]
class $className extends AbstractFixture {
  public function __invoke(): void {}
}
EOD
    );
    require_once $fixturePath;

    $this->setupFakeVendor($this->projectRoot);

    // After setupFakeVendor, we need a real composer/autoload_psr4.php for Discovery to work.
    $psr4Path = $this->projectRoot . '/.directio/vendor/composer/autoload_psr4.php';
    if (!is_dir(dirname($psr4Path))) {
      mkdir(dirname($psr4Path), 0777, TRUE);
    }
    file_put_contents($psr4Path, "<?php return ['AKlump\\\\Directio\\\\Fixture\\\\' => [dirname(dirname(__DIR__)) . '/src/Fixture']];");

    // 3. Execute
    $application = new Application();
    $application->add(new FixturesCommand());
    $command = $application->find('fixtures');
    $commandTester = new CommandTester($command);

    $commandTester->setInputs(['y', 'y', 'y', 'y', 'y', 'y']);
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
    $docPath = $this->projectRoot . '/.directio/imported/tasks.md';
    if (!is_dir(dirname($docPath))) {
      mkdir(dirname($docPath), 0777, TRUE);
    }
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

  public function testFixturesCommandAcceptsFilterAsArgument() {
    // 1. Create a document with two different fixtures
    $docPath = $this->projectRoot . '/.directio/imported/tasks.md';
    if (!is_dir(dirname($docPath))) {
      mkdir(dirname($docPath), 0777, TRUE);
    }
    $content = '## Tasks' . PHP_EOL
      . '<directio id="t1" fixture="f1"></directio>' . PHP_EOL
      . '<directio id="t2" fixture="f2"></directio>';
    file_put_contents($docPath, $content);

    // 2. Setup fixtures
    $c1 = 'F' . str_replace('.', '', uniqid('', TRUE));
    $c2 = 'F' . str_replace('.', '', uniqid('', TRUE));
    $f1Path = $this->projectRoot . "/.directio/src/Fixture/$c1.php";
    $f2Path = $this->projectRoot . "/.directio/src/Fixture/$c2.php";
    if (!is_dir(dirname($f1Path))) {
      mkdir(dirname($f1Path), 0777, TRUE);
    }

    file_put_contents($f1Path, "<?php namespace AKlump\Directio\Fixture; use AKlump\Directio\FixtureFramework\AbstractFixture; use AKlump\FixtureFramework\Fixture; #[Fixture(id: 'f1')] class $c1 extends AbstractFixture { public function __invoke(): void {} }");
    file_put_contents($f2Path, "<?php namespace AKlump\Directio\Fixture; use AKlump\Directio\FixtureFramework\AbstractFixture; use AKlump\FixtureFramework\Fixture; #[Fixture(id: 'f2')] class $c2 extends AbstractFixture { public function __invoke(): void {} }");

    // We must manually register these classes because they won't be in the autoloader.
    require_once $f1Path;
    require_once $f2Path;

    $this->setupFakeVendor($this->projectRoot);

    // After setupFakeVendor, we need a real composer/autoload_psr4.php for Discovery to work.
    $psr4Path = $this->projectRoot . '/.directio/vendor/composer/autoload_psr4.php';
    if (!is_dir(dirname($psr4Path))) {
      mkdir(dirname($psr4Path), 0777, TRUE);
    }
    file_put_contents($psr4Path, "<?php return ['AKlump\\\\Directio\\\\Fixture\\\\' => [dirname(dirname(__DIR__)) . '/src/Fixture']];");

    $application = new Application();
    $application->add(new FixturesCommand());
    $command = $application->find('fixtures');
    $commandTester = new CommandTester($command);

    // 3. Execute with filter as argument
    $commandTester->setInputs(['y', 'y']);
    ob_start();
    $commandTester->execute(['filter' => 'f1']);
    ob_end_clean();

    // 4. Verify that only f1 was processed
    $display = $commandTester->getDisplay();
    $this->assertStringContainsString('Marked "t1"', $display);
    $this->assertStringNotContainsString('Marked "t2"', $display);

    // 5. Verify that f2 is still not done in document
    $updatedContent = file_get_contents($docPath);
    $this->assertStringContainsString('<directio done id="t1" fixture="f1">', $updatedContent);
    $this->assertStringContainsString('<directio id="t2" fixture="f2">', $updatedContent);
    $this->assertStringNotContainsString('<directio done id="t2" fixture="f2">', $updatedContent);
  }

  private function setupFakeVendor(string $root) {
    $vendor = $root . '/.directio/vendor';
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
