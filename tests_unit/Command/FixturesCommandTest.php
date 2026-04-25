<?php

namespace AKlump\Directio\Tests\Unit\Command;

use AKlump\Directio\Command\FixturesCommand;
use AKlump\Directio\IO\InitializeDirectory;
use AKlump\Directio\Tests\Unit\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

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
    
    $commandTester->execute([], ['capture_stderr_separately' => TRUE]);
    
    fwrite(STDERR, $commandTester->getDisplay());
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
