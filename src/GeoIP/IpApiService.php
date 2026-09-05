<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

use TimeFrontiers\CurrencySymbols;
use TimeFrontiers\LocationException;
use TimeFrontiers\Transport\HttpRequestOptions;
use TimeFrontiers\Transport\HttpResponse;
use TimeFrontiers\Transport\HttpTransportException;
use TimeFrontiers\Transport\HttpTransportInterface;

/** Explicit HTTPS adapter for the ip-api JSON schema or a compatible gateway. */
final readonly class IpApiService implements GeoIPInterface {

  private const FIELDS = 'status,country,countryCode,regionName,city,lat,lon,currency,query,continent,continentCode';

  private HttpRequestOptions $options;
  private ?\SensitiveParameterValue $authorization;
  private string $host;

  public function __construct(
    private HttpTransportInterface $transport,
    private string $endpoint,
    #[\SensitiveParameter] ?string $authorization = null,
    ?HttpRequestOptions $options = null
  ) {
    $parts = \parse_url($endpoint);
    if (
      !\is_array($parts)
      || \strtolower((string)($parts['scheme'] ?? '')) !== 'https'
      || !isset($parts['host'])
      || isset($parts['user'])
      || isset($parts['pass'])
      || isset($parts['query'])
      || isset($parts['fragment'])
    ) {
      throw LocationException::invalidConfiguration();
    }
    $this->host = \strtolower((string)$parts['host']);
    $this->authorization = $authorization === null || $authorization === ''
      ? null
      : new \SensitiveParameterValue($authorization);
    $this->options = $options ?? new HttpRequestOptions();
  }

  public function locate(string $ip):LocationData {
    $ip = IpAddressPolicy::publicOnly()->normalize($ip);
    $url = \rtrim($this->endpoint, '/') . '/' . \rawurlencode($ip)
      . '?' . \http_build_query(['fields' => self::FIELDS], '', '&', PHP_QUERY_RFC3986);
    $headers = ['Accept: application/json'];
    if ($this->authorization !== null) {
      $headers[] = 'Authorization: ' . $this->authorization->getValue();
    }
    try {
      $response = $this->transport->get($url, $headers, $this->options);
    } catch (HttpTransportException $error) {
      throw LocationException::transportFailure($error);
    } catch (\Throwable $error) {
      throw LocationException::transportFailure($error);
    }
    return $this->decode($response, $ip);
  }

  private function decode(HttpResponse $response, string $requestedIp):LocationData {
    if (
      $response->status !== 200
      || !self::isJsonContentType($response->contentType)
      || \strlen($response->body) > $this->options->maximumResponseBytes
    ) {
      throw LocationException::invalidProviderResponse();
    }
    try {
      $data = \json_decode($response->body, true, 32, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
      throw LocationException::invalidProviderResponse();
    }
    if (!\is_array($data) || ($data['status'] ?? null) !== 'success') {
      throw LocationException::invalidProviderResponse();
    }
    $responseIp = self::requiredString($data, 'query', 64);
    if (IpAddressPolicy::publicOnly()->normalize($responseIp) !== $requestedIp) {
      throw LocationException::invalidProviderResponse();
    }
    $currency = self::optionalCode($data, 'currency', 3);
    return new LocationData(
      ip: $requestedIp,
      city: self::optionalString($data, 'city', 160),
      region: self::optionalString($data, 'regionName', 160),
      country: self::optionalString($data, 'country', 160),
      country_code: self::optionalCode($data, 'countryCode', 2),
      currency_code: $currency,
      currency_symbol: CurrencySymbols::get($currency),
      latitude: self::coordinate($data, 'lat'),
      longitude: self::coordinate($data, 'lon'),
      continent: self::optionalString($data, 'continent', 160),
      continent_code: self::optionalCode($data, 'continentCode', 2)
    );
  }

  /** @param array<array-key, mixed> $data */
  private static function requiredString(array $data, string $field, int $maximumBytes):string {
    $value = $data[$field] ?? null;
    if (!\is_string($value) || $value === '' || \strlen($value) > $maximumBytes) {
      throw LocationException::invalidProviderResponse();
    }
    return $value;
  }

  /** @param array<array-key, mixed> $data */
  private static function optionalString(array $data, string $field, int $maximumBytes):string {
    $value = $data[$field] ?? '';
    if (!\is_string($value) || \strlen($value) > $maximumBytes) {
      throw LocationException::invalidProviderResponse();
    }
    return $value;
  }

  /** @param array<array-key, mixed> $data */
  private static function optionalCode(array $data, string $field, int $length):string {
    $value = self::optionalString($data, $field, $length);
    $value = \strtoupper($value);
    if ($value !== '' && !\preg_match('/\A[A-Z]{' . $length . '}\z/D', $value)) {
      throw LocationException::invalidProviderResponse();
    }
    return $value;
  }

  /** @param array<array-key, mixed> $data */
  private static function coordinate(array $data, string $field):float {
    $value = $data[$field] ?? null;
    if (!\is_int($value) && !\is_float($value)) {
      throw LocationException::invalidProviderResponse();
    }
    return (float)$value;
  }

  private static function isJsonContentType(string $value):bool {
    $type = \strtolower(\trim(\explode(';', $value, 2)[0]));
    return $type === 'application/json' || \str_ends_with($type, '+json');
  }

  /** @return array{provider_host: string, authorization_configured: bool} */
  public function __debugInfo():array {
    return [
      'provider_host' => $this->host,
      'authorization_configured' => $this->authorization !== null,
    ];
  }

  /** @return never */
  public function __serialize():array {
    throw new \LogicException('Configured location providers cannot be serialized.');
  }
}
