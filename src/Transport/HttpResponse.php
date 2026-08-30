<?php

declare(strict_types=1);

namespace TimeFrontiers\Transport;

final readonly class HttpResponse {
  public function __construct(
    public int $status,
    public string $contentType,
    public string $body
  ) {}
}
