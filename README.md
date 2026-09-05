# TimeFrontiers PHP Location

Explicit, privacy-aware IP geolocation for PHP 8.5 applications. Construction
never sends a network request, forwarding headers are never trusted by default,
and provider results are returned as complete immutable snapshots.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.5-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Requirements

- PHP 8.5+
- ext-json
- ext-curl only when using the supplied `CurlHttpTransport`
- `geoip2/geoip2` only when using `MaxMindService::fromDatabase()`

```bash
composer require timefrontiers/php-location
```

## Explicit lookup

Inject a configured provider, then call `locate()` explicitly. Creating the
provider or lookup object has no network side effect.

```php
use TimeFrontiers\GeoIP\LocationLookup;
use TimeFrontiers\GeoIP\MaxMindService;

$lookup = new LocationLookup(
    MaxMindService::fromDatabase('/run/geoip/GeoLite2-City.mmdb'),
);

$location = $lookup->locate($trustedClientIp);
echo $location->continent;       // Africa
echo $location->continent_code;  // AF
echo $location->country;         // Nigeria
echo $location->country_code;    // NG
```

`LocationData` exposes provider-supplied continent name and code alongside
country. Canonical continent codes are `AF`, `AN`, `AS`, `EU`, `NA`, `OC`, and
`SA`. A custom provider that cannot supply continent data may leave both fields
empty. Continent is geographic data only; this package does not derive,
recommend, or select a currency from it. The caller obtains `$trustedClientIp`
at its host boundary. The package trusts only its configured resolver and does
not infer trusted-proxy behavior.

If no IP is supplied, the default resolver reads only `REMOTE_ADDR`. It ignores
`Forwarded`, `X-Forwarded-For`, `X-Real-IP`, and every other forwarding header.
A host behind a trusted proxy must inject a `ClientIpResolverInterface` whose
implementation has already applied that host's trusted-proxy policy.

Public lookup uses explicit snapshots of the current IANA IPv4/IPv6 special-
purpose and IPv6 allocated-global-unicast registries. It accepts a special
range only when IANA marks global reachability affirmatively true; false,
blank, conditional, and N/A ranges fail closed. Longest-prefix decisions retain
the affirmative anycast allocations inside broader reserved blocks. IPv4-
mapped/compatible, 6to4, TEREDO, private-embedded NAT64, unallocated, multicast,
documentation, link-local, loopback, and reserved forms are rejected before
calling the provider. A local database or application-owned resolver may deliberately inject
`IpAddressPolicy::allowNonPublic()`; public remote services such as
`IpApiService` still enforce public addresses at their own boundary.

The embedded registry snapshot is dated 2025-10-09/10. Review it against the
[IANA IPv4 special-purpose registry](https://www.iana.org/assignments/iana-ipv4-special-registry),
[IANA IPv6 special-purpose registry](https://www.iana.org/assignments/iana-ipv6-special-registry),
and [IANA IPv6 global-unicast registry](https://www.iana.org/assignments/ipv6-unicast-address-assignments)
before each release. Registry drift must fail closed and receive corpus tests.

## Bounded HTTPS JSON provider

`IpApiService` is an adapter for the ip-api JSON response shape or a compatible
application gateway. It requires an injected transport and an explicit HTTPS
endpoint.

```php
use TimeFrontiers\GeoIP\IpApiService;
use TimeFrontiers\GeoIP\LocationLookup;
use TimeFrontiers\Transport\CurlHttpTransport;
use TimeFrontiers\Transport\HttpRequestOptions;

$transport = new CurlHttpTransport(['geo.example.com']);
$provider = new IpApiService(
    transport: $transport,
    endpoint: 'https://geo.example.com/ip-api-json',
    authorization: 'Bearer ' . $_ENV['GEO_GATEWAY_TOKEN'],
    options: new HttpRequestOptions(
        connectTimeoutMilliseconds: 750,
        totalTimeoutMilliseconds: 2500,
        maximumResponseBytes: 32768,
    ),
);

$location = (new LocationLookup($provider))->locate('1.1.1.1');
```

The supplied cURL transport permits HTTPS only, verifies TLS, disables
redirects, requires an exact host allowlist, rejects private/reserved DNS
answers, pins the approved DNS result for the request, bounds connect/total
timeouts, and aborts oversized responses. It explicitly disables `HTTPS_PROXY`,
`ALL_PROXY`, and every other ambient libcurl proxy through handle options, so
validated DNS pinning always describes the direct path. Proxying is not a
feature of this transport; an application that needs it must supply a separate
transport with an explicit proxy allowlist, TLS, authentication, resolution,
and redaction policy. `IpApiService` additionally requires
HTTP 200 JSON, validates every consumed field and coordinate, and verifies that
the response describes the requested IP.

The public ip-api free endpoint is intentionally unsupported because it is
HTTP-only and disallows commercial use. Direct ip-api pro authentication is
also not built in because its key is a GET parameter; v1.1 never puts secrets
in URLs. Use an application-owned HTTPS gateway that accepts an authorization
header, or inject another `GeoIPInterface` implementation. Authorization is
redacted from debug state and provider objects cannot be serialized.

## Host enrichment

This package contains no Linktude globals, SQL, schemas, or database dependency.
Applications that need their own city/state codes may inject a
`LocationDataEnricherInterface`. An enricher receives a complete immutable
snapshot and must return another one, for example with `withHostCodes()`.
Database implementations remain in the host and must use prepared,
deterministic queries, distinguish database failure from no match, and escape
wildcards if fuzzy matching is deliberately retained.

## MaxMind

`MaxMindService` accepts the narrow `MaxMindReaderInterface`; use
`fromDatabase()` when `geoip2/geoip2` is installed. Capability, path readability,
record shape, and coordinate ranges are validated. Continent name and code are
read from the MaxMind continent object when present and otherwise left empty;
the adapter does not infer continent from country. Raw MaxMind exceptions and
database paths are not copied into public errors.

## Legacy `Location` adapter

`TimeFrontiers\Location` and `refresh()` remain deprecated migration adapters.
Construction no longer performs a lookup. Every public property starts with a
safe value, including `continent` and `continent_code`, and `refresh()` assigns
a complete validated snapshot only after lookup and enrichment succeed. Failure
preserves the previous snapshot, including continent, and records only a safe
message with blank file/line fields.

```php
$legacy = new \TimeFrontiers\Location('8.8.8.8', $provider);
$legacy->refresh();
echo $legacy->country_code;
```

Prefer `LocationLookup::locate()` and immutable `LocationData` in new code.

## Currency symbols

`CurrencySymbols::get()` normalizes a code to uppercase and returns a
display-only symbol hint. Unknown codes return the normalized code. It is not an
ISO currency authority and must not decide settlement currency or money rules.
Existing `currency_code` and `currency_symbol` fields remain for compatibility;
consumers that need currency policy must apply their own configured mapping.

## Privacy and retention

An IP address is personal data in many jurisdictions. Before enabling remote
lookup, the application owner must document the lawful basis or consent,
specific purpose, provider/subprocessor, disclosed fields, geographic
precision, cache lifetime, retention period, deletion process, and user notice.

The package provides no cache or persistence by default. Minimize precision and
retention, do not log requested IPs, provider bodies, authorization, or exact
coordinates unless the documented purpose requires them, and never reuse
location collected for one purpose in an unrelated decision.

## Quality gates

```bash
composer validate --strict
composer audit
composer check
```

## License

[MIT License](LICENSE)
