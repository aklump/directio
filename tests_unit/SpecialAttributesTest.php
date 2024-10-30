<?php

namespace AKlump\Directio\Tests\Unit;

use AKlump\Directio\SpecialAttributes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\SpecialAttributes
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
    $this->assertArrayHasKey('[X]', $done_keys);
    $this->assertArrayHasKey('[x]', $done_keys);
  }

  public function testId() {
    $attributes = new SpecialAttributes();
    $this->assertSame('id', $attributes->id);
    $this->assertSame('id', $attributes->name);
  }

  public function testDone() {
    $attributes = new SpecialAttributes();
    $this->assertSame('done', $attributes->done);
    $this->assertSame('done', $attributes->complete);
  }
}
