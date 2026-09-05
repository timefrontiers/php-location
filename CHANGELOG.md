# Changelog

## 1.2.0 - 2026-09-05

### Added

- Provider-supplied `continent` and `continent_code` on immutable
  `LocationData`, with canonical codes `AF`, `AN`, `AS`, `EU`, `NA`, `OC`, and
  `SA`. Empty values remain valid for custom providers that cannot supply them.
- `IpApiService` requests `continent` and `continentCode` and validates them
  through the existing bounded decoding path.
- `MaxMindService` copies continent name and code from the reader continent
  object when present and otherwise leaves them empty.
- Legacy `Location` adapter properties `continent` and `continent_code`.

### Changed

- `withHostCodes()` preserves continent fields on the returned snapshot.

### Notes

- Continent is geographic data only. This release does not derive, recommend,
  or select a currency. Existing currency fields are unchanged.

## 1.1.0 - 2026-08-30

### Added

- Explicit `LocationLookup`, immutable validated `LocationData`, public/non-
  public IP policies, `REMOTE_ADDR`-only resolution, and a host enrichment seam.
- HTTPS-only bounded cURL transport with host allowlisting, public DNS checks,
  DNS pinning, TLS verification, redirects disabled, timeouts, and byte limits.
- Maintained longest-prefix IANA registry policy shared by lookup, providers,
  literal transport targets, and DNS-answer validation.
- Optional narrow MaxMind reader boundary with redacted adapter failures.
- PHPUnit, PHPStan level 8, parallel lint, Composer gates, and PHP 8.5 CI.

### Changed

- Require PHP 8.5 and ext-json; accurately suggest ext-curl and geoip2/geoip2.
- Make every lookup explicit; constructing services or the legacy adapter never
  performs network access.
- Validate/normalize IPv4 and IPv6 and reject private/reserved disclosure to
  public remote providers.
- Normalize the currency map, remove duplicate `LKR`, and define symbols as
  display-only hints.

### Deprecated

- Mutable `TimeFrontiers\Location` properties and `refresh()` in favor of
  `LocationLookup::locate()` and immutable `LocationData`.

### Removed

- Forwarding-header trust, the plaintext/free ip-api default, query-string API
  keys, `file_get_contents()` HTTP, Linktude globals/SQL enrichment, and raw
  provider error/file/line exposure.

### Security

- Remote requests require explicit HTTPS configuration and bounded transport.
- The direct cURL transport explicitly disables ambient proxy environment
  variables so they cannot bypass validated and pinned destination resolution.
- Provider responses must be successful JSON for the requested IP and satisfy
  strict field, code, coordinate, content-type, status, and size checks.
- Legacy refresh is total and atomic: failure cannot leave uninitialized or
  partially updated public state.
