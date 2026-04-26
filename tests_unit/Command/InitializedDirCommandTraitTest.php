<?php

namespace AKlump\Directio\Tests\Unit\Command;

use AKlump\Directio\Command\InitializedDirCommandTrait;
use AKlump\Directio\IO\GetDirectioRoot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Question\ConfirmationQuestion;

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
      public function test($input, $output) {
        return $this->getBaseDirOrInitializeCurrent($input, $output);
      }
    };
    
    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();
    
    $result = $command->test($input, $output);
    $this->assertEquals(realpath($this->tempDir), realpath($result));
  }

  public function testGetBaseDirWhenNotInitializedAndUserSaysNo() {
    $command = new class extends Command {
      use InitializedDirCommandTrait;
      public function test($input, $output) {
        return $this->getBaseDirOrInitializeCurrent($input, $output);
      }
    };
    $command->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet());
    
    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();
    
    $helper = $this->getMockBuilder(\Symfony\Component\Console\Helper\QuestionHelper::class)
      ->onlyMethods(['ask'])
      ->getMock();
    $helper->method('ask')->willReturn(FALSE);
    $command->getHelperSet()->set($helper, 'question');
    
    $result = $command->test($input, $output);
    $this->assertEquals('', $result);
    $this->assertStringContainsString('Directio is not tracking your project', $output->fetch());
  }

  public function testGetBaseDirWhenNotInitializedAndUserSaysYes() {
    $command = new class extends Command {
      use InitializedDirCommandTrait;
      public function test($input, $output) {
        return $this->getBaseDirOrInitializeCurrent($input, $output);
      }
    };
    $command->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet());
    
    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();
    
    $helper = $this->getMockBuilder(\Symfony\Component\Console\Helper\QuestionHelper::class)
      ->onlyMethods(['ask'])
      ->getMock();
    $helper->method('ask')->willReturn(TRUE);
    $command->getHelperSet()->set($helper, 'question');
    
    $result = $command->test($input, $output);
    $this->assertEquals(realpath($this->tempDir), realpath($result));
    $this->assertFileExists($this->tempDir . '/.directio');
  }
}
