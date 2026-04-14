<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

interface GeoIPInterface {
  /**
   * Locate an IP address and return location data.
   *
   * @param string $ip The IP address to locate.
   * @return LocationData
   * @throws \TimeFrontiers\LocationException
   */
  public function locate(string $ip): LocationData;
}