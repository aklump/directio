<?php

namespace AKlump\Directio\Tests\Command;

use AKlump\Directio\Command\InitializedDirCommandTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \AKlump\Directio\Command\InitializedDirCommandTrait
 * @uses \AKlump\Directio\IO\GetDirectioRoot
 * @uses \AKlump\Directio\IO\GetShortPath
 * @uses \AKlump\Directio\Command\InitializeCommand
 * @uses \AKlump\Directio\IO\InitializeDirectory
 * @uses \AKlump\Directio\Config\Names
 * @uses \AKlump\Directio\IO\GetLogsDirectory
 */
class InitializedDirCommandTraitTest extends TestCase {

  private string $tempDir;
  private string $originalDir;

  protected function setUp(): void {
    $this->originalDir = getcwd();
    $this->tempDir = sys_get_temp_dir() . '/directio_trait_test_' . uniqid();
    mkdir($this->tempDir, 0777, TRUE);
    chdir($this->tempDir);
  }

  protected function tearDown(): void {
    chdir($this->originalDir);
    $this->removeDir($this->tempDir);
  }

  private function removeDir(string $dir) {
    if (!is_dir($dir)) {
      return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
      (is_dir("$dir/$file")) ? $this->removeDir("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
  }

  public function testGetBaseDirWhenAlreadyInitialized() {
    mkdir($this->tempDir . '/.directio');

    $command = new class extends Command {
      use InitializedDirCommandTrait;

      protected static $defaultName = 'test';

      protected function execute(InputInterface $input, OutputInterface $output) {
        $result = $this->getBaseDirOrInitializeCurrent($input, $output);
        $output->write('RESULT:' . $result);

        return Command::SUCCESS;
      }
    };

    $application = new Application();
    $application->add($command);
    $tester = new CommandTester($application->find('test'));
    $tester->execute([]);

    $display = $tester->getDisplay();
    preg_match('/RESULT:(.+)$/', $display, $matches);
    $result = $matches[1] ?? '';
    $this->assertEquals(realpath($this->tempDir), realpath($result));
  }

  public function testGetBaseDirWhenNotInitializedAndUserSaysNo() {
    $command = new class extends Command {
      use InitializedDirCommandTrait;

      protected static $defaultName = 'test';

      protected function execute(InputInterface $input, OutputInterface $output) {
        $result = $this->getBaseDirOrInitializeCurrent($input, $output);
        $output->write('RESULT:' . $result);

        return Command::SUCCESS;
      }
    };

    $application = new Application();
    $application->add($command);
    $tester = new CommandTester($application->find('test'));
    $tester->setInputs(['no']);
    $tester->execute([]);

    $display = $tester->getDisplay();
    preg_match('/RESULT:(.*)$/', $display, $matches);
    $result = $matches[1] ?? 'NOT_FOUND';
    $this->assertEquals('', $result);
  }

  public function testGetBaseDirWhenNotInitializedAndUserSaysYes() {
    $command = new class extends Command {
      use InitializedDirCommandTrait;

      protected static $defaultName = 'test';

      protected function execute(InputInterface $input, OutputInterface $output) {
        $result = $this->getBaseDirOrInitializeCurrent($input, $output);
        $output->write('RESULT:' . $result);

        return Command::SUCCESS;
      }
    };

    $application = new Application();
    $application->add($command);
    $tester = new CommandTester($application->find('test'));
    $tester->setInputs(['yes']);
    $tester->execute([]);

    $display = $tester->getDisplay();
    preg_match('/RESULT:(.+)$/', $display, $matches);
    $result = $matches[1] ?? '';
    $this->assertEquals(realpath($this->tempDir), realpath($result));
    $this->assertFileExists($this->tempDir . '/.directio');
  }
}
