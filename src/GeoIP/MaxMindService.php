<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

use TimeFrontiers\LocationException;

final readonly class MaxMindService implements GeoIPInterface {

  public function __construct(private MaxMindReaderInterface $reader) {}

  public static function fromDatabase(#[\SensitiveParameter] string $databasePath):self {
    return new self(new GeoIp2DatabaseReader($databasePath));
  }

  public function locate(string $ip):LocationData {
    $ip = IpAddressPolicy::allowNonPublic()->normalize($ip);
    try {
      $record = $this->reader->city($ip);
      return new LocationData(
        ip: $ip,
        city: self::nestedString($record, ['city', 'name']),
        region: self::nestedString($record, ['mostSpecificSubdivision', 'name']),
        country: self::nestedString($record, ['country', 'name']),
        country_code: self::nestedString($record, ['country', 'isoCode']),
        currency_code: '',
        currency_symbol: '',
        latitude: self::nestedFloat($record, ['location', 'latitude']),
        longitude: self::nestedFloat($record, ['location', 'longitude'])
      );
    } catch (LocationException $error) {
      throw $error;
    } catch (\Throwable $error) {
      throw LocationException::providerFailure($error);
    }
  }

  /** @param non-empty-list<string> $path */
  private static function nestedString(object $record, array $path):string {
    $value = self::nested($record, $path);
    if ($value === null) {
      return '';
    }
    if (!\is_string($value)) {
      throw LocationException::invalidProviderResponse();
    }
    return $value;
  }

  /** @param non-empty-list<string> $path */
  private static function nestedFloat(object $record, array $path):float {
    $value = self::nested($record, $path);
    if ($value === null) {
      return 0.0;
    }
    if (!\is_int($value) && !\is_float($value)) {
      throw LocationException::invalidProviderResponse();
    }
    return (float)$value;
  }

  /** @param non-empty-list<string> $path */
  private static function nested(object $record, array $path):mixed {
    $value = $record;
    foreach ($path as $property) {
      if (!\is_object($value) || !isset($value->{$property})) {
        return null;
      }
      $value = $value->{$property};
    }
    return $value;
  }
}
