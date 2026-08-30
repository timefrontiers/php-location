<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

use TimeFrontiers\CurrencySymbols;
use TimeFrontiers\LocationException;

/** Complete immutable provider result with optional host-owned code enrichment. */
final readonly class LocationData {

  public string $ip;
  public string $city;
  public string $region;
  public string $country;
  public string $country_code;
  public string $currency_code;
  public string $currency_symbol;
  public float $latitude;
  public float $longitude;
  public ?string $city_code;
  public ?string $region_code;

  public function __construct(
    string $ip,
    string $city,
    string $region,
    string $country,
    string $country_code,
    string $currency_code,
    string $currency_symbol,
    float $latitude,
    float $longitude,
    ?string $city_code = null,
    ?string $region_code = null
  ) {
    $this->ip = IpAddressPolicy::allowNonPublic()->normalize($ip);
    $this->city = self::text($city, 160);
    $this->region = self::text($region, 160);
    $this->country = self::text($country, 160);
    $this->country_code = self::code($country_code, 2);
    $this->currency_code = self::code($currency_code, 3);
    $this->currency_symbol = self::text(
      $currency_symbol !== '' ? $currency_symbol : CurrencySymbols::get($this->currency_code),
      24
    );
    if (!\is_finite($latitude) || $latitude < -90.0 || $latitude > 90.0) {
      throw LocationException::invalidProviderResponse();
    }
    if (!\is_finite($longitude) || $longitude < -180.0 || $longitude > 180.0) {
      throw LocationException::invalidProviderResponse();
    }
    $this->latitude = $latitude;
    $this->longitude = $longitude;
    $this->city_code = self::optionalHostCode($city_code);
    $this->region_code = self::optionalHostCode($region_code);
  }

  public function withHostCodes(?string $cityCode, ?string $regionCode):self {
    return new self(
      $this->ip,
      $this->city,
      $this->region,
      $this->country,
      $this->country_code,
      $this->currency_code,
      $this->currency_symbol,
      $this->latitude,
      $this->longitude,
      $cityCode,
      $regionCode
    );
  }

  private static function text(string $value, int $maximumBytes):string {
    $value = \trim($value);
    if (
      \strlen($value) > $maximumBytes
      || \preg_match('//u', $value) !== 1
      || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/D', $value) === 1
    ) {
      throw LocationException::invalidProviderResponse();
    }
    return $value;
  }

  private static function code(string $value, int $length):string {
    $value = \strtoupper(\trim($value));
    if ($value !== '' && !\preg_match('/\A[A-Z]{' . $length . '}\z/D', $value)) {
      throw LocationException::invalidProviderResponse();
    }
    return $value;
  }

  private static function optionalHostCode(?string $value):?string {
    if ($value === null) {
      return null;
    }
    $value = \trim($value);
    if ($value === '' || !\preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/D', $value)) {
      throw LocationException::invalidProviderResponse();
    }
    return $value;
  }
}
