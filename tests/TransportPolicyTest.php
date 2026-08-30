<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\TestCase;
use TimeFrontiers\GeoIP\IpAddressPolicy;
use TimeFrontiers\Tests\Fixtures\FakeDnsResolver;
use TimeFrontiers\Tests\Fixtures\IpCorpus;
use TimeFrontiers\Transport\CurlHttpTransport;
use TimeFrontiers\Transport\HttpRequestOptions;
use TimeFrontiers\Transport\HttpTransportException;
use TimeFrontiers\Transport\PublicDnsResolver;

final class TransportPolicyTest extends TestCase {

  public function testRequestBoundsRejectUnsafeValues():void {
    foreach ([
      [0, 1000, 100], [1000, 999, 100], [1000, 11000, 100], [1000, 2000, 0],
      [1000, 2000, 1048577],
    ] as [$connect, $total, $bytes]) {
      try {
        new HttpRequestOptions($connect, $total, $bytes);
        self::fail('Unsafe transport bounds must be rejected.');
      } catch (\InvalidArgumentException) {
        self::addToAssertionCount(1);
      }
    }
  }

  public function testCurlTransportRejectsPlaintextBeforeNetworkAccess():void {
    if (!\extension_loaded('curl')) {
      self::markTestSkipped('ext-curl is not available.');
    }
    $transport = new CurlHttpTransport(['geo.example.test']);
    try {
      $transport->get('http://geo.example.test/json', [], new HttpRequestOptions());
      self::fail('Plaintext transport must be rejected.');
    } catch (HttpTransportException $error) {
      self::assertSame('transport_policy_rejected', $error->reason());
    }
  }

  public function testCurlTransportRejectsEveryNonGlobalLiteralBeforeNetworkAccess():void {
    if (!\extension_loaded('curl')) {
      self::markTestSkipped('ext-curl is not available.');
    }
    foreach (IpCorpus::nonGlobal() as $ip) {
      $transport = new CurlHttpTransport([$ip]);
      $urlHost = \str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
      try {
        $transport->get('https://' . $urlHost . '/json', [], new HttpRequestOptions());
        self::fail('A non-global SSRF target must be rejected: ' . $ip);
      } catch (HttpTransportException $error) {
        self::assertSame('transport_policy_rejected', $error->reason());
      }
    }
  }

  public function testDnsAnswersUseTheExactSameGlobalReachabilityCorpus():void {
    foreach (IpCorpus::nonGlobal() as $ip) {
      $resolver = new PublicDnsResolver(new FakeDnsResolver([$ip]));
      try {
        $resolver->resolve('geo.example.test');
        self::fail('A non-global DNS answer must be rejected: ' . $ip);
      } catch (HttpTransportException $error) {
        self::assertSame('transport_policy_rejected', $error->reason());
      }
    }

    foreach (IpCorpus::globallyReachable() as $ip) {
      $resolver = new PublicDnsResolver(new FakeDnsResolver([$ip]));
      self::assertSame(
        [IpAddressPolicy::publicOnly()->normalize($ip)],
        $resolver->resolve('geo.example.test')
      );
    }
  }

  public function testCurlTransportRejectsInjectedNonGlobalDnsAnswersBeforeRequest():void {
    if (!\extension_loaded('curl')) {
      self::markTestSkipped('ext-curl is not available.');
    }
    foreach (IpCorpus::nonGlobal() as $ip) {
      $transport = new CurlHttpTransport(
        ['geo.example.test'],
        new PublicDnsResolver(new FakeDnsResolver([$ip]))
      );
      try {
        $transport->get('https://geo.example.test/json', [], new HttpRequestOptions());
        self::fail('A non-global DNS answer must stop the transport: ' . $ip);
      } catch (HttpTransportException $error) {
        self::assertSame('transport_policy_rejected', $error->reason());
      }
    }
  }
}
