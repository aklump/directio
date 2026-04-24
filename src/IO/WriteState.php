<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\IO;

use AKlump\Directio\Model\TaskStateInterface;
use PDO;
use RuntimeException;

class WriteState {

  /**
   * @param string $path
   * @param \AKlump\Directio\Model\TaskStateInterface[] $state
   *
   * @return void
   *
   * @throws \RuntimeException If the file can't be written.
   *
   */
  public function __invoke(string $path, array $state): void {
    $this->writeMany($path, $state);
  }

  public function writeOne(string $path, TaskStateInterface $state): void {
    $pdo = $this->getPdo($path);
    $sql = "INSERT INTO task_state (id, completed, redo, env, user)
            VALUES (:id, :completed, :redo, :env, :user)
            ON CONFLICT(id) DO UPDATE SET
              completed = excluded.completed,
              redo = excluded.redo,
              env = excluded.env,
              user = excluded.user";
    $stmt = $pdo->prepare($sql);
    try {
      $stmt->execute([
        'id' => $state->getId(),
        'completed' => $state->getCompleted(),
        'redo' => $state->getRedo(),
        'env' => $state->getEnv(),
        'user' => $state->getUser(),
      ]);
    }
    catch (\Exception $e) {
      throw new RuntimeException(sprintf('Failed to write to "%s": %s', $path, $e->getMessage()), 0, $e);
    }
  }

  public function writeMany(string $path, array $state): void {
    $pdo = $this->getPdo($path);
    $pdo->beginTransaction();
    try {
      foreach ($state as $item) {
        $this->writeOne($path, $item);
      }
      $pdo->commit();
    }
    catch (\Exception $e) {
      $pdo->rollBack();
      throw new RuntimeException(sprintf('Failed to write to "%s": %s', $path, $e->getMessage()), 0, $e);
    }
  }

  private function getPdo(string $path): PDO {
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_state (
      id TEXT PRIMARY KEY,
      completed TEXT NOT NULL DEFAULT '',
      redo TEXT NOT NULL DEFAULT '',
      env TEXT NOT NULL DEFAULT '',
      user TEXT NOT NULL DEFAULT ''
    )");

    return $pdo;
  }

}
