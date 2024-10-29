<?php

namespace AKlump\Directio;

interface TaskInterface {

  public function isComplete(): bool;

  public function getId(): string;

}
