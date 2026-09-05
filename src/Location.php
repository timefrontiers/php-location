<?php

declare(strict_types=1);

namespace TimeFrontiers;

use TimeFrontiers\GeoIP\ClientIpResolverInterface;
use TimeFrontiers\GeoIP\GeoIPInterface;
use TimeFrontiers\GeoIP\IpAddressPolicy;
use TimeFrontiers\GeoIP\LocationData;
use TimeFrontiers\GeoIP\LocationDataEnricherInterface;
use TimeFrontiers\GeoIP\LocationLookup;

/**
 * Initialized legacy property adapter around explicit LocationLookup.
 *
 * Construction never performs a lookup. Prefer LocationLookup::locate().
 *
 * @deprecated Use LocationLookup and immutable LocationData.
 */
class Location {
  public string $ip = '';
  public string $city = '';
  public ?string $city_code = null;
  public string $state = '';
  public ?string $state_code = null;
  public string $country = '';
  public ?string $country_code = null;
  public string $continent = '';
  public ?string $continent_code = null;
  public string $currency_code = '';
  public string $currency_symbol = '';
  public float $latitude = 0.0;
  public float $longitude = 0.0;

  /** @var array<string, list<array{int, int, string, string, int}>> */
  protected array $_errors = [];
  private ?LocationLookup $lookup;
  private ?LocationData $snapshot = null;

  public function __construct(
    private ?string $defaultIp = null,
    ?GeoIPInterface $geo_ip = null,
    ?ClientIpResolverInterface $resolver = null,
    ?IpAddressPolicy $ipPolicy = null,
    ?LocationDataEnricherInterface $enricher = null
  ) {
    $this->lookup = $geo_ip === null
      ? null
      : new LocationLookup($geo_ip, $resolver, $ipPolicy, $enricher);
  }

  /** Explicit immutable lookup; does not mutate legacy public properties. */
  public function locate(?string $ip = null):LocationData {
    if ($this->lookup === null) {
      throw LocationException::providerNotConfigured();
    }
    return $this->lookup->locate($ip ?? $this->defaultIp);
  }

  /** Deprecated compatibility mutation; commits one complete validated snapshot. */
  public function refresh(?string $ip = null):bool {
    try {
      $snapshot = $this->locate($ip);
      $this->apply($snapshot);
      unset($this->_errors['refresh']);
      return true;
    } catch (LocationException $error) {
      $this->addSafeError($error);
      return false;
    } catch (\Throwable $error) {
      $this->addSafeError(LocationException::providerFailure($error));
      return false;
    }
  }

  public function data():?LocationData {
    return $this->snapshot;
  }

  /** @return array<string, list<array{int, int, string, string, int}>> */
  public function getErrors():array {
    return $this->_errors;
  }

  private function apply(LocationData $data):void {
    $this->ip = $data->ip;
    $this->city = $data->city;
    $this->city_code = $data->city_code;
    $this->state = $data->region;
    $this->state_code = $data->region_code;
    $this->country = $data->country;
    $this->country_code = $data->country_code !== '' ? $data->country_code : null;
    $this->continent = $data->continent;
    $this->continent_code = $data->continent_code !== '' ? $data->continent_code : null;
    $this->currency_code = $data->currency_code;
    $this->currency_symbol = $data->currency_symbol;
    $this->latitude = $data->latitude;
    $this->longitude = $data->longitude;
    $this->snapshot = $data;
  }

  private function addSafeError(LocationException $error):void {
    $status = \in_array($error->reason(), ['invalid_ip', 'client_ip_unavailable'], true) ? 400 : 503;
    $this->_errors['refresh'][] = [0, $status, $error->getMessage(), '', 0];
  }
}
