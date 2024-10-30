<?php
// SPDX-License-Identifier: BSD-3-Clause

namespace AKlump\Directio\Model;

class TaskState implements TaskStateInterface {

  protected string $id;

  protected string $completed;

  protected string $redo;

  protected string $env;

  protected string $user;

  public function setId(string $id): self {
    $this->id = $id;

    return $this;
  }

  public function setCompleted(string $completed): self {
    $this->completed = $completed;

    return $this;
  }

  public function setRedo(string $redo): self {
    $this->redo = $redo;

    return $this;
  }

  public function setEnv(string $env): self {
    $this->env = $env;

    return $this;
  }

  public function setUser(string $user): self {
    $this->user = $user;

    return $this;
  }

  public function getId(): string {
    return $this->id;
  }

  public function getCompleted(): string {
    return $this->completed;
  }

  public function getRedo(): string {
    return $this->redo;
  }

  public function getEnv(): string {
    return $this->env;
  }

  public function getUser(): string {
    return $this->user;
  }


}
