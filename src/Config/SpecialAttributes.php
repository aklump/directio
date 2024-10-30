<?php

namespace AKlump\Directio\Config;

/**
 * @code
 * if (array_intersect_key($attributes, SpecialAttributes::doneKeys())) {
 *   $completed_task_ids[] = $attributes['id'];
 * }
 * @endcode
 */
class SpecialAttributes {

  private static array $id = ['id', 'name'];

  private static array $done = ['[x]', 'done', 'complete'];

  public static function idKeys(): array {
    return array_fill_keys(self::$id, TRUE);
  }

  public static function doneKeys(): array {
    return array_fill_keys(self::$done, TRUE);
  }
}
