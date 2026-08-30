<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

use TimeFrontiers\LocationException;

/**
 * Validates and canonicalizes lookup addresses before provider disclosure.
 *
 * The registry snapshots below follow the IANA IPv4/IPv6 special-purpose and
 * IPv6 global-unicast registries last updated 2025-10-09/10. A special-purpose
 * entry is public only when IANA marks Globally Reachable as affirmatively
 * true. Blank, conditional, N/A, and false entries fail closed. Longest-prefix
 * matching preserves the explicit affirmative allocations inside broader
 * reserved blocks.
 */
final readonly class IpAddressPolicy {

  /** @var list<array{string, bool}> */
  private const array IPV4_SPECIAL_PURPOSE = [
    ['0.0.0.0/8', false],
    ['0.0.0.0/32', false],
    ['10.0.0.0/8', false],
    ['100.64.0.0/10', false],
    ['127.0.0.0/8', false],
    ['169.254.0.0/16', false],
    ['172.16.0.0/12', false],
    ['192.0.0.0/24', false],
    ['192.0.0.0/29', false],
    ['192.0.0.8/32', false],
    ['192.0.0.9/32', true],
    ['192.0.0.10/32', true],
    ['192.0.0.170/32', false],
    ['192.0.0.171/32', false],
    ['192.0.2.0/24', false],
    ['192.31.196.0/24', true],
    ['192.52.193.0/24', true],
    ['192.88.99.0/24', false],
    ['192.88.99.2/32', false],
    ['192.168.0.0/16', false],
    ['192.175.48.0/24', true],
    ['198.18.0.0/15', false],
    ['198.51.100.0/24', false],
    ['203.0.113.0/24', false],
    ['224.0.0.0/4', false],
    ['240.0.0.0/4', false],
    ['255.255.255.255/32', false],
  ];

  /** @var list<array{string, bool}> */
  private const array IPV6_SPECIAL_PURPOSE = [
    ['::/128', false],
    ['::1/128', false],
    ['::ffff:0:0/96', false],
    ['64:ff9b::/96', true],
    ['64:ff9b:1::/48', false],
    ['100::/64', false],
    ['100:0:0:1::/64', false],
    ['2001::/23', false],
    ['2001::/32', false],
    ['2001:1::1/128', true],
    ['2001:1::2/128', true],
    ['2001:1::3/128', true],
    ['2001:2::/48', false],
    ['2001:3::/32', true],
    ['2001:4:112::/48', true],
    ['2001:10::/28', false],
    ['2001:20::/28', true],
    ['2001:30::/28', true],
    ['2001:db8::/32', false],
    ['2002::/16', false],
    ['2620:4f:8000::/48', true],
    ['3fff::/20', false],
    ['5f00::/16', false],
    ['fc00::/7', false],
    ['fe80::/10', false],
    ['ff00::/8', false],
  ];

  /** @var list<string> */
  private const array IPV6_ALLOCATED_GLOBAL_UNICAST = [
    '2001:200::/23', '2001:400::/23', '2001:600::/23', '2001:800::/22',
    '2001:c00::/23', '2001:e00::/23', '2001:1200::/23', '2001:1400::/22',
    '2001:1800::/23', '2001:1a00::/23', '2001:1c00::/22', '2001:2000::/19',
    '2001:4000::/23', '2001:4200::/23', '2001:4400::/23', '2001:4600::/23',
    '2001:4800::/23', '2001:4a00::/23', '2001:4c00::/23', '2001:5000::/20',
    '2001:8000::/19', '2001:a000::/20', '2001:b000::/20', '2003::/18',
    '2400::/12', '2410::/12', '2600::/12', '2610::/23', '2620::/23',
    '2630::/12', '2800::/12', '2a00::/12', '2a10::/12', '2c00::/12',
  ];

  private function __construct(private bool $allowNonPublic) {}

  public static function publicOnly():self {
    return new self(false);
  }

  /** Explicit policy for private databases or application-owned resolvers. */
  public static function allowNonPublic():self {
    return new self(true);
  }

  public function normalize(string $ip):string {
    $ip = \trim($ip);
    if ($ip === '' || \filter_var($ip, FILTER_VALIDATE_IP) === false) {
      throw LocationException::invalidIp();
    }
    $packed = @\inet_pton($ip);
    $normalized = $packed === false ? false : @\inet_ntop($packed);
    if ($normalized === false) {
      throw LocationException::invalidIp();
    }
    if (!$this->allowNonPublic && !self::isGloballyReachable($packed)) {
      throw LocationException::invalidIp();
    }
    return \strtolower($normalized);
  }

  private static function isGloballyReachable(string $packed):bool {
    if (\strlen($packed) === 4) {
      return self::registryDecision($packed, self::IPV4_SPECIAL_PURPOSE) ?? true;
    }
    if (\strlen($packed) !== 16) {
      return false;
    }

    $special = self::registryDecision($packed, self::IPV6_SPECIAL_PURPOSE);
    if ($special !== null) {
      if (!$special) {
        return false;
      }
      if (self::matches($packed, '64:ff9b::/96')) {
        return self::registryDecision(\substr($packed, 12, 4), self::IPV4_SPECIAL_PURPOSE) ?? true;
      }
      return true;
    }

    foreach (self::IPV6_ALLOCATED_GLOBAL_UNICAST as $cidr) {
      if (self::matches($packed, $cidr)) {
        return true;
      }
    }
    return false;
  }

  /** @param list<array{string, bool}> $entries */
  private static function registryDecision(string $packed, array $entries):?bool {
    $bestPrefix = -1;
    $decision = null;
    foreach ($entries as [$cidr, $globallyReachable]) {
      $prefix = self::prefixBits($cidr);
      if ($prefix > $bestPrefix && self::matches($packed, $cidr)) {
        $bestPrefix = $prefix;
        $decision = $globallyReachable;
      }
    }
    return $decision;
  }

  private static function prefixBits(string $cidr):int {
    $separator = \strrpos($cidr, '/');
    return $separator === false ? -1 : (int)\substr($cidr, $separator + 1);
  }

  private static function matches(string $address, string $cidr):bool {
    [$networkText, $prefixText] = \explode('/', $cidr, 2);
    $network = \inet_pton($networkText);
    if ($network === false || \strlen($address) !== \strlen($network)) {
      return false;
    }
    $prefixBits = (int)$prefixText;
    $wholeBytes = \intdiv($prefixBits, 8);
    if ($wholeBytes > 0 && \substr($address, 0, $wholeBytes) !== \substr($network, 0, $wholeBytes)) {
      return false;
    }
    $remainingBits = $prefixBits % 8;
    if ($remainingBits === 0) {
      return true;
    }
    $mask = (0xff << (8 - $remainingBits)) & 0xff;
    return (\ord($address[$wholeBytes]) & $mask) === (\ord($network[$wholeBytes]) & $mask);
  }
}
