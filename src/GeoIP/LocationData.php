<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

/**
 * Data Transfer Object for location information.
 */
class LocationData {
  public function __construct(
    public readonly string $ip,
    public readonly string $city,
    public readonly string $region,
    public readonly string $country,
    public readonly string $country_code,
    public readonly string $currency_code,
    public readonly string $currency_symbol,
    public readonly float $latitude,
    public readonly float $longitude
  ) {}
}