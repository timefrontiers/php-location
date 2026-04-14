<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

use GeoIp2\Database\Reader;
use TimeFrontiers\LocationException;
use TimeFrontiers\CurrencySymbols;

class MaxMindService implements GeoIPInterface {
  private Reader $_reader;

  public function __construct(string $database_path)
  {
    if (!\file_exists($database_path)) {
      throw new LocationException('MaxMind database file not found');
    }
    $this->_reader = new Reader($database_path);
  }

  public function locate(string $ip): LocationData  {
    try {
      $record = $this->_reader->city($ip);

      return new LocationData(
        ip: $ip,
        city: $record->city->name ?? '',
        region: $record->mostSpecificSubdivision->name ?? '',
        country: $record->country->name ?? '',
        country_code: $record->country->isoCode ?? '',
        currency_code: '',
        currency_symbol: '',
        latitude: (float)($record->location->latitude ?? 0.0),
        longitude: (float)($record->location->longitude ?? 0.0)
      );
    } catch (\Throwable $e) {
      throw new LocationException($e->getMessage(), $e->getCode(), $e);
    }
  }
}