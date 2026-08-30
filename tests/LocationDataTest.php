<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TimeFrontiers\GeoIP\LocationData;
use TimeFrontiers\LocationException;

final class LocationDataTest extends TestCase {

  public function testDataIsNormalizedAndImmutable():void {
    $data = new LocationData(
      '2606:4700:4700:0:0:0:0:1111',
      ' Lagos ',
      ' Lagos ',
      ' Nigeria ',
      'ng',
      'ngn',
      '',
      6.455,
      3.384
    );
    self::assertSame('2606:4700:4700::1111', $data->ip);
    self::assertSame('Lagos', $data->city);
    self::assertSame('NG', $data->country_code);
    self::assertSame('NGN', $data->currency_code);
    self::assertSame('₦', $data->currency_symbol);
    self::assertTrue((new \ReflectionClass($data))->isReadOnly());
  }

  /** @param array<string, mixed> $changes */
  #[DataProvider('invalidFields')]
  public function testInvalidProviderFieldsAreRejected(array $changes):void {
    $arguments = [
      'ip' => '8.8.8.8', 'city' => 'City', 'region' => 'Region',
      'country' => 'Country', 'country_code' => 'US', 'currency_code' => 'USD',
      'currency_symbol' => '$', 'latitude' => 1.0, 'longitude' => 2.0,
    ];
    $arguments = [...$arguments, ...$changes];
    $this->expectException(LocationException::class);
    new LocationData(...$arguments);
  }

  /** @return iterable<string, array{array<string, mixed>}> */
  public static function invalidFields():iterable {
    yield 'bad ip' => [['ip' => 'invalid']];
    yield 'bad utf8' => [['city' => "\xFF"]];
    yield 'control character' => [['region' => "bad\0value"]];
    yield 'country code' => [['country_code' => 'USA']];
    yield 'currency code' => [['currency_code' => '12']];
    yield 'latitude' => [['latitude' => 91.0]];
    yield 'longitude' => [['longitude' => -181.0]];
    yield 'nan' => [['latitude' => NAN]];
    yield 'host code' => [['city_code' => '../unsafe']];
  }
}
