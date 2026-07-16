<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Config;

use AKlump\Directio\Config\SpecialAttributes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Config\SpecialAttributes
 */
class SpecialAttributesTest extends TestCase {

  public function testExpiresKeysContainsExpectedValues() {
    $id_keys = SpecialAttributes::expiresKeys();
    $this->assertArrayHasKey('redo', $id_keys);
  }
  public function testIdKeysContainsExpectedValues() {
    $id_keys = SpecialAttributes::idKeys();
    $this->assertArrayHasKey('id', $id_keys);
    $this->assertArrayHasKey('name', $id_keys);
  }

  public function testDoneKeysContainsExpectedValues() {
    $done_keys = SpecialAttributes::doneKeys();
    $this->assertArrayHasKey('done', $done_keys);
    $this->assertArrayHasKey('complete', $done_keys);
    $this->assertArrayHasKey('x', $done_keys);
  }

  public function testDefaultIdKey() {
    $this->assertSame('id', SpecialAttributes::defaultIdKey());
  }

  public function testDefaultDoneKey() {
    $this->assertSame('done', SpecialAttributes::defaultDoneKey());
  }

  public function testFixtureKeysContainsExpectedValues() {
    $fixture_keys = SpecialAttributes::fixtureKeys();
    $this->assertArrayHasKey('fixture', $fixture_keys);
  }

  public function testExtractId() {
    $this->assertSame('foo', SpecialAttributes::extractId(['id' => 'foo']));
    $this->assertSame('bar', SpecialAttributes::extractId(['name' => 'bar']));
    $this->assertSame('foo', SpecialAttributes::extractId(['id' => 'foo', 'name' => 'bar']));
    $this->assertNull(SpecialAttributes::extractId(['other' => 'baz']));
    $this->assertSame('1', SpecialAttributes::extractId(['id' => TRUE]));
  }

  public function testExtractDone() {
    $this->assertSame('foo', SpecialAttributes::extractDone(['done' => 'foo']));
    $this->assertSame('bar', SpecialAttributes::extractDone(['x' => 'bar']));
    $this->assertSame('baz', SpecialAttributes::extractDone(['complete' => 'baz']));
    $this->assertTrue(SpecialAttributes::extractDone(['done' => TRUE]));
    $this->assertNull(SpecialAttributes::extractDone(['other' => 'baz']));
  }

  public function testExtractExpires() {
    $this->assertSame('P1D', SpecialAttributes::extractExpires(['redo' => 'P1D']));
    $this->assertNull(SpecialAttributes::extractExpires(['other' => 'baz']));
  }

  public function testExtractFixture() {
    $this->assertSame('foo', SpecialAttributes::extractFixture(['fixture' => 'foo']));
    $this->assertNull(SpecialAttributes::extractFixture(['other' => 'baz']));
  }
}
