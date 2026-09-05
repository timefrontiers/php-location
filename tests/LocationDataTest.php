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
    self::assertSame('', $data->continent);
    self::assertSame('', $data->continent_code);
    self::assertTrue((new \ReflectionClass($data))->isReadOnly());
  }

  public function testContinentNameAndCodeAreNormalized():void {
    $data = new LocationData(
      ip: '8.8.8.8',
      city: 'Lagos',
      region: 'Lagos',
      country: 'Nigeria',
      country_code: 'NG',
      currency_code: 'NGN',
      currency_symbol: '',
      latitude: 6.455,
      longitude: 3.384,
      continent: ' Africa ',
      continent_code: ' af '
    );
    self::assertSame('Africa', $data->continent);
    self::assertSame('AF', $data->continent_code);
  }

  /** @param array{continent: string, continent_code: string} $continent */
  #[DataProvider('supportedContinents')]
  public function testEveryCanonicalContinentCodeIsAccepted(array $continent):void {
    $data = new LocationData(
      ip: '8.8.8.8',
      city: 'City',
      region: 'Region',
      country: 'Country',
      country_code: 'US',
      currency_code: 'USD',
      currency_symbol: '$',
      latitude: 1.0,
      longitude: 2.0,
      continent: $continent['continent'],
      continent_code: $continent['continent_code']
    );
    self::assertSame($continent['continent'], $data->continent);
    self::assertSame($continent['continent_code'], $data->continent_code);
  }

  /** @return iterable<string, array{array{continent: string, continent_code: string}}> */
  public static function supportedContinents():iterable {
    yield 'AF' => [['continent' => 'Africa', 'continent_code' => 'AF']];
    yield 'AN' => [['continent' => 'Antarctica', 'continent_code' => 'AN']];
    yield 'AS' => [['continent' => 'Asia', 'continent_code' => 'AS']];
    yield 'EU' => [['continent' => 'Europe', 'continent_code' => 'EU']];
    yield 'NA' => [['continent' => 'North America', 'continent_code' => 'NA']];
    yield 'OC' => [['continent' => 'Oceania', 'continent_code' => 'OC']];
    yield 'SA' => [['continent' => 'South America', 'continent_code' => 'SA']];
  }

  public function testEmptyContinentRemainsValidForCustomProviders():void {
    $data = new LocationData(
      ip: '8.8.8.8',
      city: 'City',
      region: 'Region',
      country: 'Country',
      country_code: 'US',
      currency_code: 'USD',
      currency_symbol: '$',
      latitude: 1.0,
      longitude: 2.0
    );
    self::assertSame('', $data->continent);
    self::assertSame('', $data->continent_code);
  }

  public function testNamedConstructionWithoutContinentArgumentsRemainsValid():void {
    $data = new LocationData(
      ip: '8.8.8.8',
      city: 'City',
      region: 'Region',
      country: 'Country',
      country_code: 'US',
      currency_code: 'USD',
      currency_symbol: '$',
      latitude: 1.0,
      longitude: 2.0,
      city_code: 'CITY-1',
      region_code: 'STATE-1'
    );
    self::assertSame('CITY-1', $data->city_code);
    self::assertSame('', $data->continent);
    self::assertSame('', $data->continent_code);
  }

  public function testWithHostCodesPreservesContinentFields():void {
    $data = new LocationData(
      ip: '8.8.8.8',
      city: 'Lagos',
      region: 'Lagos',
      country: 'Nigeria',
      country_code: 'NG',
      currency_code: 'NGN',
      currency_symbol: '',
      latitude: 6.455,
      longitude: 3.384,
      continent: 'Africa',
      continent_code: 'AF'
    );
    $enriched = $data->withHostCodes('LNK-CITY', 'LNK-STATE');
    self::assertSame('LNK-CITY', $enriched->city_code);
    self::assertSame('LNK-STATE', $enriched->region_code);
    self::assertSame('Africa', $enriched->continent);
    self::assertSame('AF', $enriched->continent_code);
    self::assertSame('NGN', $enriched->currency_code);
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
    yield 'unknown continent code' => [['continent_code' => 'XX']];
    yield 'malformed continent code' => [['continent_code' => 'AFR']];
    yield 'numeric continent code' => [['continent_code' => '12']];
    yield 'continent control character' => [['continent' => "Africa\0zone"]];
  }
}
