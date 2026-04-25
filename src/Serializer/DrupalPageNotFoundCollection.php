<?php

namespace AKlump\Directio\Serializer;

use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * Decodes Drupal 'Top page not found errors' report HTML.
 */
class DrupalPageNotFoundCollection implements DecoderInterface {

  public const FORMAT = 'drupal_page_not_found';

  /**
   * {@inheritdoc}
   */
  public function decode(string $data, string $format, array $context = []) {
    $dom = new \DOMDocument();
    @$dom->loadHTML($data);
    $xpath = new \DOMXPath($dom);

    // The table is inside .block-system-main-block.
    // Based on the provided MHTML content, the structure is:
    // <div class="block-system-main-block">...<table>...<tbody>...<tr>...
    $rows = $xpath->query('//div[contains(@class, "block-system-main-block")]//table/tbody/tr');

    $collection = [];
    foreach ($rows as $row) {
      $cols = $xpath->query('td', $row);
      if ($cols->length >= 2) {
        $collection[] = [
          'count' => (int) trim($cols->item(0)->textContent),
          'url' => trim($cols->item(1)->textContent),
        ];
      }
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding(string $format): bool {
    return self::FORMAT === $format;
  }
}
