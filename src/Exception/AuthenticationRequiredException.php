<?php

namespace AKlump\Directio\Exception;

/**
 * Exception thrown when authentication is required to access a resource.
 */
class AuthenticationRequiredException extends \RuntimeException {

  private string $url;

  private ?string $finalUrl;

  private ?int $statusCode;

  private string $reason;

  public function __construct(
    string $url,
    ?string $final_url = NULL,
    ?int $status_code = NULL,
    string $reason = '',
    ?\Throwable $previous = NULL,
  ) {
    $this->url = $url;
    $this->finalUrl = $final_url;
    $this->statusCode = $status_code;
    $this->reason = $reason;

    $message = sprintf('Authentication is required to access %s.', $url);
    if ($reason) {
      $message = sprintf('Authentication is required to access %s: %s', $url, $reason);
    }

    parent::__construct($message, $status_code ?? 0, $previous);
  }

  public function getUrl(): string {
    return $this->url;
  }

  public function getFinalUrl(): ?string {
    return $this->finalUrl;
  }

  public function getStatusCode(): ?int {
    return $this->statusCode;
  }

  public function getReason(): string {
    return $this->reason;
  }

}
