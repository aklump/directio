<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit\Lexer;

use AKlump\Directio\Lexer\TaskLexer;
use AKlump\Directio\Model\Attributes;
use DomainException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Lexer\TaskLexer
 * @uses   \AKlump\Directio\Model\Attributes
 * @uses   \AKlump\Directio\Lexer\AttributesLexer
 * @uses   \AKlump\Directio\TextProcessor\ParseAttributes
 */
class TaskLexerTest extends TestCase {

  public function testNestedTagThrows() {
    $this->expectException(DomainException::class);
    $this->expectExceptionMessage('foo>bar');
    $lexer = new TaskLexer();
    $lexer->setInput('<!-- directio [] id=foo --><!-- directio [] id=bar --><!-- /directio --><!-- /directio -->');
    $lexer->moveNext();
    $lexer->skipUntil(TaskLexer::T_CLOSE_TAG);
  }

  public function testOnlyBooleanAttributes() {
    $lexer = $this->createLexer(['[]' => TRUE, 'done' => TRUE], 'lorem');
    $lexer->skipUntil(TaskLexer::T_OPEN_TAG);
    $lexer->moveNext();
    $this->assertSame('<!-- directio [] done -->', $lexer->token->value);
  }

  public function testOpenTagWithoutAttributes() {
    $lexer = $this->createLexer([], "## Check Foo\n\nlorem ipsum\n");
    $lexer->skipUntil(TaskLexer::T_OPEN_TAG);
    $lexer->moveNext();
    $this->assertSame('<!-- directio -->', $lexer->token->value);

    $lexer->skipUntil(TaskLexer::T_OPEN_TAG);
    $this->assertNull($lexer->lookahead);
  }

  public function testCloseTag() {
    $lexer = $this->createLexer(['id' => 'checkFoo']);
    $lexer->skipUntil(TaskLexer::T_CLOSE_TAG);
    $lexer->moveNext();
    $this->assertSame('<!-- /directio -->', $lexer->token->value);
  }

  public function testOpenTag() {
    $lexer = $this->createLexer(['id' => 'checkFoo']);
    $lexer->skipUntil(TaskLexer::T_OPEN_TAG);
    $lexer->moveNext();
    $this->assertSame('<!-- directio id=checkFoo -->', $lexer->token->value);
  }

  protected function createLexer(array $attributes, string $inner_text = 'lorem ipsum dolar sit amet'): TaskLexer {
    $content = sprintf('<!-- directio %s-->', ltrim(' ' . (new Attributes($attributes)) . ' '));
    $content .= PHP_EOL . PHP_EOL;
    $content .= $inner_text . PHP_EOL;
    $content .= '<!-- /directio -->' . PHP_EOL;

    $lexer = new TaskLexer();
    $lexer->setInput($content);
    $lexer->moveNext();

    return $lexer;
  }

}
