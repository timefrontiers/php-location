<?php

declare(strict_types=1);

namespace TimeFrontiers\Transport;

use TimeFrontiers\GeoIP\IpAddressPolicy;

/** Resolves a host and rejects the entire answer set if any answer is non-global. */
final readonly class PublicDnsResolver {

  private DnsResolverInterface $resolver;

  public function __construct(?DnsResolverInterface $resolver = null) {
    $this->resolver = $resolver ?? new SystemDnsResolver();
  }

  /** @return non-empty-list<string> */
  public function resolve(string $host):array {
    if (\filter_var($host, FILTER_VALIDATE_IP) !== false) {
      try {
        return [IpAddressPolicy::publicOnly()->normalize($host)];
      } catch (\Throwable) {
        throw HttpTransportException::policyRejected();
      }
    }

    try {
      $candidates = $this->resolver->resolve($host);
    } catch (\Throwable) {
      throw HttpTransportException::policyRejected();
    }
    $addresses = [];
    foreach ($candidates as $candidate) {
      try {
        $addresses[] = IpAddressPolicy::publicOnly()->normalize($candidate);
      } catch (\Throwable) {
        throw HttpTransportException::policyRejected();
      }
    }
    $addresses = \array_values(\array_unique($addresses));
    if ($addresses === []) {
      throw HttpTransportException::policyRejected();
    }
    return $addresses;
  }
}
