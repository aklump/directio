<?php

namespace AKlump\Directio\IO;

use RuntimeException;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ReadState {

  /**
   * @param string $path
   *
   * @return \AKlump\Directio\Model\TaskInterface[]
   *
   * @throws \RuntimeException If the state cannot be read.
   */
  public function __invoke(string $path): array {
    if (!file_exists($path)) {
      throw new RuntimeException(sprintf('Missing file: %s', $path));
    }
    $data = file_get_contents($path);
    if (empty($data)) {
      return [];
    }
    $normalizers = [
      new ArrayDenormalizer(),
      new ObjectNormalizer(),
    ];
    $encoders = [new YamlEncoder()];
    $serializer = new Serializer($normalizers, $encoders);
    $format = pathinfo($path, PATHINFO_EXTENSION);

    return $serializer->deserialize($data, \AKlump\Directio\Model\Task::class . '[]', $format);
  }

}
