<?php

namespace AKlump\Directio\Tests\Unit\Lexer;

use AKlump\Directio\Lexer\AttributesLexer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Lexer\AttributesLexer
 */
class AttributesLexerTest extends TestCase {

  public static function dataFortestCompletionMarkProvider(): array {
    $tests = [];
    $tests[] = [
      '<!-- directio id=foo complete -->',
      'complete',
    ];
    $tests[] = [
      '<!-- directio id=foo done -->',
      'done',
    ];
    $tests[] = [
      '<!-- directio id=foo x -->',
      'x',
    ];
    $tests[] = [
      '<!-- directio id=foo X -->',
      'X',
    ];

    return $tests;
  }

  /**
   * @dataProvider dataFortestCompletionMarkProvider
   */
  public function testCompletionMark(string $attributes_markup, string $expected_key) {
    $lexer = new AttributesLexer();
    $lexer->setInput($attributes_markup);
    $lexer->moveNext();
    $result = NULL;
    while (TRUE) {
      if (NULL === $lexer->lookahead) {
        break;
      }
      $lexer->moveNext();
      if (!$lexer->token->isA(AttributesLexer::T_ATTRIBUTE_NAME)) {
        continue;
      }
      if ($lexer->token->value === $expected_key) {
        $result = $expected_key;
        break;
      }
    }
    $this->assertSame($expected_key, $result);
  }

  public function testTwoAttributes(): void {
    $lexer = new AttributesLexer();
    $lexer->setInput('id="check foo bar" expires=P5D');
    $lexer->moveNext();

    $lexer->skipUntil(AttributesLexer::T_ATTRIBUTE_NAME);
    $this->assertSame('id', $lexer->lookahead->value);
    $lexer->skipUntil(AttributesLexer::T_ATTRIBUTE_VALUE);
    $this->assertSame('check foo bar', $lexer->lookahead->value);

    $lexer->skipUntil(AttributesLexer::T_ATTRIBUTE_NAME);
    $this->assertSame('expires', $lexer->lookahead->value);
    $lexer->skipUntil(AttributesLexer::T_ATTRIBUTE_VALUE);
    $this->assertSame('P5D', $lexer->lookahead->value);
  }

  public function testOneAttribute(): void {
    $lexer = new AttributesLexer();
    $lexer->setInput('id=check');
    $lexer->moveNext();

    $lexer->skipUntil(AttributesLexer::T_ATTRIBUTE_NAME);
    $this->assertSame('id', $lexer->lookahead->value);
    $lexer->skipUntil(AttributesLexer::T_ATTRIBUTE_VALUE);
    $this->assertSame('check', $lexer->lookahead->value);
  }


}
