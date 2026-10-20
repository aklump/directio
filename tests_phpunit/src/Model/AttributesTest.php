<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Model;

use AKlump\Directio\Model\Attributes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Model\Attributes
 *
 * @uses \AKlump\Directio\Config\SpecialAttributes
 */
class AttributesTest extends TestCase {

  public function testEmptyToString() {
    $this->assertSame('', (string) (new Attributes([])));
  }

  public function testToString() {
    $attributes = new Attributes([
      'id' => 'bump_perms',
      'redo' => 'P1M',
      'done' => TRUE,
      'deprecated' => FALSE,
      'title' => 'Lorem Ipsum',
    ]);
    $this->assertSame('#bump_perms redo=P1M done title="Lorem Ipsum"', (string) $attributes);
  }

  public function testNameKeyAlsoGetsHashShorthand() {
    $attributes = new Attributes(['name' => 'foo']);
    $this->assertSame('#foo', (string) $attributes);
  }

  public function testDoneKeyIsKeptAsWritten() {
    $attributes = new Attributes(['x' => TRUE, 'id' => 'foo']);
    $this->assertSame('x #foo', (string) $attributes);
  }

  public function testIdWithUnsupportedCharactersFallsBackToKeyValueForm() {
    // A character outside "#name"'s grammar (here a stray U+02D9, as found
    // in a real surveyed document) can't use the "#value" shorthand, but it
    // also doesn't itself need quoting.
    $id = "page_not_found_redirect\u{02D9}s";
    $attributes = new Attributes(['id' => $id]);
    $this->assertSame(sprintf('id=%s', $id), (string) $attributes);
  }

  public function testIdWithUnsupportedCharactersAndWhitespaceIsQuoted() {
    $id = "page not found redirect\u{02D9}s";
    $attributes = new Attributes(['id' => $id]);
    $this->assertSame(sprintf('id="%s"', $id), (string) $attributes);
  }

  public function testValueContainingABraceIsQuoted() {
    $attributes = new Attributes(['foo' => 'a}b']);
    $this->assertSame('foo="a}b"', (string) $attributes);
  }

  public function testValueContainingAnOpeningBraceIsQuoted() {
    $attributes = new Attributes(['foo' => 'a{b']);
    $this->assertSame('foo="a{b"', (string) $attributes);
  }

  public function testEmptyValueIsQuoted() {
    $attributes = new Attributes(['id' => 'x', 'redo' => '']);
    $this->assertSame('#x redo=""', (string) $attributes);
  }

  public function testEmptyIdIsWrittenAsKeyValue() {
    $attributes = new Attributes(['id' => '']);
    $this->assertSame('id=""', (string) $attributes);
  }

  public function testValueContainingAQuoteCannotBeRendered() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('"foo"');
    (string) new Attributes(['foo' => 'a"b']);
  }
}
