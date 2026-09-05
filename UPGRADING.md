# Upgrading PHP-Location

## From 1.1.x to 1.2.x

PHP-Location 1.2 adds provider-supplied continent name and code to the same
explicit lookup. Construction still performs no provider or network call.

### Read continent from the existing snapshot

```php
$location = $lookup->locate($trustedClientIp);

$location->continent;       // Africa
$location->continent_code;  // AF
$location->country;         // Nigeria
$location->country_code;    // NG
```

Canonical continent codes are `AF`, `AN`, `AS`, `EU`, `NA`, `OC`, and `SA`.
Unknown nonempty codes fail closed. Custom `GeoIPInterface` implementations may
leave both fields empty; the package does not maintain a hidden
country-to-continent table.

Existing positional and named `LocationData` construction remains valid. The
new arguments are optional and appended after `city_code` and `region_code`.
`withHostCodes()` retains continent data. `GeoIPInterface::locate()` is
unchanged.

### Continent is not currency policy

Existing `currency_code` and `currency_symbol` fields are not removed or made
mandatory. Do not use them, or continent, as a currency recommendation. Helper
and other consumers must apply their own configured currency mapping.

### Legacy adapter

Deprecated `TimeFrontiers\Location` adds initialized `continent` (`''`) and
`continent_code` (`null`). Values are copied only after a successful
`refresh()`. A failed refresh preserves the previous snapshot, including
continent. Prefer `LocationLookup::locate()`.

## From 1.0.x to 1.1.x

PHP-Location 1.1 requires PHP 8.5 and changes lookup from an implicit constructor
side effect to an explicit, injected operation.

### Replace constructor lookup

Version 1.0 performed a plaintext request during `new Location()`. Version 1.1
never looks up during construction. New code uses:

```php
$lookup = new LocationLookup($configuredProvider, $trustedResolver);
$data = $lookup->locate($explicitIp); // immutable LocationData
```

The deprecated property adapter requires an explicit `refresh()`:

```php
$location = new Location($ip, $configuredProvider);
if (!$location->refresh()) {
    // Handle the safe error; all properties remain initialized.
}
```

### Move proxy policy to the host

The package ignores every forwarding header. Omitted IP lookup uses
`REMOTE_ADDR` only. If a trusted reverse proxy owns the connection, inject a
host resolver that validates the proxy peer and parses the trusted chain using
the host's single established policy. Do not duplicate proxy logic here.

### Configure a provider explicitly

- Remote production providers must use HTTPS.
- The supplied cURL transport requires an exact allowed-host list and bounded
  connect/total timeouts and response bytes.
- The supplied direct transport disables all environment-derived proxies.
  Proxy use requires a separate explicit transport and security policy.
- Credentials belong in authorization/configuration, never a query string.
- The free ip-api HTTP endpoint and direct pro query-key integration are not
  supported. Use an HTTPS gateway or another injected provider.
- Provider JSON must identify the requested IP and pass strict schema checks.

### Move Linktude enrichment out

The package no longer reads globals or a Linktude database. Implement
`LocationDataEnricherInterface` in the host if application city/state codes are
needed. Use prepared deterministic queries, treat `false` as database failure,
distinguish no row, and escape `%`, `_`, and the escape character before any
approved `LIKE` match.

### Review IP and privacy policy

Public provider lookup follows maintained IANA registry snapshots and rejects
false, blank, conditional, or N/A global-reachability entries plus unallocated
IPv6 global-unicast space and transition forms that can embed non-public IPv4.
Review the three linked IANA registries in README before each release. An
explicit `IpAddressPolicy::allowNonPublic()` is available for a local/private data source.
Before rollout, record lawful basis/consent, purpose, provider, cache lifetime,
precision, retention and deletion. There is no package cache or persistence.

### MaxMind and display symbols

MaxMind is optional and loaded only through `fromDatabase()` or an injected
reader. Public failures do not contain raw driver messages or database paths.
Currency symbols are normalized display hints only; the currency code remains
authoritative.

### Deployment order

1. Configure and test the provider/transport without production traffic.
2. Inject the host's trusted resolver and optional enrichment adapter.
3. Migrate callers from constructor properties to explicit immutable results.
4. Define cache/retention/deletion and monitoring without sensitive logs.
5. Remove the deprecated `Location` adapter in a later major release.

### Linktude compatibility hold

Known Linktude callers still construct `Location`: `linktude/php-event` while
recording events and `linktude/php-user` while enriching session audit rows.
The root, Event development manifest, and User development manifest therefore
require `~1.0.3`, which excludes 1.1. The root lock and installed package remain
at v1.0.3.

Before Location v1.1 is published, release consumer revisions containing that
hold (User after v1.1.0 and Event before any compatible widening). Existing
published consumer manifests still say `^1.0`, so uncommitted source pins alone
are not release authorization. After those holds are published, migrate each
consumer to an explicit configured lookup/result or deliberately omit optional
geolocation; test the real path, release it, and only then widen its constraint.
`dev/php-location-v1.1-consumer-hold-smoke.php` makes this order executable.
