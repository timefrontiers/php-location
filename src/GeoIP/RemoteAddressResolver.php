<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

/** Default resolver: reads REMOTE_ADDR only and never trusts forwarding headers. */
final readonly class RemoteAddressResolver implements ClientIpResolverInterface {

  /** @var array<string, mixed> */
  private array $server;

  /** @param array<string, mixed>|null $server */
  public function __construct(?array $server = null) {
    $this->server = $server ?? $_SERVER;
  }

  public function resolve():?string {
    $remote = $this->server['REMOTE_ADDR'] ?? null;
    return \is_string($remote) && \trim($remote) !== '' ? $remote : null;
  }
}
