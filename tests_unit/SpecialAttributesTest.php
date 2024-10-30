<?php

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\Config\SpecialAttributes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Config\SpecialAttributes
 */
class SpecialAttributesTest extends TestCase {

  public function testIdKeysContainsExpectedValues() {
    $id_keys = SpecialAttributes::idKeys();
    $this->assertArrayHasKey('id', $id_keys);
    $this->assertArrayHasKey('name', $id_keys);
  }

  public function testDoneKeysContainsExpectedValues() {
    $done_keys = SpecialAttributes::doneKeys();
    $this->assertArrayHasKey('done', $done_keys);
    $this->assertArrayHasKey('complete', $done_keys);
    $this->assertArrayHasKey('[x]', $done_keys);
  }
}
