<?php

namespace AKlump\Directio\Fixture;

use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\Directio\Serializer\MhtmlDecoder;
use AKlump\Directio\Serializer\DrupalPageNotFoundCollection;
use AKlump\FixtureFramework\Exception\FixtureException;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class CheckPageNotFoundRedirects extends AbstractFixture {

  public function __invoke(): void {
    $urls_to_check = $this->getUrlsToCheck();
    $base_url = $this->options->require('base_url');
    $failed_assert_count = 0;
    foreach ($urls_to_check as $data) {
      $url = $data['url'];
      $count = $data['count'];
      $full_url = $this->resolveUrl($base_url, $url);
      $headers = $this->getHeaders($full_url);
      $status = $this->getFinalStatusCode($headers);

      if (str_starts_with($status, '4') && !$this->isServedByDrupal($headers)) {
        $this->output()
          ->writeln(sprintf('<info>%s - %s: %s</info>', $count, $status, $full_url));
      }
      else {
        $this->output()
          ->writeln(sprintf('<error>%s - %s: %s</error>', $count, $status, $full_url));
        $failed_assert_count++;
      }
    }

    if ($failed_assert_count === 0) {
      $this->output()
        ->writeln('<info>All checked URLs are correctly handled (no 404s found).</info>');
    }
    else {
      throw new FixtureException(sprintf('<error>Found %d 4xx URLs still handled by Drupal</error>', $failed_assert_count));
    }
  }

  private function resolveUrl(string $base_url, string $url): string {
    if (preg_match('/^https?:\/\//', $url)) {
      return $url;
    }
    $base_url = rtrim($base_url, '/');
    if (str_starts_with($url, '/')) {
      return $base_url . $url;
    }

    return $base_url . '/' . $url;
  }

  protected function getHeaders(string $url): array {
    $allow_insecure_ssl = $this->options->require('allow_insecure_ssl');
    $options = [
      'http' => [
        'method' => 'HEAD',
        'timeout' => 5,
      ],
      'ssl' => [
        'verify_peer' => !$allow_insecure_ssl,
        'verify_peer_name' => !$allow_insecure_ssl,
      ],
    ];
    $context = stream_context_create($options);

    $error_message = '';
    set_error_handler(function (int $errno, string $errstr) use (&$error_message) {
      $error_message = $errstr;

      return TRUE;
    });
    $headers = get_headers($url, 0, $context);
    restore_error_handler();
    if ($error_message) {
      $this->output()->writeln(sprintf('<error>%s</error>', $error_message));
    }

    return $headers ?: [];
  }

  private function getUrlsToCheck(): array {
    $source = $this->options->require('page_not_found_mhtml');
    if (!file_exists($source)) {
      throw new FixtureException(sprintf('Source file not found: %s', $source));
    }

    $data = file_get_contents($source);
    $serializer = new Serializer([new ObjectNormalizer()], [
      new MhtmlDecoder(),
      new DrupalPageNotFoundCollection(),
    ]);

    $html = $serializer->decode($data, MhtmlDecoder::FORMAT);
    $collection = $serializer->decode($html, DrupalPageNotFoundCollection::FORMAT);

    return $collection;
  }

  private function isServedByDrupal(array $headers): bool {
    foreach ($headers as $header) {
      // X-Generator: Drupal 10 (https://www.drupal.org)
      // X-Drupal-Cache: MISS
      if (preg_match('/^X-Drupal|X-Generator: Drupal/', $header)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  private function getFinalStatusCode(array $headers): string {
    $status_codes = [];
    foreach ($headers as $header) {
      if (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})\b/', $header, $matches)) {
        $status_codes[] = $matches[1];
      }
    }

    return end($status_codes) ?: '';
  }
}
