<?php

namespace AKlump\Directio\Tests\Traits;

use AKlump\Directio\Traits\MHTMLTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Traits\MHTMLTrait
 */
class MHTMLTraitTest extends TestCase {

  public function testNormalizePathForMhtml() {
    $class = new class {
      use MHTMLTrait {
        normalizePathForMhtml as public;
      }
    };

    $this->assertEquals('/', $class->normalizePathForMhtml(''));
    $this->assertEquals('/', $class->normalizePathForMhtml('/'));
    $this->assertEquals('/foo/bar', $class->normalizePathForMhtml('/foo/bar/'));
    $this->assertEquals('/foo/baz', $class->normalizePathForMhtml('/foo/bar/../baz'));
    $this->assertEquals('/baz', $class->normalizePathForMhtml('/foo/bar/../../baz'));
    $this->assertEquals('/baz', $class->normalizePathForMhtml('/foo/bar/../../../baz'));
  }

  public function testMakeAbsoluteUrlForMhtml() {
    $class = new class {
      use MHTMLTrait {
        makeAbsoluteUrlForMhtml as public;
        normalizePathForMhtml as public;
      }
    };

    $base = 'https://example.com/path/to/page.html';
    $this->assertEquals('https://example.com/path/to/image.png', $class->makeAbsoluteUrlForMhtml('image.png', $base));
    $this->assertEquals('https://example.com/image.png', $class->makeAbsoluteUrlForMhtml('/image.png', $base));
    $this->assertEquals('https://other.com/image.png', $class->makeAbsoluteUrlForMhtml('https://other.com/image.png', $base));
    $this->assertEquals('https://example.com/style.css', $class->makeAbsoluteUrlForMhtml('//example.com/style.css', $base));
    $this->assertEquals('https://example.com/path/image.png', $class->makeAbsoluteUrlForMhtml('../image.png', $base));
  }

  public function testExtractAssetUrlsFromHtml() {
    $class = new class {
      use MHTMLTrait {
        extractAssetUrlsFromHtml as public;
        makeAbsoluteUrlForMhtml as public;
        normalizePathForMhtml as public;
      }
    };

    $html = <<<'EOD'
<html>
<head>
    <link rel="stylesheet" href="style.css">
    <script src="/js/app.js"></script>
</head>
<body>
    <img src="img/logo.png">
    <video poster="img/poster.jpg">
        <source src="video.mp4">
    </video>
    <div style="background-image: url('bg.jpg')"></div>
    <div style="background: url(bg2.jpg)"></div>
</body>
</html>
EOD;

    $base = 'https://example.com/index.html';
    $urls = $class->extractAssetUrlsFromHtml($html, $base);

    $this->assertContains('https://example.com/style.css', $urls);
    $this->assertContains('https://example.com/js/app.js', $urls);
    $this->assertContains('https://example.com/img/logo.png', $urls);
    $this->assertContains('https://example.com/img/poster.jpg', $urls);
    $this->assertContains('https://example.com/video.mp4', $urls);
    $this->assertContains('https://example.com/bg.jpg', $urls);
    $this->assertContains('https://example.com/bg2.jpg', $urls);
  }

  public function testBuildMhtmlArchive() {
    $class = new class {
      use MHTMLTrait {
        buildMhtmlArchive as public;
        ensureCharsetForMhtml as public;
      }
    };

    $url = 'https://example.com/';
    $html = '<html><body>Hello</body></html>';
    $assets = [
      'https://example.com/style.css' => [
        'body' => 'body { color: red; }',
        'content_type' => 'text/css',
      ],
      'https://example.com/logo.png' => [
        'body' => 'binary-data',
        'content_type' => 'image/png',
      ],
    ];

    $mhtml = $class->buildMhtmlArchive($url, $html, 'text/html', $assets);

    $this->assertStringContainsString('Subject: https://example.com/', $mhtml);
    $this->assertStringContainsString('Content-Type: multipart/related;', $mhtml);
    $this->assertStringContainsString('Content-Location: https://example.com/', $mhtml);
    $this->assertStringContainsString(quoted_printable_encode($html), $mhtml);
    $this->assertStringContainsString('Content-Location: https://example.com/style.css', $mhtml);
    $this->assertStringContainsString(quoted_printable_encode($assets['https://example.com/style.css']['body']), $mhtml);
    $this->assertStringContainsString('Content-Location: https://example.com/logo.png', $mhtml);
    $this->assertStringContainsString(base64_encode($assets['https://example.com/logo.png']['body']), $mhtml);
  }

  public function testMakeHtmlAbsoluteForMhtml() {
    $class = new class {
      use MHTMLTrait {
        makeHtmlAbsoluteForMhtml as public;
        makeAbsoluteUrlForMhtml as public;
        normalizePathForMhtml as public;
      }
    };

    $html = <<<'EOD'
<html>
<head>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: url('bg-inline.jpg'); }
    </style>
</head>
<body>
    <a href="/contact">Contact</a>
    <img src="logo.png">
    <div style="background: url(bg-style-attr.jpg)"></div>
</body>
</html>
EOD;
    $base = 'https://example.com/folder/page.html';
    $result = $class->makeHtmlAbsoluteForMhtml($html, $base);

    $this->assertStringContainsString('href="https://example.com/folder/style.css"', $result);
    $this->assertStringContainsString("url('https://example.com/folder/bg-inline.jpg')", $result);
    $this->assertStringContainsString('href="https://example.com/contact"', $result);
    $this->assertStringContainsString('src="https://example.com/folder/logo.png"', $result);
    $this->assertStringContainsString('url(https://example.com/folder/bg-style-attr.jpg)', $result);
  }

  public function testDownloadAsMhtmlShowsProgressBar() {
    $io = $this->createMock(\Symfony\Component\Console\Style\SymfonyStyle::class);
    $io->expects($this->once())->method('progressStart')->with(1);
    $io->expects($this->once())->method('progressAdvance');
    $io->expects($this->once())->method('progressFinish');

    $class = new class($io) {
      use MHTMLTrait {
        downloadAsMhtml as public;
      }

      private $io;

      public function __construct($io) {
        $this->io = $io;
      }

      public function io() {
        return $this->io;
      }

      protected function fetchUrlForMhtml(string $url, array $headers): array {
        if (str_ends_with($url, '.html')) {
          return [
            'body' => '<html><img src="img.png"></html>',
            'content_type' => 'text/html',
            'status_code' => 200,
            'final_url' => $url,
            'headers' => [],
          ];
        }

        return [
          'body' => 'binary',
          'content_type' => 'image/png',
          'status_code' => 200,
          'final_url' => $url,
          'headers' => [],
        ];
      }
    };

    $tempFile = tempnam(sys_get_temp_dir(), 'mhtml');
    $class->downloadAsMhtml('https://example.com/index.html', $tempFile);
    unlink($tempFile);
  }

  public function testDownloadAsMhtmlUsesCache() {
    $cacheDir = sys_get_temp_dir() . '/mhtml_cache_' . uniqid();
    mkdir($cacheDir);

    $class = new class {
      use MHTMLTrait {
        downloadAsMhtml as public;
      }

      public $fetchCount = 0;

      protected function fetchUrlForMhtml(string $url, array $headers): array {
        $this->fetchCount++;
        if (str_ends_with($url, '.html')) {
          return [
            'body' => '<html><img src="img.png"></html>',
            'content_type' => 'text/html',
            'status_code' => 200,
            'final_url' => $url,
            'headers' => [],
          ];
        }

        return [
          'body' => 'binary',
          'content_type' => 'image/png',
          'status_code' => 200,
          'final_url' => $url,
          'headers' => [],
        ];
      }
    };

    $tempFile = tempnam(sys_get_temp_dir(), 'mhtml');

    // First call, should fetch 2 times (html + img)
    $class->downloadAsMhtml('https://example.com/index.html', $tempFile, [], $cacheDir);
    $this->assertEquals(2, $class->fetchCount);

    // Second call with same cache, should fetch only 1 time (html)
    $class->fetchCount = 0;
    $class->downloadAsMhtml('https://example.com/index.html', $tempFile, [], $cacheDir);

    // If we only cache discovered assets, html is fetched again.
    $this->assertEquals(1, $class->fetchCount);

    // Verify cache file exists
    $cacheFile = $cacheDir . '/' . md5('https://example.com/img.png') . '.cache';
    $this->assertFileExists($cacheFile);

    unlink($tempFile);
    // cleanup cache
    array_map('unlink', glob("$cacheDir/*"));
    rmdir($cacheDir);
  }

  public function testDownloadAsMhtmlPrintsDownloadTimes() {
    $io = $this->createMock(\Symfony\Component\Console\Style\SymfonyStyle::class);
    // Expect 1 writeln call for the final summary message.
    $io->expects($this->once())->method('writeln')
      ->with($this->stringContains('Downloaded https://example.com/index.html to '));

    $class = new class($io) {
      use MHTMLTrait {
        downloadAsMhtml as public;
      }

      private $io;

      public function __construct($io) {
        $this->io = $io;
      }

      public function io() {
        return $this->io;
      }

      protected function fetchUrlForMhtml(string $url, array $headers): array {
        if (str_ends_with($url, '.html')) {
          return [
            'body' => '<html><img src="img.png"></html>',
            'content_type' => 'text/html',
            'status_code' => 200,
            'final_url' => $url,
            'headers' => [],
          ];
        }

        return [
          'body' => 'binary',
          'content_type' => 'image/png',
          'status_code' => 200,
          'final_url' => $url,
          'headers' => [],
        ];
      }
    };

    $tempFile = tempnam(sys_get_temp_dir(), 'mhtml');
    $class->downloadAsMhtml('https://example.com/index.html', $tempFile);
    unlink($tempFile);
  }
}
