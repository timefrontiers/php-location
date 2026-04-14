<?php

declare(strict_types=1);

namespace TimeFrontiers;

use TimeFrontiers\GeoIP\GeoIPInterface;
use TimeFrontiers\GeoIP\IpApiService;
use TimeFrontiers\GeoIP\LocationData;

/**
 * Get visitor's location information from IP address.
 *
 * Errors are stored in protected $_errors and can be retrieved via getErrors().
 */
class Location {
  public string $ip;
  public string $city;
  public ?string $city_code = null;
  public string $state;
  public ?string $state_code = null;
  public string $country;
  public string $country_code;
  public string $currency_code;
  public string $currency_symbol;
  public float $latitude;
  public float $longitude;

  protected array $_errors = [];
  private GeoIPInterface $_geo_ip;

  /**
   * @param string|null $ip IP address to locate. If null, uses client's IP.
   * @param GeoIPInterface|null $geo_ip Custom GeoIP service implementation.
   * @throws LocationException If location cannot be determined.
   */
  public function __construct(?string $ip = null, ?GeoIPInterface $geo_ip = null)
  {
    $this->_geo_ip = $geo_ip ?? new IpApiService();
    $this->refresh($ip);
  }

  /**
   * Refreshes location data for the given IP.
   *
   * @param string|null $ip IP address. If null, uses client's IP.
   * @return bool True on success, false on failure (errors available via getErrors()).
   */
  public function refresh(?string $ip = null): bool
  {
    try {
      $target_ip = $ip ?? $this->_getClientIp();
      if (!$target_ip) {
        throw new LocationException('Unable to determine client IP address');
      }

      $data = $this->_geo_ip->locate($target_ip);

      $this->ip = $data->ip;
      $this->city = $data->city;
      $this->state = $data->region;
      $this->country = $data->country;
      $this->country_code = $data->country_code;
      $this->currency_code = $data->currency_code;
      $this->currency_symbol = $data->currency_symbol;
      $this->latitude = $data->latitude;
      $this->longitude = $data->longitude;

      return true;
    } catch (\Throwable $e) {
      $this->_addError('refresh', $e->getCode() ?: 500, $e->getMessage(), $e->getFile(), $e->getLine());
      return false;
    }
  }

  /**
   * Returns all collected errors.
   *
   * @return array
   */
  public function getErrors(): array  {
    return $this->_errors;
  }

  /**
   * Gets the client's IP address from server variables.
   */
  private function _getClientIp(): ?string  {
    $keys = [
      'HTTP_CLIENT_IP',
      'HTTP_X_FORWARDED_FOR',
      'HTTP_X_FORWARDED',
      'HTTP_X_CLUSTER_CLIENT_IP',
      'HTTP_FORWARDED_FOR',
      'HTTP_FORWARDED',
      'REMOTE_ADDR',
    ];

    foreach ($keys as $key) {
      if (!empty($_SERVER[$key])) {
        $ips = \explode(',', $_SERVER[$key]);
        $ip = \trim($ips[0]);
        if (\filter_var($ip, \FILTER_VALIDATE_IP)) {
          return $ip;
        }
      }
    }

    return null;
  }

  /**
   * Adds an error to the internal collection.
   *
   * @param string $context Error context (method name).
   * @param int $code Error code.
   * @param string $message Error message.
   * @param string $file File where error occurred.
   * @param int $line Line number.
   */
  protected function _addError(
    string $context,
    int $code,
    string $message,
    string $file,
    int $line
  ): void {
    $this->_errors[$context][] = [
      0, // min_rank (0 = everyone)
      $code,
      $message,
      $file,
      $line,
    ];
  }
}