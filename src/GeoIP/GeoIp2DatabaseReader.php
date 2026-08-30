<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

use TimeFrontiers\LocationException;

/** Optional geoip2/geoip2 adapter loaded only when explicitly configured. */
final class GeoIp2DatabaseReader implements MaxMindReaderInterface {

  private \Closure $cityLookup;

  public function __construct(#[\SensitiveParameter] string $databasePath) {
    if (!\is_file($databasePath) || !\is_readable($databasePath)) {
      throw LocationException::invalidConfiguration();
    }
    $readerClass = 'GeoIp2\\Database\\Reader';
    if (!\class_exists($readerClass)) {
      throw LocationException::invalidConfiguration();
    }
    try {
      $reader = new $readerClass($databasePath);
      if (!\is_callable([$reader, 'city'])) {
        throw LocationException::invalidConfiguration();
      }
      $this->cityLookup = \Closure::fromCallable([$reader, 'city']);
    } catch (\Throwable $error) {
      throw LocationException::invalidConfiguration($error);
    }
  }

  public function city(string $ip):object {
    try {
      $record = ($this->cityLookup)($ip);
    } catch (\Throwable $error) {
      throw LocationException::providerFailure($error);
    }
    if (!\is_object($record)) {
      throw LocationException::invalidProviderResponse();
    }
    return $record;
  }

  /** @return array{configured: true} */
  public function __debugInfo():array {
    return ['configured' => true];
  }

  /** @return never */
  public function __serialize():array {
    throw new \LogicException('MaxMind readers cannot be serialized.');
  }
}
