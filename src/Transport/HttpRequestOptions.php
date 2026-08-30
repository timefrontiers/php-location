<?php

declare(strict_types=1);

namespace TimeFrontiers\Transport;

final readonly class HttpRequestOptions {
  public function __construct(
    public int $connectTimeoutMilliseconds = 1000,
    public int $totalTimeoutMilliseconds = 3000,
    public int $maximumResponseBytes = 65536
  ) {
    if (
      $connectTimeoutMilliseconds < 1
      || $connectTimeoutMilliseconds > 5000
      || $totalTimeoutMilliseconds < $connectTimeoutMilliseconds
      || $totalTimeoutMilliseconds > 10000
      || $maximumResponseBytes < 1
      || $maximumResponseBytes > 1048576
    ) {
      throw new \InvalidArgumentException('HTTP request bounds are outside the supported range.');
    }
  }
}
