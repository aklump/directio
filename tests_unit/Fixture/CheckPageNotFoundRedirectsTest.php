<?php

namespace AKlump\Directio\Tests\Unit\Fixture;

use AKlump\Directio\Fixture\CheckPageNotFoundRedirects;
use AKlump\FixtureFramework\Runtime\RunOptions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @covers \AKlump\Directio\Fixture\CheckPageNotFoundRedirects
 */
class CheckPageNotFoundRedirectsTest extends TestCase {

  public function testInvokeWithMissingBaseUrl() {
    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();
    $fixture = new CheckPageNotFoundRedirects($input, $output);
    $fixture->options = RunOptions::fromArray([
      'directio_directory' => __DIR__,
      'page_not_found_mhtml' => __FILE__, // Exists, but probably won't decode correctly.
    ]);

    // We expect it to fail in getUrlsToCheck because __FILE__ is not valid MHTML,
    // unless we mock getUrlsToCheck, but we can't because it's private.
    // Wait, if I want to test base_url, I should probably mock the whole fixture?
    // No, I can just provide a valid (empty) MHTML file.
    $mhtmlPath = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($mhtmlPath, "Content-Type: multipart/related; boundary=\"b\"\n\n--b\nContent-Type: text/html\n\n<div></div>\n--b--");
    $fixture->options = RunOptions::fromArray([
      'directio_directory' => __DIR__,
      'page_not_found_mhtml' => $mhtmlPath,
    ]);

    try {
      $this->expectException(\AKlump\FixtureFramework\Exception\MissingRunOptionException::class);
      $this->expectExceptionMessage('Required run option "base_url" is missing.');
      $fixture();
    } finally {
      unlink($mhtmlPath);
    }
  }

  public function testInvokeWithMissingSource() {
    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();
    $fixture = new CheckPageNotFoundRedirects($input, $output);
    $fixture->options = RunOptions::fromArray([
      'directio_directory' => __DIR__,
      'base_url' => 'http://example.com',
    ]);

    $this->expectException(\AKlump\FixtureFramework\Exception\MissingRunOptionException::class);
    $this->expectExceptionMessage('Required run option "page_not_found_mhtml" is missing.');
    $fixture();
  }

  public function testInvokeWithMhtmlSource() {
    $mhtmlPath = tempnam(sys_get_temp_dir(), 'test');
    $mhtmlContent = <<<EOD
MIME-Version: 1.0
Content-Type: multipart/related; boundary="boundary"

--boundary
Content-Type: text/html

<div class="block-system-main-block">
  <table>
    <tbody>
      <tr><td>1</td><td>/found</td></tr>
      <tr><td>2</td><td>/not-found</td></tr>
    </tbody>
  </table>
</div>
--boundary--
EOD;
    file_put_contents($mhtmlPath, $mhtmlContent);

    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();

    $fixture = $this->getMockBuilder(CheckPageNotFoundRedirects::class)
      ->setConstructorArgs([$input, $output])
      ->onlyMethods(['getHeaders'])
      ->getMock();

    $fixture->method('getHeaders')
      ->willReturnMap([
        ['http://example.com/found', ['HTTP/1.1 200 OK']],
        ['http://example.com/not-found', ['HTTP/1.1 404 Not Found']],
      ]);

    $fixture->options = RunOptions::fromArray([
      'directio_directory' => __DIR__,
      'page_not_found_mhtml' => $mhtmlPath,
      'base_url' => 'http://example.com',
      'allow_insecure_ssl' => FALSE,
    ]);

    try {
      $fixture();
      $this->fail('Expected FixtureException was not thrown.');
    }
    catch (\AKlump\FixtureFramework\Exception\FixtureException $e) {
      $this->assertStringContainsString('Found 1 4xx URLs still handled by Drupal', $e->getMessage());
    }
    $display = $output->fetch();
    $this->assertStringContainsString('1 - 200: http://example.com/found', $display);
    $this->assertStringContainsString('2 - 404: http://example.com/not-found', $display);

    unlink($mhtmlPath);
  }

  public function testInvokeWithAllUrlsHandled() {
    $mhtmlPath = tempnam(sys_get_temp_dir(), 'test');
    $mhtmlContent = <<<EOD
MIME-Version: 1.0
Content-Type: multipart/related; boundary="boundary"

--boundary
Content-Type: text/html

<div class="block-system-main-block">
  <table>
    <tbody>
      <tr><td>1</td><td>/handled</td></tr>
    </tbody>
  </table>
</div>
--boundary--
EOD;
    file_put_contents($mhtmlPath, $mhtmlContent);

    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();

    $fixture = $this->getMockBuilder(CheckPageNotFoundRedirects::class)
      ->setConstructorArgs([$input, $output])
      ->onlyMethods(['getHeaders'])
      ->getMock();

    $fixture->method('getHeaders')
      ->willReturnMap([
        ['http://example.com/handled', ['HTTP/1.1 404 Not Found']],
      ]);

    $fixture->options = RunOptions::fromArray([
      'directio_directory' => __DIR__,
      'page_not_found_mhtml' => $mhtmlPath,
      'base_url' => 'http://example.com',
      'allow_insecure_ssl' => FALSE,
    ]);

    $fixture();
    $display = $output->fetch();
    $this->assertStringContainsString('All checked URLs are correctly handled', $display);

    unlink($mhtmlPath);
  }

  public function testInvokeWithAbsoluteUrlsInSource() {
    $mhtmlPath = tempnam(sys_get_temp_dir(), 'test');
    $mhtmlContent = <<<EOD
MIME-Version: 1.0
Content-Type: multipart/related; boundary="boundary"

--boundary
Content-Type: text/html

<div class="block-system-main-block">
  <table>
    <tbody>
      <tr><td>1</td><td>http://other.com/absolute</td></tr>
    </tbody>
  </table>
</div>
--boundary--
EOD;
    file_put_contents($mhtmlPath, $mhtmlContent);

    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();

    $fixture = $this->getMockBuilder(CheckPageNotFoundRedirects::class)
      ->setConstructorArgs([$input, $output])
      ->onlyMethods(['getHeaders'])
      ->getMock();

    $fixture->method('getHeaders')
      ->willReturnMap([
        ['http://other.com/absolute', ['HTTP/1.1 404 Not Found']],
      ]);

    $fixture->options = RunOptions::fromArray([
      'directio_directory' => __DIR__,
      'page_not_found_mhtml' => $mhtmlPath,
      'base_url' => 'http://example.com',
      'allow_insecure_ssl' => FALSE,
    ]);

    $fixture();
    $display = $output->fetch();
    $this->assertStringContainsString('1 - 404: http://other.com/absolute', $display);

    unlink($mhtmlPath);
  }

  public function testGetStatusCodeCapturesErrors() {
    $input = $this->createMock(InputInterface::class);
    $output = new BufferedOutput();
    $fixture = new CheckPageNotFoundRedirects($input, $output);
    $fixture->options = RunOptions::fromArray([
      'directio_directory' => __DIR__,
      'allow_insecure_ssl' => TRUE,
    ]);

    $method = new \ReflectionMethod(CheckPageNotFoundRedirects::class, 'getHeaders');
    $method->setAccessible(TRUE);

    // Use a non-existent domain to trigger an error.
    $method->invoke($fixture, 'http://this.domain.does.not.exist.example.com');
    $display = $output->fetch();
    $this->assertNotEmpty($display);
    // Depending on the OS/environment, the exact error message might vary,
    // but it should contain something about failing to open stream or DNS.
    $this->assertStringContainsString('get_headers', $display);
  }
}
