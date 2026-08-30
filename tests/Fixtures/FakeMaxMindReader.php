<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests\Fixtures;

use TimeFrontiers\GeoIP\MaxMindReaderInterface;

final class FakeMaxMindReader implements MaxMindReaderInterface {
  private \Closure $callback;

  /** @param callable(string): object $callback */
  public function __construct(callable $callback) {
    $this->callback = \Closure::fromCallable($callback);
  }

  public function city(string $ip):object {
    return ($this->callback)($ip);
  }
}
