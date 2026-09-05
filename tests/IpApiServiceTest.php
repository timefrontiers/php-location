<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TimeFrontiers\GeoIP\IpApiService;
use TimeFrontiers\GeoIP\IpAddressPolicy;
use TimeFrontiers\LocationException;
use TimeFrontiers\Tests\Fixtures\FakeTransport;
use TimeFrontiers\Tests\Fixtures\IpCorpus;
use TimeFrontiers\Transport\HttpRequestOptions;
use TimeFrontiers\Transport\HttpResponse;
use TimeFrontiers\Transport\HttpTransportException;

final class IpApiServiceTest extends TestCase {

  private const SECRET = 'Bearer test-super-secret';

  public function testConstructionDoesNotRequestAndLookupUsesHttpsBoundedTransport():void {
    $transport = new FakeTransport(self::success());
    $service = new IpApiService(
      $transport,
      'https://geo.example.test/json',
      self::SECRET,
      new HttpRequestOptions(250, 750, 4096)
    );
    self::assertSame(0, $transport->calls);

    $result = $service->locate('8.8.8.8');
    self::assertSame(1, $transport->calls);
    self::assertStringStartsWith('https://geo.example.test/json/8.8.8.8?', $transport->url);
    self::assertStringNotContainsString('test-super-secret', $transport->url);
    self::assertContains('Authorization: ' . self::SECRET, $transport->headers);
    self::assertSame(4096, $transport->options?->maximumResponseBytes);
    self::assertSame('8.8.8.8', $result->ip);
    self::assertSame('US', $result->country_code);
    self::assertSame('$', $result->currency_symbol);
    self::assertSame('North America', $result->continent);
    self::assertSame('NA', $result->continent_code);
    $queryString = \parse_url($transport->url, PHP_URL_QUERY);
    self::assertIsString($queryString);
    $query = [];
    \parse_str($queryString, $query);
    $fieldsValue = $query['fields'] ?? '';
    self::assertIsString($fieldsValue);
    $fields = \explode(',', $fieldsValue);
    self::assertContains('continent', $fields);
    self::assertContains('continentCode', $fields);
  }

  public function testMissingContinentFieldsRemainEmptyWithoutChangingCurrency():void {
    $payload = self::payload();
    unset($payload['continent'], $payload['continentCode']);
    $service = new IpApiService(
      new FakeTransport(new HttpResponse(
        200,
        'application/json',
        \json_encode($payload, JSON_THROW_ON_ERROR)
      )),
      'https://geo.example.test/json'
    );
    $result = $service->locate('8.8.8.8');
    self::assertSame('', $result->continent);
    self::assertSame('', $result->continent_code);
    self::assertSame('USD', $result->currency_code);
    self::assertSame('$', $result->currency_symbol);
  }

  public function testPlaintextOrCredentialBearingEndpointsAreRejected():void {
    foreach ([
      'http://geo.example.test/json',
      'https://user:password@geo.example.test/json',
      'https://geo.example.test/json?key=secret',
      'https://geo.example.test/json#fragment',
    ] as $endpoint) {
      try {
        new IpApiService(new FakeTransport(), $endpoint);
        self::fail('Unsafe endpoints must be rejected.');
      } catch (LocationException $error) {
        self::assertSame('invalid_configuration', $error->reason());
      }
    }
  }

  /** @param array<string, mixed>|string $body */
  #[DataProvider('invalidResponses')]
  public function testInvalidHttpAndProviderResponsesFailClosed(
    int $status,
    string $contentType,
    array|string $body
  ):void {
    $encoded = \is_array($body) ? \json_encode($body, JSON_THROW_ON_ERROR) : $body;
    $service = new IpApiService(
      new FakeTransport(new HttpResponse($status, $contentType, $encoded)),
      'https://geo.example.test/json'
    );
    $this->expectException(LocationException::class);
    $service->locate('8.8.8.8');
  }

  /** @return iterable<string, array{int, string, array<string, mixed>|string}> */
  public static function invalidResponses():iterable {
    yield 'status' => [503, 'application/json', self::payload()];
    yield 'content type' => [200, 'text/html', self::payload()];
    yield 'malformed JSON' => [200, 'application/json', '{'];
    yield 'provider failure' => [200, 'application/json', ['status' => 'fail', 'message' => 'raw secret']];
    yield 'mismatched IP' => [200, 'application/json', [...self::payload(), 'query' => '1.1.1.1']];
    yield 'string coordinate' => [200, 'application/json', [...self::payload(), 'lat' => '37.4']];
    yield 'invalid country code' => [200, 'application/json', [...self::payload(), 'countryCode' => 'USA']];
    yield 'unknown continent code' => [200, 'application/json', [...self::payload(), 'continentCode' => 'XX']];
    yield 'malformed continent code' => [200, 'application/json', [...self::payload(), 'continentCode' => 'AFRICA']];
  }

  public function testOversizedResponseFailsEvenWithACustomTransport():void {
    $service = new IpApiService(
      new FakeTransport(new HttpResponse(200, 'application/json', \str_repeat('x', 129))),
      'https://geo.example.test/json',
      options: new HttpRequestOptions(maximumResponseBytes: 128)
    );
    $this->expectException(LocationException::class);
    $service->locate('8.8.8.8');
  }

  public function testTimeoutAndAuthorizationRemainRedacted():void {
    $transport = new FakeTransport(failure: HttpTransportException::timeout());
    $service = new IpApiService($transport, 'https://geo.example.test/json', self::SECRET);
    self::assertStringNotContainsString('test-super-secret', \var_export($service, true));
    try {
      $service->locate('8.8.8.8');
      self::fail('Timeout should fail.');
    } catch (LocationException $error) {
      self::assertSame('transport_failure', $error->reason());
      self::assertStringNotContainsString('test-super-secret', $error->getMessage());
    }
  }

  public function testTlsFailureIsMappedWithoutLeakingTransportDetails():void {
    $transport = new FakeTransport(failure: HttpTransportException::tlsFailure());
    $service = new IpApiService($transport, 'https://geo.example.test/json', self::SECRET);
    try {
      $service->locate('8.8.8.8');
      self::fail('A TLS verification failure should fail closed.');
    } catch (LocationException $error) {
      self::assertSame('transport_failure', $error->reason());
      self::assertSame('The location provider transport failed.', $error->getMessage());
      self::assertNull($error->getPrevious());
      self::assertStringNotContainsString('test-super-secret', \var_export($error, true));
    }
  }

  public function testPrivateAddressIsRejectedBeforeTransport():void {
    foreach (IpCorpus::nonGlobal() as $ip) {
      $transport = new FakeTransport(self::success());
      $service = new IpApiService($transport, 'https://geo.example.test/json');
      try {
        $service->locate($ip);
        self::fail('A non-global IP should be rejected: ' . $ip);
      } catch (LocationException $error) {
        self::assertSame('invalid_ip', $error->reason());
        self::assertSame(0, $transport->calls);
      }
    }
  }

  public function testEveryGlobalControlPassesProviderBoundaryCanonically():void {
    foreach (IpCorpus::globallyReachable() as $ip) {
      $canonical = IpAddressPolicy::publicOnly()->normalize($ip);
      $transport = new FakeTransport(new HttpResponse(
        200,
        'application/json',
        \json_encode([...self::payload(), 'query' => $canonical], JSON_THROW_ON_ERROR)
      ));
      $result = (new IpApiService($transport, 'https://geo.example.test/json'))->locate($ip);
      self::assertSame($canonical, $result->ip);
      self::assertSame(1, $transport->calls);
    }
  }

  private static function success():HttpResponse {
    return new HttpResponse(200, 'application/json; charset=utf-8', \json_encode(self::payload(), JSON_THROW_ON_ERROR));
  }

  /** @return array<string, mixed> */
  private static function payload():array {
    return [
      'status' => 'success',
      'query' => '8.8.8.8',
      'city' => 'Mountain View',
      'regionName' => 'California',
      'country' => 'United States',
      'countryCode' => 'us',
      'currency' => 'usd',
      'continent' => 'North America',
      'continentCode' => 'na',
      'lat' => 37.4056,
      'lon' => -122.0775,
    ];
  }
}
