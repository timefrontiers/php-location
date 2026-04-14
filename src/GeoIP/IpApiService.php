<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

use TimeFrontiers\LocationException;
use TimeFrontiers\CurrencySymbols;

/**
 * Free IP geolocation service using ip-api.com.
 * Limited to 45 requests per minute for free tier.
 */
class IpApiService implements GeoIPInterface {
  private string $_api_url = 'http://ip-api.com/json/';
  private ?string $_api_key;

  /**
   * @param string|null $api_key Optional API key for commercial use.
   */
  public function __construct(?string $api_key = null)
  {
    $this->_api_key = $api_key;
    if ($api_key) {
      $this->_api_url = 'https://pro.ip-api.com/json/';
    }
  }

  public function locate(string $ip): LocationData
  {
    $url = $this->_api_url . $ip . '?fields=status,message,country,countryCode,regionName,city,lat,lon,currency,query';
    if ($this->_api_key) {
      $url .= '&key=' . $this->_api_key;
    }

    $context = stream_context_create([
      'http' => [
        'timeout' => 5,
        'ignore_errors' => true,
      ],
    ]);

    $response = @\file_get_contents($url, false, $context);
    if ($response === false) {
      throw new LocationException('Failed to connect to ip-api.com');
    }

    $data = \json_decode($response);
    if ($data === null) {
      throw new LocationException('Invalid response from ip-api.com');
    }

    if ($data->status === 'fail') {
      throw new LocationException($data->message ?? 'Unknown error from ip-api.com');
    }

    return new LocationData(
      ip: $data->query ?? $ip,
      city: $data->city ?? '',
      region: $data->regionName ?? '',
      country: $data->country ?? '',
      country_code: $data->countryCode ?? '',
      currency_code: $data->currency ?? '',
      currency_symbol: CurrencySymbols::get($data->currency ?? ''),
      latitude: (float)($data->lat ?? 0.0),
      longitude: (float)($data->lon ?? 0.0)
    );
  }
}