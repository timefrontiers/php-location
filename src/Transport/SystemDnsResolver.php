<?php

declare(strict_types=1);

namespace TimeFrontiers\Transport;

/** Native DNS lookup boundary; security validation belongs to PublicDnsResolver. */
final readonly class SystemDnsResolver implements DnsResolverInterface {

  public function resolve(string $host):array {
    $records = @\dns_get_record($host, DNS_A | DNS_AAAA);
    if ($records === false) {
      return [];
    }
    $addresses = [];
    foreach ($records as $record) {
      $candidate = $record['ip'] ?? $record['ipv6'] ?? null;
      if (\is_string($candidate)) {
        $addresses[] = $candidate;
      }
    }
    return $addresses;
  }
}
