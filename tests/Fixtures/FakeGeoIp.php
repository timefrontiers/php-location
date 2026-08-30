<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests\Fixtures;

use TimeFrontiers\GeoIP\GeoIPInterface;
use TimeFrontiers\GeoIP\LocationData;

final class FakeGeoIp implements GeoIPInterface {
  public int $calls = 0;
  /** @var list<string> */
  public array $ips = [];
  private \Closure $callback;

  /** @param callable(string): LocationData $callback */
  public function __construct(callable $callback) {
    $this->callback = \Closure::fromCallable($callback);
  }

  public function locate(string $ip):LocationData {
    $this->calls++;
    $this->ips[] = $ip;
    return ($this->callback)($ip);
  }
}
