<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit\Model;

use AKlump\Directio\Model\Attributes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Model\Attributes
 */
class AttributesTest extends TestCase {

  public function testEmptyToString() {
    $this->assertSame('', (string) (new Attributes([])));
  }
  public function testToString() {
    $attributes = new Attributes([
      'id' => 'foo',
      'title' => 'Lorem Ipsum',
      'done' => TRUE,
      'deprecated' => false,
      'foo' => 'some "thing"',
      'bar' => '"thing"',
    ]);
    $this->assertSame('id=foo title="Lorem Ipsum" done foo="some &quot;thing&quot;" bar=&quot;thing&quot;', (string) $attributes);
  }
}
