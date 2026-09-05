<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\TestCase;
use TimeFrontiers\GeoIP\LocationData;
use TimeFrontiers\Location;
use TimeFrontiers\LocationException;
use TimeFrontiers\Tests\Fixtures\FakeGeoIp;
use TimeFrontiers\Tests\Fixtures\LocationFixture;

final class LocationCompatibilityTest extends TestCase {

  public function testEveryPublicPropertyIsInitializedAndConstructionDoesNotLookup():void {
    $provider = new FakeGeoIp(static fn (string $ip) => LocationFixture::forIp($ip));
    $location = new Location('8.8.8.8', $provider);

    self::assertSame(0, $provider->calls);
    self::assertSame('', $location->ip);
    self::assertSame('', $location->city);
    self::assertNull($location->city_code);
    self::assertSame('', $location->state);
    self::assertNull($location->state_code);
    self::assertSame('', $location->country);
    self::assertNull($location->country_code);
    self::assertSame('', $location->continent);
    self::assertNull($location->continent_code);
    self::assertSame('', $location->currency_code);
    self::assertSame('', $location->currency_symbol);
    self::assertSame(0.0, $location->latitude);
    self::assertSame(0.0, $location->longitude);
  }

  public function testRefreshCommitsAtomicallyAndFailurePreservesPriorSnapshot():void {
    $calls = 0;
    $provider = new FakeGeoIp(static function (string $ip) use (&$calls):LocationData {
      $calls++;
      if ($calls > 1) {
        throw LocationException::providerFailure(new \RuntimeException('secret provider path C:\\secret'));
      }
      return LocationFixture::forIp($ip, 'First City', 'North America', 'NA')
        ->withHostCodes('CITY-1', 'STATE-1');
    });
    $location = new Location('8.8.8.8', $provider);

    self::assertTrue($location->refresh());
    self::assertSame('First City', $location->city);
    self::assertSame('CITY-1', $location->city_code);
    self::assertSame('North America', $location->continent);
    self::assertSame('NA', $location->continent_code);
    $snapshot = $location->data();
    self::assertInstanceOf(LocationData::class, $snapshot);
    self::assertSame('North America', $snapshot->continent);
    self::assertSame('NA', $snapshot->continent_code);

    self::assertFalse($location->refresh('1.1.1.1'));
    self::assertSame($snapshot, $location->data());
    self::assertSame('8.8.8.8', $location->ip);
    self::assertSame('First City', $location->city);
    self::assertSame('North America', $location->continent);
    self::assertSame('NA', $location->continent_code);
    $errors = $location->getErrors();
    self::assertSame('', $errors['refresh'][0][3]);
    self::assertSame(0, $errors['refresh'][0][4]);
    self::assertStringNotContainsString('secret', \serialize($errors));
  }

  public function testLocateReturnsContinentWithoutMutatingLegacyProperties():void {
    $provider = new FakeGeoIp(
      static fn (string $ip) => LocationFixture::forIp($ip, continent: 'Africa', continentCode: 'AF')
    );
    $location = new Location('8.8.8.8', $provider);
    $data = $location->locate();
    self::assertSame(1, $provider->calls);
    self::assertSame('Africa', $data->continent);
    self::assertSame('AF', $data->continent_code);
    self::assertSame('', $location->continent);
    self::assertNull($location->continent_code);
    self::assertNull($location->data());
  }

  public function testRefreshWithoutProviderFailsSafelyWithoutUninitializedState():void {
    $location = new Location();
    self::assertFalse($location->refresh('8.8.8.8'));
    self::assertSame('', $location->ip);
    self::assertSame('A location provider must be configured before lookup.', $location->getErrors()['refresh'][0][2]);
  }
}
