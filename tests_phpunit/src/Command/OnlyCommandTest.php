<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Command;

use AKlump\Directio\Command\OnlyCommand;
use AKlump\Directio\IO\InitializeDirectory;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \AKlump\Directio\Command\OnlyCommand
 * @covers \AKlump\Directio\Command\InitializedDirCommandTrait
 *
 * @uses \AKlump\Directio\IO\InitializeDirectory
 * @uses \AKlump\Directio\IO\GetDirectioRoot
 * @uses \AKlump\Directio\IO\GetShortPath
 * @uses \AKlump\Directio\IO\ReadDocument
 * @uses \AKlump\Directio\IO\WriteDocument
 * @uses \AKlump\Directio\IO\GetLogsDirectory
 * @uses \AKlump\Directio\Config\Names
 * @uses \AKlump\Directio\Config\SpecialAttributes
 * @uses \AKlump\Directio\Model\Document
 * @uses \AKlump\Directio\Model\Heading
 * @uses \AKlump\Directio\Model\Task
 * @uses \AKlump\Directio\TextProcessor\HasLegacyMarkup
 * @uses \AKlump\Directio\TextProcessor\ParseAttributes
 * @uses \AKlump\Directio\TextProcessor\ParseTasks
 * @uses \AKlump\Directio\TextProcessor\ScanHeadings
 * @uses \AKlump\Directio\TextProcessor\ScanLines
 * @uses \AKlump\Directio\TextProcessor\ValidateTaskSyntax
 * @uses \AKlump\Directio\Command\ImportCommand
 * @uses \AKlump\Directio\Command\UpdateCommand
 */
class OnlyCommandTest extends TestCase {

  use TestWithFilesTrait;

  private string $projectRoot;

  private string $importedDir;

  protected function setUp(): void {
    $this->projectRoot = $this->getTestFilePath('project/', TRUE);
    chdir($this->projectRoot);
    (new InitializeDirectory())($this->projectRoot);
    $this->importedDir = $this->projectRoot . '/.directio/imported';
    mkdir($this->importedDir);
  }

  protected function tearDown(): void {
    $this->deleteAllTestFiles();
  }

  public function testRemovesTasksWithoutTheFlagAndWritesNoState() {
    $path = $this->writeDocument('tasks.md', "# Intro\n\nKeep me.\n\n## Pre config {#pre redo=P1D security}\n\nbody a\n\n## Deploy {#deploy}\n\nbody b\n\n## Audit {#audit security=high}\n\nbody c\n");

    $state = $this->projectRoot . '/.directio/state.sqlite';
    $state_before = file_exists($state) ? md5_file($state) : NULL;

    $tester = $this->createTester();
    $tester->setInputs(['yes']);
    $this->assertSame(0, $tester->execute(['attributes' => ['security']]));

    $this->assertSame("# Intro\n\nKeep me.\n\n## Pre config {#pre redo=P1D security}\n\nbody a\n\n## Audit {#audit security=high}\n\nbody c\n", file_get_contents($path));
    $display = $tester->getDisplay();
    $this->assertStringContainsString('keep 2, remove 1', $display);
    $this->assertStringContainsString('✂️ deploy', $display);
    $this->assertSame($state_before, file_exists($state) ? md5_file($state) : NULL);
  }

  public function testNameValueMatchesOnlyThatValue() {
    $path = $this->writeDocument('tasks.md', "## A {#a security=high}\n\n## B {#b security=low}\n\n## C {#c security}\n");

    $tester = $this->createTester();
    $tester->execute(['attributes' => ['security=high']], ['interactive' => FALSE]);

    $this->assertSame("## A {#a security=high}\n", file_get_contents($path));
  }

  public function testSeveralAttributesKeepATaskWithAnyOfThem() {
    $path = $this->writeDocument('tasks.md', "## A {#a security}\n\n## B {#b perf}\n\n## C {#c}\n");

    $tester = $this->createTester();
    $tester->execute(['attributes' => ['security', 'perf']], ['interactive' => FALSE]);

    $this->assertSame("## A {#a security}\n\n## B {#b perf}\n", file_get_contents($path));
  }

  public function testAppliesToEveryImportedDocument() {
    $one = $this->writeDocument('one.md', "## A {#a security}\n\n## B {#b}\n");
    $two = $this->writeDocument('two.md', "## C {#c}\n");

    $tester = $this->createTester();
    $tester->execute(['attributes' => ['security']], ['interactive' => FALSE]);

    $this->assertSame("## A {#a security}\n", file_get_contents($one));
    $this->assertSame('', file_get_contents($two));
  }

  public function testNoMatchingTaskChangesNothing() {
    $content = "## A {#a}\n\n## B {#b}\n";
    $path = $this->writeDocument('tasks.md', $content);

    $tester = $this->createTester();
    $this->assertSame(1, $tester->execute(['attributes' => ['securty']], ['interactive' => FALSE]));

    $this->assertSame($content, file_get_contents($path));
    $this->assertStringContainsString('No imported task has securty; nothing was removed.', $tester->getDisplay());
  }

  public function testEveryTaskMatchingRemovesNothing() {
    $tester = $this->createTester();
    $this->writeDocument('tasks.md', "## A {#a security}\n");
    $this->assertSame(0, $tester->execute(['attributes' => ['security']], ['interactive' => FALSE]));
    $this->assertStringContainsString('nothing to remove', $tester->getDisplay());
  }

  public function testDecliningTheConfirmationChangesNothing() {
    $content = "## A {#a security}\n\n## B {#b}\n";
    $path = $this->writeDocument('tasks.md', $content);

    $tester = $this->createTester();
    $tester->setInputs(['no']);
    $this->assertSame(1, $tester->execute(['attributes' => ['security']]));

    $this->assertSame($content, file_get_contents($path));
    $this->assertStringContainsString('Nothing was removed.', $tester->getDisplay());
  }

  public function testWarnsWhenRemovingADoneTaskNotYetSaved() {
    $this->writeDocument('tasks.md', "## A {#a security}\n\n## B {x #b}\n\n## C {#c}\n");

    $tester = $this->createTester();
    $tester->execute(['attributes' => ['security']], ['interactive' => FALSE]);

    $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
    $this->assertStringContainsString('Marked done but not yet saved by "update": b.', $display);
    $this->assertStringNotContainsString('saved by "update": b, c', $display);
  }

  public function testInvalidSyntaxWritesNothing() {
    $good = $this->writeDocument('a.md', "## A {#a security}\n\n## B {#b}\n");
    $this->writeDocument('b.md', "## Broken {#c 'bad'}\n");

    $tester = $this->createTester();
    $this->assertSame(1, $tester->execute(['attributes' => ['security']], ['interactive' => FALSE]));

    $this->assertSame("## A {#a security}\n\n## B {#b}\n", file_get_contents($good));
  }

  public function testRejectsAnInvalidAttributeName() {
    $tester = $this->createTester();
    $this->assertSame(1, $tester->execute(['attributes' => ['#foo']], ['interactive' => FALSE]));
    $this->assertStringContainsString('Not an attribute name: "#foo"', $tester->getDisplay());
  }

  public function testWithIsAnAlias() {
    $application = new Application();
    $application->add(new OnlyCommand());
    $this->assertInstanceOf(OnlyCommand::class, $application->find('with'));
  }

  private function writeDocument(string $name, string $content): string {
    $path = $this->importedDir . '/' . $name;
    file_put_contents($path, $content);

    return $path;
  }

  private function createTester(): CommandTester {
    $application = new Application();
    $application->add(new OnlyCommand());

    return new CommandTester($application->find('only'));
  }

}
