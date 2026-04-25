<?php

namespace AKlump\Directio\Serializer;

use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * A simple MHTML decoder that extracts the first text/html part.
 */
class MhtmlDecoder implements DecoderInterface {

  public const FORMAT = 'mhtml';

  /**
   * {@inheritdoc}
   */
  public function decode(string $data, string $format, array $context = []) {
    $boundary = $this->getBoundary($data);
    if (!$boundary) {
      return $data;
    }

    $parts = explode('--' . $boundary, $data);
    foreach ($parts as $part) {
      if (str_contains($part, 'Content-Type: text/html')) {
        return $this->decodePart($part);
      }
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding(string $format): bool {
    return self::FORMAT === $format;
  }

  private function getBoundary(string $data): ?string {
    if (preg_match('/boundary="?([^";\n\r]+)"?/i', $data, $matches)) {
      return $matches[1];
    }

    return NULL;
  }

  private function decodePart(string $part): string {
    // Separate headers from body
    $parts = preg_split('/\r?\n\r?\n/', trim($part), 2);
    if (count($parts) < 2) {
      return '';
    }
    list($headers, $body) = $parts;

    if (preg_match('/Content-Transfer-Encoding: quoted-printable/i', $headers)) {
      $body = quoted_printable_decode($body);
    }
    elseif (preg_match('/Content-Transfer-Encoding: base64/i', $headers)) {
      $body = base64_decode($body);
    }

    return $body;
  }
}
