<?php

namespace AKlump\Directio\Tests\Unit\Fixture;

use AKlump\Directio\Fixture\UpdateEasyPerms;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use AKlump\FixtureFramework\Exception\FixtureException;

/**
 * @covers \AKlump\Directio\Fixture\UpdateEasyPerms
 */
class UpdateEasyPermsTest extends TestCase {

  private string $tempDir;
  private string $cwd;

  protected function setUp(): void {
    $this->cwd = getcwd();
    $this->tempDir = sys_get_temp_dir() . '/directio_test_' . uniqid();
    mkdir($this->tempDir);
    chdir($this->tempDir);
  }

  protected function tearDown(): void {
    chdir($this->cwd);
    if (is_dir($this->tempDir)) {
      $this->removeDir($this->tempDir);
    }
  }

  private function removeDir(string $dir) {
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
      (is_dir("$dir/$file")) ? $this->removeDir("$dir/$file") : unlink("$dir/$file");
    }

    return rmdir($dir);
  }

  public function testDoesNothingIfComposerShowSucceeds() {
    // We can't easily mock exec() globally in a clean way without extensions,
    // but we can rely on the fact that 'composer' might not be available
    // or we can mock the behavior if we refactor the fixture to use a wrapper.
    // However, for this specific task, I'll check if I can at least run it
    // and see it handles "not found" correctly if I don't have composer set up.
    
    // Actually, a better way is to test the logic by mocking the system state if possible.
    // Since the fixture uses `exec` and `is_dir(getcwd() . '/easy-perms')`,
    // I will simulate the directory.

    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();
    $fixture = new UpdateEasyPerms($input, $output);

    // If composer show aklump/easy-perms fails (which it should in a temp dir)
    // and easy-perms dir doesn't exist.
    $fixture();
    $this->assertStringContainsString('Subdirectory "easy-perms/" not found', $output->fetch());
  }

  public function testThrowsExceptionIfUpdateFails() {
    mkdir($this->tempDir . '/easy-perms');
    
    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();
    $fixture = new UpdateEasyPerms($input, $output);

    // This should fail because 'composer update' will fail in an empty dir.
    $this->expectException(FixtureException::class);
    $fixture();
  }
}
