<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TimeFrontiers\GeoIP\IpAddressPolicy;
use TimeFrontiers\LocationException;
use TimeFrontiers\Tests\Fixtures\IpCorpus;

final class IpAddressPolicyTest extends TestCase {

  #[DataProvider('globalAddresses')]
  public function testCurrentGloballyReachableControlsAreAccepted(string $ip):void {
    $packed = \inet_pton($ip);
    self::assertNotFalse($packed);
    self::assertSame(
      \strtolower((string)\inet_ntop($packed)),
      IpAddressPolicy::publicOnly()->normalize($ip)
    );
  }

  #[DataProvider('nonGlobalAddresses')]
  public function testSpecialTransitionAndUnallocatedAddressesFailClosed(string $ip):void {
    try {
      IpAddressPolicy::publicOnly()->normalize($ip);
      self::fail('The non-global address should be rejected: ' . $ip);
    } catch (LocationException $error) {
      self::assertSame('invalid_ip', $error->reason());
    }
  }

  /** @return iterable<string, array{string}> */
  public static function globalAddresses():iterable {
    foreach (IpCorpus::globallyReachable() as $ip) {
      yield $ip => [$ip];
    }
  }

  /** @return iterable<string, array{string}> */
  public static function nonGlobalAddresses():iterable {
    foreach (IpCorpus::nonGlobal() as $ip) {
      yield $ip => [$ip];
    }
  }
}
