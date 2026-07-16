<?php

namespace AKlump\Directio\Tests;

use AKlump\Directio\HTMLElementStyle;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\HTMLElementStyle
 */
class HTMLElementStyleTest extends TestCase {

  public function testStyleMethods() {
    $style = new HTMLElementStyle();
    $this->assertNotEmpty($style->getOpenTagPattern());
    $this->assertEquals('</directio>', $style->getCloseTagConstant());
    $this->assertMatchesRegularExpression('/' . $style->getOpenTagPattern() . '/', '<directio id="foo">');
    $this->assertMatchesRegularExpression('/' . $style->getOpenTagPattern() . '/', '<directio>');
  }
}
