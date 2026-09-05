<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests\Fixtures;

use TimeFrontiers\GeoIP\LocationData;

final class LocationFixture {
  public static function forIp(
    string $ip,
    string $city = 'Mountain View',
    string $continent = '',
    string $continentCode = ''
  ):LocationData {
    return new LocationData(
      ip: $ip,
      city: $city,
      region: 'California',
      country: 'United States',
      country_code: 'US',
      currency_code: 'USD',
      currency_symbol: '$',
      latitude: 37.4056,
      longitude: -122.0775,
      continent: $continent,
      continent_code: $continentCode
    );
  }
}
