<?php

namespace AKlump\Directio\Traits;

use AKlump\FixtureFramework\Exception\FixtureException;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Trait for creating MHTML (MIME HTML) archives.
 */
trait MHTMLTrait {

  /**
   * Downloads a URL and its assets into a single MHTML archive.
   *
   * @param string $url The URL of the page to download.
   * @param string $path The local path where the MHTML file should be saved.
   * @param array $headers An associative array of headers, e.g., ['Cookie' => '...']
   * @param string|null $cache_dir Optional. A local directory to cache discovered assets.
   *
   * @throws \AKlump\FixtureFramework\Exception\FixtureException
   */
  protected function downloadAsMhtml(string $url, string $path, array $headers = [], string $cache_dir = NULL): void {
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, TRUE) && !is_dir($directory)) {
      throw new FixtureException(sprintf('Directory "%s" was not created', $directory));
    }
    if ($cache_dir && !is_dir($cache_dir) && !mkdir($cache_dir, 0777, TRUE) && !is_dir($cache_dir)) {
      throw new FixtureException(sprintf('Cache directory "%s" was not created', $cache_dir));
    }

    $stopwatch = class_exists(Stopwatch::class) ? new Stopwatch() : NULL;
    if ($stopwatch) {
      $stopwatch->start('mhtml');
    }

    $main_response = $this->fetchUrlForMhtml($url, $headers);
    $io = method_exists($this, 'io') ? $this->io() : NULL;

    $html = $main_response['body'];
    $content_type = $main_response['content_type'] ?: 'text/html';

    if (str_contains($content_type, 'text/html')) {
      $html = $this->makeHtmlAbsoluteForMhtml($html, $url);
    }

    $asset_urls = $this->extractAssetUrlsFromHtml($html, $url);
    $assets = [];
    if ($io && !empty($asset_urls)) {
      $io->progressStart(count($asset_urls));
    }
    foreach ($asset_urls as $asset_url) {
      $asset_cache_file = $cache_dir ? $cache_dir . '/' . md5($asset_url) . '.cache' : NULL;
      if ($asset_cache_file && file_exists($asset_cache_file)) {
        $assets[$asset_url] = unserialize(file_get_contents($asset_cache_file));
      }
      else {
        try {
          $assets[$asset_url] = $this->fetchUrlForMhtml($asset_url, $headers);
          if ($asset_cache_file) {
            file_put_contents($asset_cache_file, serialize($assets[$asset_url]));
          }
        }
        catch (FixtureException $exception) {
          if ($io) {
            $io->warning(sprintf('Could not include asset in MHTML: %s', $asset_url));
          }
        }
      }
      if ($io && !empty($asset_urls)) {
        $io->progressAdvance();
      }
    }
    if ($io && !empty($asset_urls)) {
      $io->progressFinish();
    }

    $mhtml = $this->buildMhtmlArchive($url, $html, $content_type, $assets);

    if (file_put_contents($path, $mhtml) === FALSE) {
      throw new FixtureException(sprintf('Failed to write to %s', $path));
    }

    if ($stopwatch) {
      $event = $stopwatch->stop('mhtml');
      if ($io) {
        $display_path = method_exists($this, 'shortPath') ? $this->shortPath($path) : $path;
        $io->writeln(sprintf('Downloaded %s to %s in %dms', $url, $display_path, $event->getDuration()));
      }
    }
  }

  protected function fetchUrlForMhtml(string $url, array $headers): array {
    $allow_insecure_ssl = FALSE;
    if (method_exists($this, 'options')) {
      $allow_insecure_ssl = $this->options()->get('allow_insecure_ssl', FALSE);
    }

    $header_string = '';
    foreach ($headers as $name => $value) {
      $header_string .= "$name: $value\r\n";
    }

    $opts = [
      'http' => [
        'method' => 'GET',
        'header' => $header_string,
        'follow_location' => 1,
        'max_redirects' => 5,
        'ignore_errors' => TRUE,
      ],
      'ssl' => [
        'verify_peer' => !$allow_insecure_ssl,
        'verify_peer_name' => !$allow_insecure_ssl,
      ],
    ];

    $context = stream_context_create($opts);
    $body = @file_get_contents($url, FALSE, $context);

    if ($body === FALSE) {
      $error = error_get_last();
      throw new FixtureException(sprintf('Failed to download %s: %s', $url, $error['message'] ?? 'Unknown error'));
    }

    $content_type = 'application/octet-stream';
    if (isset($http_response_header)) {
      foreach ($http_response_header as $header) {
        if (stripos($header, 'Content-Type:') === 0) {
          $content_type = trim(substr($header, strlen('Content-Type:')));
        }
      }
    }

    return [
      'body' => $body,
      'content_type' => $content_type,
    ];
  }

  private function makeHtmlAbsoluteForMhtml(string $html, string $base_url): string {
    if (empty($html)) {
      return $html;
    }
    $dom = new \DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    // Use a hack to preserve UTF-8
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $attributes = [
      'img' => 'src',
      'script' => 'src',
      'link' => 'href',
      'source' => 'src',
      'video' => 'poster',
      'a' => 'href',
      'form' => 'action',
      'area' => 'href',
      'iframe' => 'src',
      'embed' => 'src',
      'object' => 'data',
    ];

    foreach ($attributes as $tag => $attribute) {
      foreach ($dom->getElementsByTagName($tag) as $element) {
        if ($element->hasAttribute($attribute)) {
          $url = trim($element->getAttribute($attribute));
          if ($url !== '' && !str_starts_with($url, 'data:') && !str_starts_with($url, 'mailto:') && !str_starts_with($url, '#')) {
            $absolute_url = $this->makeAbsoluteUrlForMhtml($url, $base_url);
            if ($absolute_url) {
              $element->setAttribute($attribute, $absolute_url);
            }
          }
        }
      }
    }

    foreach ($dom->getElementsByTagName('*') as $element) {
      if ($element->hasAttribute('style')) {
        $style = $element->getAttribute('style');
        $new_style = preg_replace_callback('/url\((["\']?)([^)"\']+)\1\)/i', function ($matches) use ($base_url) {
          $quote = $matches[1];
          $url = trim($matches[2]);
          if ($url === '' || str_starts_with($url, 'data:')) {
            return $matches[0];
          }
          $absolute_url = $this->makeAbsoluteUrlForMhtml($url, $base_url);

          return "url($quote$absolute_url$quote)";
        }, $style);
        if ($new_style !== $style) {
          $element->setAttribute('style', $new_style);
        }
      }
    }

    foreach ($dom->getElementsByTagName('style') as $element) {
      $css = $element->textContent;
      $new_css = preg_replace_callback('/url\((["\']?)([^)"\']+)\1\)/i', function ($matches) use ($base_url) {
        $quote = $matches[1];
        $url = trim($matches[2]);
        if ($url === '' || str_starts_with($url, 'data:')) {
          return $matches[0];
        }
        $absolute_url = $this->makeAbsoluteUrlForMhtml($url, $base_url);

        return "url($quote$absolute_url$quote)";
      }, $css);
      if ($new_css !== $css) {
        $element->textContent = $new_css;
      }
    }

    $html = $dom->saveHTML();

    return str_replace('<?xml encoding="utf-8" ?>', '', $html);
  }

  private function extractAssetUrlsFromHtml(string $html, string $base_url): array {
    $urls = [];

    $dom = new \DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    @$dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $attributes = [
      'img' => 'src',
      'script' => 'src',
      'link' => 'href',
      'source' => 'src',
      'video' => 'poster',
    ];

    foreach ($attributes as $tag => $attribute) {
      foreach ($dom->getElementsByTagName($tag) as $element) {
        if (!$element->hasAttribute($attribute)) {
          continue;
        }

        $asset_url = trim($element->getAttribute($attribute));
        if ($asset_url === '' || str_starts_with($asset_url, 'data:') || str_starts_with($asset_url, 'mailto:')) {
          continue;
        }

        $absolute_url = $this->makeAbsoluteUrlForMhtml($asset_url, $base_url);
        if ($absolute_url !== NULL) {
          $urls[$absolute_url] = $absolute_url;
        }
      }
    }

    if (preg_match_all('/url\((["\']?)([^)"\']+)\1\)/i', $html, $matches)) {
      foreach ($matches[2] as $asset_url) {
        $asset_url = trim($asset_url);
        if ($asset_url === '' || str_starts_with($asset_url, 'data:')) {
          continue;
        }

        $absolute_url = $this->makeAbsoluteUrlForMhtml($asset_url, $base_url);
        if ($absolute_url !== NULL) {
          $urls[$absolute_url] = $absolute_url;
        }
      }
    }

    return array_values($urls);
  }

  private function makeAbsoluteUrlForMhtml(string $url, string $base_url): ?string {
    if (preg_match('/^https?:\/\//i', $url)) {
      return $url;
    }

    if (str_starts_with($url, '//')) {
      $scheme = parse_url($base_url, PHP_URL_SCHEME) ?: 'https';

      return $scheme . ':' . $url;
    }

    $base_parts = parse_url($base_url);
    if (empty($base_parts['scheme']) || empty($base_parts['host'])) {
      return NULL;
    }

    $origin = $base_parts['scheme'] . '://' . $base_parts['host'];
    if (!empty($base_parts['port'])) {
      $origin .= ':' . $base_parts['port'];
    }

    if (str_starts_with($url, '/')) {
      return $origin . $url;
    }

    $base_path = $base_parts['path'] ?? '/';
    $directory = rtrim(dirname($base_path), '/');
    $absolute_path = $directory . '/' . $url;

    return $origin . $this->normalizePathForMhtml($absolute_path);
  }

  private function normalizePathForMhtml(string $path): string {
    $parts = [];
    foreach (explode('/', $path) as $part) {
      if ($part === '' || $part === '.') {
        continue;
      }

      if ($part === '..') {
        array_pop($parts);
        continue;
      }

      $parts[] = $part;
    }

    return '/' . implode('/', $parts);
  }

  private function buildMhtmlArchive(string $page_url, string $html, string $content_type, array $assets): string {
    $boundary = '----=_NextPart_' . bin2hex(random_bytes(16));
    $date = gmdate('D, d M Y H:i:s') . ' GMT';

    $lines = [
      'From: <Saved by Directio>',
      'Snapshot-Content-Location: ' . $page_url,
      'Subject: ' . $page_url,
      'Date: ' . $date,
      'MIME-Version: 1.0',
      sprintf('Content-Type: multipart/related; type="text/html"; boundary="%s"', $boundary),
      '',
      'This is a multi-part message in MIME format.',
      '',
    ];

    $lines[] = '--' . $boundary;
    $html_content_type = $this->ensureCharsetForMhtml($content_type);
    $lines[] = 'Content-Type: ' . $html_content_type;
    $lines[] = 'Content-Transfer-Encoding: quoted-printable';
    $lines[] = 'Content-Location: ' . $page_url;
    $lines[] = '';
    $lines[] = quoted_printable_encode($html);
    $lines[] = '';

    foreach ($assets as $asset_url => $asset) {
      $lines[] = '--' . $boundary;
      $asset_content_type = $asset['content_type'] ?: 'application/octet-stream';
      $asset_content_type = $this->ensureCharsetForMhtml($asset_content_type);
      $lines[] = 'Content-Type: ' . $asset_content_type;

      if ($this->shouldUseQuotedPrintableForMhtml($asset_content_type)) {
        $lines[] = 'Content-Transfer-Encoding: quoted-printable';
        $lines[] = 'Content-Location: ' . $asset_url;
        $lines[] = '';
        $lines[] = quoted_printable_encode($asset['body']);
      }
      else {
        $lines[] = 'Content-Transfer-Encoding: base64';
        $lines[] = 'Content-Location: ' . $asset_url;
        $lines[] = '';
        $lines[] = rtrim(chunk_split(base64_encode($asset['body'])));
      }
      $lines[] = '';
    }

    $lines[] = '--' . $boundary . '--';
    $lines[] = '';

    return implode("\r\n", $lines);
  }

  private function ensureCharsetForMhtml(string $content_type): string {
    if (stripos($content_type, 'charset=') !== FALSE) {
      return $content_type;
    }
    $text_types = [
      'text/html',
      'text/css',
      'text/plain',
      'application/javascript',
      'application/x-javascript',
      'image/svg+xml',
    ];
    foreach ($text_types as $type) {
      if (stripos($content_type, $type) !== FALSE) {
        return $content_type . '; charset=UTF-8';
      }
    }

    return $content_type;
  }

  /**
   * Determines if a content type should use quoted-printable encoding.
   *
   * @param string $content_type
   *
   * @return bool
   */
  private function shouldUseQuotedPrintableForMhtml(string $content_type): bool {
    $types = [
      'text/html',
      'text/css',
      'text/plain',
      'application/javascript',
      'application/x-javascript',
      'image/svg+xml',
    ];
    foreach ($types as $type) {
      if (stripos($content_type, $type) !== FALSE) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
