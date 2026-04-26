<?php

namespace AKlump\Directio\Traits;

use AKlump\Directio\Style\StyleInterface;

trait HasStyleTrait {

  private StyleInterface $style;

  private string $closeTagPattern = '';

  private string $openTagPattern = '';

  private string $openTagStart = '';

  private string $openTagEnd = '';

  public function setStyle(StyleInterface $style): void {
    $this->openTagPattern = $style->getOpenTagPattern();
    list($this->openTagStart) = explode('(?:', $style->getOpenTagPattern(), 2);
    list(, $this->openTagEnd) = explode(')?', $style->getOpenTagPattern(), 2);
    $this->closeTagPattern = preg_quote($style->getCloseTagConstant(), '/');
    $this->style = $style;
  }
}
