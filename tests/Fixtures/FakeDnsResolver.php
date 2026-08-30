<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests\Fixtures;

use TimeFrontiers\Transport\DnsResolverInterface;

final class FakeDnsResolver implements DnsResolverInterface {
  public int $calls = 0;

  /** @param list<string> $addresses */
  public function __construct(private array $addresses) {}

  public function resolve(string $host):array {
    $this->calls++;
    return $this->addresses;
  }
}
