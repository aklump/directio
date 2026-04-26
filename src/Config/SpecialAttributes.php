<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Config;

/**
 * @code
 * if (SpecialAttributes::extractDone($attributes)) {
 *   $completed_task_ids[] = SpecialAttributes::extractId($attributes);
 * }
 * @endcode
 */
class SpecialAttributes {

  /**
   * Get the keys that can be used to identify a task.
   *
   * @var array|string[] One or more keys that can be used to identify a task,
   * where the first is treated as default.
   */
  private static array $id = ['id', 'name'];

  /**
   * Get the keys that can be used to mark a task as done.
   *
   * @var array|string[] One or more keys that can be used to mark a task as
   * done, where the first is treated as default.
   */
  private static array $done = ['done', 'x', 'complete'];

  private static array $expires = ['redo'];

  private static array $fixtures = ['fixture'];

  public static function idKeys(): array {
    return array_fill_keys(self::$id, TRUE);
  }

  public static function defaultIdKey(): string {
    return reset(self::$id);
  }

  public static function doneKeys(): array {
    return array_fill_keys(self::$done, TRUE);
  }

  public static function defaultDoneKey(): string {
    return reset(self::$done);
  }

  public static function expiresKeys(): array {
    return array_fill_keys(self::$expires, TRUE);
  }

  public static function fixtureKeys(): array {
    return array_fill_keys(self::$fixtures, TRUE);
  }

  public static function extractId(array $attributes): ?string {
    foreach (self::$id as $key) {
      if (isset($attributes[$key])) {
        return (string) $attributes[$key];
      }
    }

    return NULL;
  }

  /**
   * Extract the value of the "done" attribute.
   *
   * @param array $attributes
   *
   * @return mixed The value of the first found "done" attribute, or NULL.
   */
  public static function extractDone(array $attributes): mixed {
    foreach (self::$done as $key) {
      if (isset($attributes[$key])) {
        return $attributes[$key];
      }
    }

    return NULL;
  }

  /**
   * Extract the value of the "expires" attribute.
   *
   * @param array $attributes
   *
   * @return mixed The value of the first found "expires" attribute, or NULL.
   */
  public static function extractExpires(array $attributes): mixed {
    foreach (self::$expires as $key) {
      if (isset($attributes[$key])) {
        return $attributes[$key];
      }
    }

    return NULL;
  }

  /**
   * Extract the value of the "fixture" attribute.
   *
   * @param array $attributes
   *
   * @return mixed The value of the first found "fixture" attribute, or NULL.
   */
  public static function extractFixture(array $attributes): mixed {
    foreach (self::$fixtures as $key) {
      if (isset($attributes[$key])) {
        return $attributes[$key];
      }
    }

    return NULL;
  }
}
