# TimeFrontiers PHP Location

A modern, flexible PHP library to retrieve visitor location information from IP address with support for multiple GeoIP providers.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Features

- **Multiple GeoIP provider support** via interface – easily switch between services.
- **Built-in free provider** using ip-api.com (no API key required).
- **Currency symbol mapping** for over 100 currencies.
- **Client IP auto-detection** from common server headers.
- **Error collection** with access‑based filtering via `InstanceError`.
- **Strict typing and modern PHP** (8.1+).
- **PSR‑4 autoloading**.

## Installation

```bash
composer require timefrontiers/php-location
```

## Requirements

- PHP 8.1 or higher
- [timefrontiers/php-instance-error](https://github.com/timefrontiers/php-instance-error) ^1.0 (optional, for error display)

## Basic Usage

### Quick Start

```php
use TimeFrontiers\Location;

$location = new Location();

echo $location->ip;              // 123.45.67.89
echo $location->city;            // Lagos
echo $location->state;           // Lagos
echo $location->country;         // Nigeria
echo $location->country_code;    // NG
echo $location->currency_code;   // NGN
echo $location->currency_symbol; // ₦
echo $location->latitude;        // 6.4474
echo $location->longitude;       // 3.3903
```

### Specify an IP Address

```php
$location = new Location('8.8.8.8');
echo $location->city; // Mountain View
```

### Refresh Location

```php
$location = new Location();
// Later...
$location->refresh('1.1.1.1');
```

## Error Handling

Errors are stored in a protected `$_errors` property and can be retrieved with `getErrors()`. Use the `InstanceError` package to filter errors based on user rank.

```php
use TimeFrontiers\Location;
use TimeFrontiers\InstanceError;

$location = new Location('invalid-ip');

if (!$location->refresh()) {
    $errors = (new InstanceError($location, false))->get();
    foreach ($errors['refresh'] as $error) {
        echo $error[2]; // Error message
    }
}
```

## GeoIP Providers

### Default: ip-api.com (free)

The library uses ip-api.com by default with no API key required. Free tier limit: 45 requests per minute.

```php
$location = new Location(); // Uses ip-api.com
```

### Using a Commercial ip-api.com Key

```php
use TimeFrontiers\GeoIP\IpApiService;

$service = new IpApiService('your-api-key');
$location = new Location(null, $service);
```

### Creating a Custom Provider

Implement the `GeoIPInterface` and pass it to the constructor.

```php
use TimeFrontiers\GeoIP\GeoIPInterface;
use TimeFrontiers\GeoIP\LocationData;
use TimeFrontiers\Location;

class MyProvider implements GeoIPInterface
{
    public function locate(string $ip): LocationData
    {
        // Fetch from your API
        return new LocationData(
            ip: $ip,
            city: '...',
            region: '...',
            country: '...',
            country_code: '...',
            currency_code: '...',
            currency_symbol: '...',
            latitude: 0.0,
            longitude: 0.0
        );
    }
}

$location = new Location(null, new MyProvider());
```

## Currency Symbols

The library includes a static map of over 100 currency codes to symbols (`CurrencySymbols::get('USD') // '$'`). If a code is not found, the code itself is returned.

## Available Properties

| Property           | Type    | Description                              |
|--------------------|---------|------------------------------------------|
| `$ip`              | string  | IP address used                          |
| `$city`            | string  | City name                                |
| `$city_code`       | ?string | Reserved for future use (currently null) |
| `$state`           | string  | Region/state name                        |
| `$state_code`      | ?string | Reserved for future use (currently null) |
| `$country`         | string  | Country name                             |
| `$country_code`    | string  | Two‑letter country code                  |
| `$currency_code`   | string  | Three‑letter currency code               |
| `$currency_symbol` | string  | Currency symbol (e.g. $, €, ₦)           |
| `$latitude`        | float   | Latitude                                 |
| `$longitude`       | float   | Longitude                                |

## Security Considerations

- IP addresses are never stored locally; they are sent to the configured GeoIP provider.
- When using a free service, be aware of rate limits and privacy policies.
- Client IP detection respects proxy headers; ensure your application is configured securely.

## License

MIT License. See [LICENSE](LICENSE) for details.
```