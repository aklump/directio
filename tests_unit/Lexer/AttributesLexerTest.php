<?php

namespace AKlump\Directio\Tests\Unit\Lexer;

use AKlump\Directio\Lexer\AttributesLexer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Lexer\AttributesLexer
 */
class AttributesLexerTest extends TestCase {

  public function testTwoAttributes(): void {
    $this->lexer = new AttributesLexer();
    $this->lexer->setInput('id=check foo bar expires=P5D');
    $this->lexer->moveNext();

    $this->lexer->skipUntil(AttributesLexer::T_ATTRIBUTE_NAME);
    $this->assertSame('id', $this->lexer->lookahead->value);
    $this->lexer->skipUntil(AttributesLexer::T_ATTRIBUTE_VALUE);
    $this->assertSame('check foo bar', $this->lexer->lookahead->value);

    $this->lexer->skipUntil(AttributesLexer::T_ATTRIBUTE_NAME);
    $this->assertSame('expires', $this->lexer->lookahead->value);
    $this->lexer->skipUntil(AttributesLexer::T_ATTRIBUTE_VALUE);
    $this->assertSame('P5D', $this->lexer->lookahead->value);
  }

  public function testOneAttribute(): void {
    $this->lexer = new AttributesLexer();
    $this->lexer->setInput('id=check');
    $this->lexer->moveNext();

    $this->lexer->skipUntil(AttributesLexer::T_ATTRIBUTE_NAME);
    $this->assertSame('id', $this->lexer->lookahead->value);
    $this->lexer->skipUntil(AttributesLexer::T_ATTRIBUTE_VALUE);
    $this->assertSame('check', $this->lexer->lookahead->value);
  }


}
