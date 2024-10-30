<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\IO;

use AKlump\Directio\Model\TaskState;
use RuntimeException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class WriteState {

  /**
   * @param string $path
   * @param \AKlump\Directio\Model\TaskState[] $state
   *
   * @return void
   *
   */
  public function __invoke(string $path, array $state): void {
    $normalizers = [
      new ObjectNormalizer(),
      new ArrayDenormalizer(),
    ];
    $encoders = [new YamlEncoder(), new JsonEncoder()];
    $serializer = new Serializer($normalizers, $encoders);
    $format = pathinfo($path, PATHINFO_EXTENSION);

    $context = [];

    $data = $serializer->serialize($state, $format, $context);
    if (!file_put_contents($path, $data)) {
      throw new RuntimeException(sprintf('Failed to write "%s"', $path));
    }
  }

}
