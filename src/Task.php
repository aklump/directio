<?php

namespace AKlump\Directio;

class Task implements TaskInterface {

  protected string $id;

  protected string $completed;

  protected string $expires;

  protected string $env;

  protected string $user;

  public function isComplete(): bool {
    // TODO Add logic here.
    return TRUE;
  }

  public function setId(string $id): self {
    $this->id = $id;

    return $this;
  }

  public function setCompleted(string $completed): self {
    $this->completed = $completed;

    return $this;
  }

  public function setExpires(string $expires): self {
    $this->expires = $expires;

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

  public function getExpires(): string {
    return $this->expires;
  }

  public function getEnv(): string {
    return $this->env;
  }

  public function getUser(): string {
    return $this->user;
  }


}
