<?php

namespace AKlump\Directio;

use AKlump\Directio\Style\StyleInterface;

class HTMLElementStyle implements StyleInterface {

  /**
   * {@inheritdoc}
   */
  public function getOpenTagPattern(): string {
    return '<directio(?: .+?)?>';
  }

  /**
   * {@inheritdoc}
   */
  public function getCloseTagConstant(): string {
    return '</directio>';
  }

}
