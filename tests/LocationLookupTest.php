<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\TestCase;
use TimeFrontiers\GeoIP\IpAddressPolicy;
use TimeFrontiers\GeoIP\LocationDataEnricherInterface;
use TimeFrontiers\GeoIP\LocationLookup;
use TimeFrontiers\GeoIP\RemoteAddressResolver;
use TimeFrontiers\LocationException;
use TimeFrontiers\Tests\Fixtures\FakeGeoIp;
use TimeFrontiers\Tests\Fixtures\IpCorpus;
use TimeFrontiers\Tests\Fixtures\LocationFixture;

final class LocationLookupTest extends TestCase {

  public function testConstructionPerformsNoLookupOrNetworkSideEffect():void {
    $provider = new FakeGeoIp(static fn (string $ip) => LocationFixture::forIp($ip));
    new LocationLookup($provider);
    self::assertSame(0, $provider->calls);
  }

  public function testOmittedIpUsesOnlyRemoteAddressAndIgnoresSpoofedHeaders():void {
    $provider = new FakeGeoIp(static fn (string $ip) => LocationFixture::forIp($ip));
    $resolver = new RemoteAddressResolver([
      'REMOTE_ADDR' => '8.8.8.8',
      'HTTP_X_FORWARDED_FOR' => '1.1.1.1',
      'HTTP_X_REAL_IP' => '9.9.9.9',
    ]);
    $result = (new LocationLookup($provider, $resolver))->locate();

    self::assertSame('8.8.8.8', $result->ip);
    self::assertSame(['8.8.8.8'], $provider->ips);
  }

  public function testIpv6IsCanonicalizedBeforeLookup():void {
    $provider = new FakeGeoIp(static fn (string $ip) => LocationFixture::forIp($ip));
    $result = (new LocationLookup($provider))->locate('2606:4700:4700:0:0:0:0:1111');
    self::assertSame('2606:4700:4700::1111', $result->ip);
  }

  public function testPrivateReservedAndInvalidAddressesFailBeforeProviderDisclosure():void {
    foreach ([...IpCorpus::nonGlobal(), 'not-an-ip'] as $ip) {
      $provider = new FakeGeoIp(static fn (string $value) => LocationFixture::forIp($value));
      try {
        (new LocationLookup($provider))->locate($ip);
        self::fail('A non-public address should be rejected.');
      } catch (LocationException $error) {
        self::assertSame('invalid_ip', $error->reason());
        self::assertSame(0, $provider->calls);
      }
    }
  }

  public function testEveryGlobalControlReachesTheProviderInCanonicalForm():void {
    foreach (IpCorpus::globallyReachable() as $ip) {
      $provider = new FakeGeoIp(static fn (string $value) => LocationFixture::forIp($value));
      $result = (new LocationLookup($provider))->locate($ip);
      $packed = \inet_pton($ip);
      self::assertNotFalse($packed);
      self::assertSame(\strtolower((string)\inet_ntop($packed)), $result->ip);
      self::assertSame(1, $provider->calls);
    }
  }

  public function testNonPublicLookupRequiresAnExplicitPolicy():void {
    $provider = new FakeGeoIp(static fn (string $ip) => LocationFixture::forIp($ip));
    $result = (new LocationLookup(
      $provider,
      ipPolicy: IpAddressPolicy::allowNonPublic()
    ))->locate('10.0.0.1');
    self::assertSame('10.0.0.1', $result->ip);
  }

  public function testProviderMustDescribeTheRequestedIp():void {
    $provider = new FakeGeoIp(static fn () => LocationFixture::forIp('1.1.1.1'));
    $this->expectException(LocationException::class);
    (new LocationLookup($provider))->locate('8.8.8.8');
  }

  public function testHostEnrichmentIsInjectedAndReturnsANewCompleteSnapshot():void {
    $provider = new FakeGeoIp(
      static fn (string $ip) => LocationFixture::forIp($ip, continent: 'North America', continentCode: 'NA')
    );
    $enricher = new class implements LocationDataEnricherInterface {
      public function enrich(\TimeFrontiers\GeoIP\LocationData $location):\TimeFrontiers\GeoIP\LocationData {
        return $location->withHostCodes('LNK-CITY', 'LNK-STATE');
      }
    };
    $result = (new LocationLookup($provider, enricher: $enricher))->locate('8.8.8.8');
    self::assertSame('LNK-CITY', $result->city_code);
    self::assertSame('LNK-STATE', $result->region_code);
    self::assertSame('North America', $result->continent);
    self::assertSame('NA', $result->continent_code);
    self::assertSame('USD', $result->currency_code);
  }
}
