<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

interface GeoIPInterface {
  /**
   * Explicitly locate one already-normalized IP address.
   *
   * Implementations must return a complete snapshot for the requested IP and
   * must not disclose provider details through public failures.
   *
   * @throws \TimeFrontiers\LocationException
   */
  public function locate(string $ip): LocationData;
}
