<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\TestCase;
use TimeFrontiers\CurrencySymbols;
use TimeFrontiers\GeoIP\MaxMindService;
use TimeFrontiers\LocationException;
use TimeFrontiers\Tests\Fixtures\FakeMaxMindReader;

final class MaxMindAndCurrencyTest extends TestCase {

  public function testMaxMindAdapterNormalizesACompleteRecord():void {
    $reader = new FakeMaxMindReader(static fn ():object => (object)[
      'city' => (object)['name' => 'Lagos'],
      'mostSpecificSubdivision' => (object)['name' => 'Lagos'],
      'country' => (object)['name' => 'Nigeria', 'isoCode' => 'NG'],
      'location' => (object)['latitude' => 6.455, 'longitude' => 3.384],
    ]);
    $data = (new MaxMindService($reader))->locate('196.6.103.73');
    self::assertSame('Lagos', $data->city);
    self::assertSame('NG', $data->country_code);
    self::assertSame('', $data->currency_code);
  }

  public function testMaxMindFailuresAreRedactedAtAdapterBoundary():void {
    $secret = 'C:\\private\\Geo.mmdb';
    $reader = new FakeMaxMindReader(static function () use ($secret):object {
      throw new \RuntimeException('Cannot read ' . $secret);
    });
    try {
      (new MaxMindService($reader))->locate('8.8.8.8');
      self::fail('Reader failure must be mapped.');
    } catch (LocationException $error) {
      self::assertSame('provider_failure', $error->reason());
      self::assertStringNotContainsString($secret, $error->getMessage());
      self::assertNull($error->getPrevious());
    }
  }

  public function testMissingMaxMindDatabaseDoesNotExposePath():void {
    $path = 'C:\\private\\missing.mmdb';
    try {
      MaxMindService::fromDatabase($path);
      self::fail('Missing database must fail.');
    } catch (LocationException $error) {
      self::assertSame('invalid_configuration', $error->reason());
      self::assertStringNotContainsString($path, $error->getMessage());
    }
  }

  public function testCurrencyMapIsNormalizedDisplayOnlyAndHasOneLkrValue():void {
    self::assertSame('₦', CurrencySymbols::get(' ngn '));
    self::assertSame('Rs', CurrencySymbols::get('LKR'));
    self::assertSame('ZZZ', CurrencySymbols::get(' zzz '));
    self::assertSame('', CurrencySymbols::get(''));
  }
}
