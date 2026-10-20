<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Command;

use AKlump\Directio\Command\ConvertCommand;
use AKlump\Directio\Config\SpecialAttributes;
use AKlump\Directio\Model\Document;
use AKlump\Directio\TextProcessor\ParseTasks;
use AKlump\Directio\TextProcessor\ValidateTaskSyntax;
use AKlump\Directio\Tests\TestingTraits\TestWithFilesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \AKlump\Directio\Command\ConvertCommand
 *
 * @uses \AKlump\Directio\Config\SpecialAttributes
 * @uses \AKlump\Directio\IO\GetShortPath
 * @uses \AKlump\Directio\IO\ReadDocument
 * @uses \AKlump\Directio\IO\WriteDocument
 * @uses \AKlump\Directio\Model\Attributes
 * @uses \AKlump\Directio\Model\Document
 * @uses \AKlump\Directio\Model\Heading
 * @uses \AKlump\Directio\Model\Task
 * @uses \AKlump\Directio\TextProcessor\HasLegacyMarkup
 * @uses \AKlump\Directio\TextProcessor\ParseAttributes
 * @uses \AKlump\Directio\TextProcessor\ParseTasks
 * @uses \AKlump\Directio\TextProcessor\ScanHeadings
 * @uses \AKlump\Directio\TextProcessor\ScanLines
 * @uses \AKlump\Directio\TextProcessor\ValidateTaskSyntax
 */
class ConvertCommandTest extends TestCase {

  use TestWithFilesTrait;

  protected function setUp(): void {
    $this->getTestFilePath('project/', TRUE);
  }

  protected function tearDown(): void {
    $this->deleteAllTestFiles();
  }

  public function testCase1HeadingFirstConvertsCleanly() {
    $path = $this->getTestFilePath('project/document.md');
    file_put_contents($path, <<<'EOD'
<directio id="ssh_on" redo="P1D">

## Enable SSH Access

If applicable turn on SSH access to the server.

</directio>
EOD);

    $commandTester = $this->executeCommand(['file' => $path], ['yes']);

    $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    $this->assertSame(<<<'EOD'
## Enable SSH Access {#ssh_on redo=P1D}

If applicable turn on SSH access to the server.

EOD, file_get_contents($path));
  }

  public function testCase2HeadlessInventsHeadingOneLevelDeeperThanContext() {
    $path = $this->getTestFilePath('project/document.md');
    file_put_contents($path, <<<'EOD'
# Doc Title

## Server Setup

Some intro text.

<directio id="bump_unbuild" redo="P1M">
* do a thing
* do another thing
</directio>

## Finalize
EOD);

    $commandTester = $this->executeCommand(['file' => $path], ['yes']);

    $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    $content = file_get_contents($path);
    $this->assertStringContainsString('### Bump unbuild {#bump_unbuild redo=P1M}', $content);
    $this->assertStringContainsString('* do a thing', $content);
    $this->assertStringContainsString('* do another thing', $content);
    $this->assertStringContainsString('invented heading', $commandTester->getDisplay());
  }

  public function testCase2HeadlessNeverDeeperThanBodyHeadingOrSix() {
    // The invented heading must stay shallower than the deepest heading
    // already present in the task's own body.
    $path = $this->getTestFilePath('project/document.md');
    file_put_contents($path, <<<'EOD'
# Doc Title

## Section

<directio id="with_sub_headings">
Some intro bullet.
### Existing Sub Heading
Body text.
</directio>
EOD);
    $this->executeCommand(['file' => $path], ['yes']);
    $content = file_get_contents($path);
    // A naive "one deeper than context" would give level 3, as deep as
    // the heading already inside the body; the cap keeps it at 2.
    $this->assertStringContainsString('## With sub headings {#with_sub_headings}', $content);
    $this->assertStringNotContainsString('### With sub headings', $content);

    // And it never goes past level 6, even when the context is already
    // at level 6.
    $deep_path = $this->getTestFilePath('project/deep.md');
    file_put_contents($deep_path, <<<'EOD'
# L1

## L2

### L3

#### L4

##### L5

###### L6

<directio id="too_deep">
* a bullet
</directio>
EOD);
    $this->executeCommand(['file' => $deep_path], ['yes']);
    $deep_content = file_get_contents($deep_path);
    $this->assertStringContainsString('###### Too deep {#too_deep}', $deep_content);
  }

  public function testCase2SameLevelSiblingTreatedAsHeadless() {
    // setup_ldp_files_server-shaped: a first heading with a same-level
    // sibling later in the body must not be promoted, or the sibling
    // would end the task prematurely under the new "same or higher level
    // ends a task" rule.
    $path = $this->getTestFilePath('project/document.md');
    file_put_contents($path, <<<'EOD'
# Doc Title

## Finalize Section

<directio id="setup_ldp_files_server">

### Local Setup

Do the local setup steps.

### Live Dev Porter

Do the remote steps.

</directio>

## Release Testing
EOD);

    $commandTester = $this->executeCommand(['file' => $path], ['yes']);

    $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    $content = file_get_contents($path);
    $this->assertStringContainsString('## Setup ldp files server {#setup_ldp_files_server}', $content);
    $this->assertStringContainsString('### Local Setup', $content);
    $this->assertStringContainsString('### Live Dev Porter', $content);
    $this->assertStringContainsString('Do the local setup steps.', $content);
    $this->assertStringContainsString('Do the remote steps.', $content);
  }

  public function testCase3RefusesAndWritesNothingWithTheRightLineNumber() {
    $path = $this->getTestFilePath('project/document.md');
    $original = <<<'EOD'
<directio id="foo">

## Foo Task

Body text.

</directio>
Stray orphan content here.

## Next Section
EOD;
    file_put_contents($path, $original);

    $commandTester = $this->executeCommand(['file' => $path]);

    $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
    $this->assertStringContainsString('Line 8:', $commandTester->getDisplay());
    $this->assertSame($original, file_get_contents($path));
  }

  public function testCase3RefusesContentUnderADeeperHeadingAfterTheCloseTag() {
    // "### Notes" is deeper than the task's "## A", so under the new rule
    // it and its text would be inside the task and go with a later cut.
    $path = $this->getTestFilePath('project/document.md');
    $original = <<<'EOD'
<directio id="a">
## A
body
</directio>

### Notes
notes text

<directio id="b">
## B
body
</directio>
EOD;
    file_put_contents($path, $original);

    $commandTester = $this->executeCommand(['file' => $path]);

    $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
    $this->assertStringContainsString('Line 6:', $commandTester->getDisplay());
    $this->assertSame($original, file_get_contents($path));
  }

  public function testHeadlessBodyWithALevelOneHeadingIsRefused() {
    $path = $this->getTestFilePath('project/document.md');
    $original = <<<'EOD'
# Doc

<directio id="x">
intro
# Inner H1
more
</directio>
EOD;
    file_put_contents($path, $original);

    $commandTester = $this->executeCommand(['file' => $path]);

    $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
    $this->assertStringContainsString('Line 3:', $commandTester->getDisplay());
    $this->assertStringContainsString('level-1 heading', $commandTester->getDisplay());
    $this->assertSame($original, file_get_contents($path));
  }

  public function testOwnHeadingDeeperThanThePreviousTaskIsRefused() {
    $path = $this->getTestFilePath('project/document.md');
    $original = <<<'EOD'
<directio id="a">
## A
body
</directio>

<directio id="b">
### B
body
</directio>
EOD;
    file_put_contents($path, $original);

    $commandTester = $this->executeCommand(['file' => $path]);

    $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
    $this->assertStringContainsString('Line 7:', $commandTester->getDisplay());
    $this->assertStringContainsString('nest', $commandTester->getDisplay());
    $this->assertSame($original, file_get_contents($path));
  }

  public function testHeadlessTaskAfterAShallowTaskDoesNotNestInsideIt() {
    $path = $this->getTestFilePath('project/document.md');
    file_put_contents($path, <<<'EOD'
# Doc

## Section

<directio id="a">
# A
body
</directio>

<directio id="b">
body
</directio>
EOD);

    $commandTester = $this->executeCommand(['file' => $path], ['yes']);

    $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    $content = file_get_contents($path);
    $this->assertStringContainsString("# A {#a}\n", $content);
    $this->assertStringContainsString("# B {#b}\n", $content);
    $this->assertSame(['a', 'b'], (new Document())->setContent($content)->getIds());
  }

  public function testSetextHeadingFirstIsPromotedToAtx() {
    $path = $this->getTestFilePath('project/document.md');
    file_put_contents($path, <<<'EOD'
<directio id="s">
Title
=====
body
</directio>
EOD);

    $commandTester = $this->executeCommand(['file' => $path], ['yes']);

    $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    $this->assertSame("# Title {#s}\n\nbody\n", file_get_contents($path));
    $this->assertStringContainsString('0 invented', $commandTester->getDisplay());
  }

  public function testInvalidRedoIsReportedNotThrown() {
    $path = $this->getTestFilePath('project/document.md');
    $original = <<<'EOD'
<directio id="x" redo="">
body
</directio>
EOD;
    file_put_contents($path, $original);

    $commandTester = $this->executeCommand(['file' => $path]);

    $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
    $this->assertStringContainsString('Cannot convert', $commandTester->getDisplay());
    $this->assertSame($original, file_get_contents($path));
  }

  public function testUnclosedTagIsReportedNotThrown() {
    $path = $this->getTestFilePath('project/document.md');
    $original = "line one\n<directio id=\"x\">\nbody\n";
    file_put_contents($path, $original);

    $commandTester = $this->executeCommand(['file' => $path]);

    $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
    $this->assertStringContainsString('line 2', $commandTester->getDisplay());
    $this->assertSame($original, file_get_contents($path));
  }

  public function testLegacyMarkupInsideAFenceIsLeftAlone() {
    $path = $this->getTestFilePath('project/document.md');
    $original = <<<'EOD'
## Foo {#foo}

```html
<directio id="old">
</directio>
```
EOD;
    file_put_contents($path, $original);

    $commandTester = $this->executeCommand(['file' => $path]);

    $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    $this->assertStringContainsString('nothing to convert', $commandTester->getDisplay());
    $this->assertSame($original, file_get_contents($path));
  }

  public function testAlreadyOnNewSyntaxIsANoop() {
    $path = $this->getTestFilePath('project/document.md');
    $original = <<<'EOD'
## Foo Task {#foo}

Body text.
EOD;
    file_put_contents($path, $original);

    $commandTester = $this->executeCommand(['file' => $path]);

    $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    $this->assertStringContainsString('nothing to convert', $commandTester->getDisplay());
    $this->assertSame($original, file_get_contents($path));
  }

  public function testAttributesArePreservedByteForValueAcrossConversion() {
    $path = $this->getTestFilePath('project/document.md');
    file_put_contents($path, <<<'EOD'
<directio x id="bump_perms" redo="P1M" fixture="my_fixture">

## Permissions

Body.

</directio>
EOD);

    $this->executeCommand(['file' => $path], ['yes']);

    $document = (new Document())->setContent(file_get_contents($path));
    $tasks = (new ParseTasks())($document->getContent());
    $this->assertCount(1, $tasks);
    $attributes = $tasks[0]->getAttributes();
    $this->assertSame('bump_perms', SpecialAttributes::extractId($attributes));
    $this->assertSame('P1M', SpecialAttributes::extractExpires($attributes));
    $this->assertSame('my_fixture', SpecialAttributes::extractFixture($attributes));
    $this->assertTrue(SpecialAttributes::extractDone($attributes));
  }

  public function testConvertedOutputPassesValidationAndIdsMatchTheOriginal() {
    $path = $this->getTestFilePath('project/document.md');
    $original = <<<'EOD'
<feature>
---

# Example Document 2026-10-20

<directio id="ssh_on" redo="P1D">

## Enable SSH Access

If applicable turn on SSH access to the server.

</directio>

<directio id="bump_unbuild" redo="P1M">
* do a thing
</directio>

<directio x id="bump_perms" redo="P1M">

## Permissions

Body.

</directio>
EOD;
    file_put_contents($path, $original);

    // A thin, independent regex extraction of the ids from the ORIGINAL
    // old-syntax markup, so this assertion doesn't rely on the command's
    // own compatibility reader to check itself.
    preg_match_all('/<directio[^>]*\bid="([^"]*)"/', $original, $matches);
    $original_ids = $matches[1];

    $commandTester = $this->executeCommand(['file' => $path], ['yes']);
    $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());

    $converted_content = file_get_contents($path);
    // Must not throw LegacySyntaxException (or any other exception).
    (new ValidateTaskSyntax())($converted_content);

    $document = (new Document())->setContent($converted_content);
    $this->assertSame($original_ids, $document->getIds());
  }

  public function testOverwritePromptDeclined() {
    $path = $this->getTestFilePath('project/document.md');
    $original = <<<'EOD'
<directio id="foo">

## Foo

Body.

</directio>
EOD;
    file_put_contents($path, $original);

    $commandTester = $this->executeCommand(['file' => $path], ['no']);

    $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
    $this->assertSame($original, file_get_contents($path));
  }

  public function testMissingFileFails() {
    $commandTester = $this->executeCommand(['file' => $this->getTestFilePath('project/', TRUE) . 'does-not-exist.md']);

    $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
    $this->assertStringContainsString('does not exist', $commandTester->getDisplay());
  }

  private function executeCommand(array $arguments, array $inputs = []): CommandTester {
    $application = new Application();
    $application->add(new ConvertCommand());
    $command = $application->find('convert');
    $commandTester = new CommandTester($command);
    $commandTester->setInputs($inputs);
    $commandTester->execute($arguments);

    return $commandTester;
  }

}
