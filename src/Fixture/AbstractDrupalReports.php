<?php

namespace AKlump\Directio\Fixture;

use AKlump\Directio\Exception\AuthenticationRequiredException;
use AKlump\Directio\FixtureFramework\AbstractFixture;
use AKlump\Directio\Traits\MHTMLTrait;
use AKlump\FixtureFramework\Exception\FixtureException;

/**
 * Base class for Drupal report fixtures.
 */
abstract class AbstractDrupalReports extends AbstractFixture {

  use MHTMLTrait;

  /**
   * @inheritDoc
   */
  public function __invoke(): void {
    $session_name = $this->context()->require('admin_session_cookie.name');
    $session_value = $this->context()->require('admin_session_cookie.value');

    $headers = [
      'Cookie' => "$session_name=$session_value",
    ];

    $urls = $this->getUrls();
    foreach ($urls as $url => $destination) {
      if (file_exists($destination) && filesize($destination) > 0) {
        if (!$this->io()
          ->confirm(sprintf('The file %s already exists. Overwrite?', $this->shortPath($destination)), FALSE)) {
          continue;
        }
      }

      try {
        $this->downloadAsMhtml($url, $destination, $headers, $this->cacheDirectory() . '/www/');
      }
      catch (AuthenticationRequiredException $exception) {
        $this->context()->remove('admin_session_cookie.name');
        $this->context()->remove('admin_session_cookie.value');

        throw new FixtureException(
          'The saved admin session appears to be invalid and has been cleared. Rerun the admin session fixture.',
          0,
          $exception
        );
      }
    }
  }

  /**
   * Get the URLs to download and their destinations.
   *
   * @return array
   *   An associative array where keys are URLs and values are local file paths.
   */
  abstract protected function getUrls(): array;

}
