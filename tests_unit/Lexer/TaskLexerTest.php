<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Tests\Unit\Lexer;

use AKlump\Directio\Lexer\TaskLexer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Lexer\TaskLexer
 */
class TaskLexerTest extends TestCase {

  public function testOpenTagWithoutAttributes() {
    $open = '<!-- directio -->';
    $close = '<!-- /directio -->';
    $content = "$open\n\n## Check Foo\n\nlorem ipsum\n\n$close\n";
    $this->lexer = new TaskLexer();
    $this->lexer->setInput($content);
    $this->lexer->moveNext();

    $this->lexer->skipUntil(TaskLexer::T_OPEN_TAG);
    $this->assertSame('<!-- directio -->', $this->lexer->lookahead->value);

    $this->lexer->skipUntil(TaskLexer::T_ATTRIBUTES);
    $this->assertNull($this->lexer->lookahead);
  }

  public function testCloseTag() {
    $this->lexer->skipUntil(TaskLexer::T_CLOSE_TAG);
    $this->assertSame('<!-- /directio -->', $this->lexer->lookahead->value);
  }

  public function testAttributes() {
    $this->lexer->skipUntil(TaskLexer::T_ATTRIBUTES);
    $this->assertSame('id=checkFoo', $this->lexer->lookahead->value);
  }

  public function testOpenTag() {
    $this->lexer->skipUntil(TaskLexer::T_OPEN_TAG);
    $this->assertSame('<!-- directio id=checkFoo -->', $this->lexer->lookahead->value);
  }

  protected function setUp(): void {
    $open = '<!-- directio id=checkFoo -->';
    $close = '<!-- /directio -->';
    $content = "$open\n\n## Check Foo\n\nlorem ipsum\n\n$close\n";
    $this->lexer = new TaskLexer();
    $this->lexer->setInput($content);
    $this->lexer->moveNext();
  }


}
