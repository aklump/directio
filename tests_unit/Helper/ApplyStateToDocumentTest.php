<?php

namespace Helper;

use AKlump\Directio\Helper\ApplyStateToDocument;
use AKlump\Directio\Model\Document;
use AKlump\Directio\Model\TaskState;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Helper\ApplyStateToDocument
 * @uses \AKlump\Directio\Lexer\AttributesLexer
 * @uses \AKlump\Directio\Lexer\TaskLexer
 * @uses \AKlump\Directio\Model\Document
 * @uses \AKlump\Directio\Model\TaskState
 * @uses \AKlump\Directio\TextProcessor\ParseAttributes
 */
class ApplyStateToDocumentTest extends TestCase {

  const CONTENT = <<<EOD
  <!-- directio [] id=foo redo=P1D -->
  foobar
  <!-- /directio -->
  EOD;

  public static function dataForInvokeProvider(): array {
    $tests = [];

    // The task was completed and it's not yet time to redo it.
    $tests[] = [
      date_create('2025-01-01T10:55:53-08:00'),
      [
        [
          'id' => 'foo',
          'completed' => '2025-01-01T06:55:53-08:00',
          'redo' => '2025-01-02T06:55:53-08:00',
        ],
      ],
      '',
    ];

    // The task is completed, but it's time to redo it.
    $tests[] = [
      date_create('2025-01-23T15:23:37Z'),
      [
        [
          'id' => 'foo',
          'completed' => '2025-01-01T06:55:53-08:00',
          'redo' => '2025-01-02T06:55:53-08:00',
        ],
      ],
      self::CONTENT,
    ];


    // The task was never completed.
    $tests[] = [
      date_create('2025-01-23T15:23:37Z'),
      [],
      self::CONTENT,
    ];


    return $tests;
  }

  /**
   * @dataProvider dataForInvokeProvider
   */
  public function testInvoke(DateTimeInterface $now, array $state_array, string $expected) {
    $state = $this->createStateFromArray($state_array);
    $document = new Document();
    $document->setContent(self::CONTENT);
    $result = (new ApplyStateToDocument($now))($state, $document);
    $this->assertSame($expected, $result->getContent());
  }

  /**
   * @param array $state_array
   *
   * @return \AKlump\Directio\Model\TaskStateInterface[]
   */
  private function createStateFromArray(array $state_array): array {
    $state = [];
    foreach ($state_array as $data) {
      $item = new TaskState();
      $item->setId($data['id']);
      $item->setCompleted($data['completed']);
      $item->setRedo($data['redo']);
      $state[] = $item;
    }

    return $state;
  }


}
