<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\IO;

use AKlump\Directio\Model\TaskState;
use AKlump\Directio\Model\TaskStateInterface;
use PDO;
use RuntimeException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ReadState {

  /**
   * @param string $path
   *
   * @return \AKlump\Directio\Model\TaskStateInterface[]
   *
   * @throws \RuntimeException If the state cannot be read.
   */
  public function __invoke(string $path): array {
    $this->migrateIfNecessary($path);
    if (!file_exists($path)) {
      throw new RuntimeException(sprintf('Missing file: %s; try directio init.', $path));
    }
    $pdo = $this->getPdo($path);
    $stmt = $pdo->query('SELECT * FROM task_state');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(function ($row) {
      return (new TaskState())
        ->setId($row['id'])
        ->setCompleted($row['completed'])
        ->setRedo($row['redo'])
        ->setEnv($row['env'])
        ->setUser($row['user']);
    }, $rows);
  }

  public function readById(string $path, string $id): ?TaskStateInterface {
    $this->migrateIfNecessary($path);
    if (!file_exists($path)) {
      return NULL;
    }
    $pdo = $this->getPdo($path);
    $stmt = $pdo->prepare('SELECT * FROM task_state WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      return NULL;
    }

    return (new TaskState())
      ->setId($row['id'])
      ->setCompleted($row['completed'])
      ->setRedo($row['redo'])
      ->setEnv($row['env'])
      ->setUser($row['user']);
  }

  public function has(string $path, string $id): bool {
    $this->migrateIfNecessary($path);
    if (!file_exists($path)) {
      return FALSE;
    }
    $pdo = $this->getPdo($path);
    $stmt = $pdo->prepare('SELECT 1 FROM task_state WHERE id = :id');
    $stmt->execute(['id' => $id]);

    return (bool) $stmt->fetchColumn();
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

  private function migrateIfNecessary(string $path): void {
    if (file_exists($path)) {
      return;
    }
    $json_path = preg_replace('/\.sqlite$/', '.json', $path);
    if (!file_exists($json_path)) {
      return;
    }

    $data = file_get_contents($json_path);
    if (empty($data)) {
      return;
    }

    $normalizers = [new ArrayDenormalizer(), new ObjectNormalizer()];
    $encoders = [new JsonEncoder()];
    $serializer = new Serializer($normalizers, $encoders);
    $states = $serializer->deserialize($data, TaskState::class . '[]', 'json');

    $pdo = $this->getPdo($path);
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare("INSERT INTO task_state (id, completed, redo, env, user) VALUES (:id, :completed, :redo, :env, :user)");
      foreach ($states as $state) {
        $stmt->execute([
          'id' => $state->getId(),
          'completed' => $state->getCompleted(),
          'redo' => $state->getRedo(),
          'env' => $state->getEnv(),
          'user' => $state->getUser(),
        ]);
      }
      $pdo->commit();
      rename($json_path, $json_path . '.bak');
    }
    catch (\Exception $e) {
      $pdo->rollBack();
      throw new RuntimeException(sprintf('Migration failed: %s', $e->getMessage()), 0, $e);
    }
  }

}
