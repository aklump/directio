<?php

namespace AKlump\Directio\Model;

interface TaskInterface {

  public function isComplete(): bool;

  public function getId(): string;

}
