<?php

namespace AKlump\Directio\Helper;

use AKlump\Directio\Lexer\TaskLexer;
use AKlump\Directio\Model\Document;
use AKlump\Directio\Model\DocumentInterface;
use AKlump\Directio\TextProcessor\ParseAttributes;
use AKlump\Directio\Config\SpecialAttributes;

/**
 * Adds the 'done' attribute to a specific task in a document.
 */
final class MarkTaskDoneInDocument {

  /**
   * @param string $taskId The ID of the task to mark as done.
   * @param DocumentInterface $document The document to modify.
   *
   * @return DocumentInterface A new document with the task marked as done.
   */
  public function __invoke(string $taskId, DocumentInterface $document): DocumentInterface {
    $content = $document->getContent();
    $lexer = new TaskLexer();
    $lexer->setInput($content);
    $lexer->moveNext();

    $parseAttributes = new ParseAttributes();
    $updates = [];

    while (TRUE) {
      $lexer->skipUntil(TaskLexer::T_OPEN_TAG);
      if (NULL === $lexer->lookahead) {
        break;
      }
      $lexer->moveNext();

      $originalTag = $lexer->token->value;
      $attributes = $parseAttributes($originalTag);
      $this_id = SpecialAttributes::extractId($attributes);

      if ($this_id === $taskId) {
        if (!SpecialAttributes::extractDone($attributes)) {
          $newTag = $this->addDoneAttribute($originalTag);
          $updates[] = [
            'pos' => $lexer->token->position,
            'len' => strlen($originalTag),
            'new' => $newTag,
          ];
        }
      }
    }

    if (empty($updates)) {
      return $document;
    }

    // Apply updates from right to left to maintain positions
    $updates = array_reverse($updates);
    foreach ($updates as $update) {
      $content = substr_replace($content, $update['new'], $update['pos'], $update['len']);
    }

    return (new Document())->setContent($content);
  }

  /**
   * Adds 'done' attribute to the open tag.
   *
   * @param string $tag e.g. '<directio id="foo">' or '<directio id="foo" redo="P1D">'
   *
   * @return string e.g. '<directio done id="foo">' or '<directio done id="foo" redo="P1D">'
   */
  private function addDoneAttribute(string $tag): string {
    $done_attribute = SpecialAttributes::defaultDoneKey();
    // We want to insert the attribute right after '<directio'
    if (preg_match('/^(<directio)(\s|>)/i', $tag, $matches)) {
      $prefix = $matches[1];
      $rest = substr($tag, strlen($prefix));
      if (trim($rest) === '>') {
        return sprintf('%s %s>', $prefix, $done_attribute);
      }

      return sprintf('%s %s%s', $prefix, $done_attribute, $rest);
    }

    return $tag;
  }
}
