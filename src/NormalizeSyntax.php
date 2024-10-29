<?php

namespace AKlump\Directio;

class NormalizeSyntax {

  public function __invoke(string $content): string {
    $content = $this->ensureProperSpaces($content);

    return $this->removeAttributeValueQuotes($content);
  }

  private function removeAttributeValueQuotes(string $content) {
    return preg_replace_callback('#<!-- directio (.+?) -->#', function ($matches) {
      return preg_replace('#="(.+?)"#', '=$1', $matches[0]);
    }, $content);
  }

  private function ensureProperSpaces(string $content) {
    $content = preg_replace_callback('#<!--\s?directio (.+?)\s?-->#', function ($matches) {
      return sprintf('<!-- directio %s -->', $matches[1]);
    }, $content);

    return preg_replace_callback('#<!--\s?/directio\s?-->#', function ($matches) {
      return '<!-- /directio -->';
    }, $content);
  }
}
