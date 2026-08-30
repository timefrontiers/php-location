<?php

declare(strict_types=1);

namespace TimeFrontiers\Transport;

/** HTTPS-only cURL transport with host allowlisting, DNS pinning, and byte bounds. */
final readonly class CurlHttpTransport implements HttpTransportInterface {

  /** @var list<string> */
  private array $allowedHosts;
  private PublicDnsResolver $dnsResolver;

  /** @param non-empty-list<string> $allowedHosts */
  public function __construct(array $allowedHosts, ?PublicDnsResolver $dnsResolver = null) {
    if (!\extension_loaded('curl')) {
      throw HttpTransportException::unavailable();
    }
    $normalized = [];
    foreach ($allowedHosts as $host) {
      $host = self::normalizeHost($host);
      if ($host === '' || !self::validHost($host)) {
        throw new \InvalidArgumentException('The HTTP transport host allowlist is invalid.');
      }
      $normalized[] = $host;
    }
    $this->allowedHosts = \array_values(\array_unique($normalized));
    $this->dnsResolver = $dnsResolver ?? new PublicDnsResolver();
  }

  public function get(string $url, array $headers, HttpRequestOptions $options):HttpResponse {
    [$authorizedUrl, $host, $resolved] = $this->authorizeUrl($url);
    $safeHeaders = [];
    foreach ($headers as $header) {
      if ($header === '' || \str_contains($header, "\r") || \str_contains($header, "\n")) {
        throw HttpTransportException::policyRejected();
      }
      $safeHeaders[] = $header;
    }

    $handle = \curl_init();
    if ($handle === false) {
      throw HttpTransportException::unavailable();
    }
    $body = '';
    $tooLarge = false;
    $write = static function (\CurlHandle $unused, string $chunk) use (&$body, &$tooLarge, $options):int {
      unset($unused);
      if (\strlen($body) + \strlen($chunk) > $options->maximumResponseBytes) {
        $tooLarge = true;
        return 0;
      }
      $body .= $chunk;
      return \strlen($chunk);
    };
    $curlOptions = [
      CURLOPT_URL => $authorizedUrl,
      CURLOPT_HTTPGET => true,
      CURLOPT_HTTPHEADER => $safeHeaders,
      CURLOPT_HEADER => false,
      CURLOPT_RETURNTRANSFER => false,
      CURLOPT_WRITEFUNCTION => $write,
      CURLOPT_CONNECTTIMEOUT_MS => $options->connectTimeoutMilliseconds,
      CURLOPT_TIMEOUT_MS => $options->totalTimeoutMilliseconds,
      CURLOPT_NOSIGNAL => true,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_MAXREDIRS => 0,
      CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
      CURLOPT_PROXY => '',
      CURLOPT_NOPROXY => '*',
      CURLOPT_USERAGENT => 'timefrontiers-php-location/1.1',
    ];
    if (\filter_var($host, FILTER_VALIDATE_IP) === false) {
      $addresses = \array_map(
        static fn (string $ip):string => \str_contains($ip, ':') ? '[' . $ip . ']' : $ip,
        $resolved
      );
      $curlOptions[CURLOPT_RESOLVE] = [$host . ':443:' . \implode(',', $addresses)];
    }
    $configured = \curl_setopt_array($handle, $curlOptions);
    if (!$configured) {
      throw HttpTransportException::unavailable();
    }
    $executed = \curl_exec($handle);
    if ($tooLarge) {
      throw HttpTransportException::responseTooLarge();
    }
    if ($executed === false) {
      $errorCode = \curl_errno($handle);
      if ($errorCode === CURLE_OPERATION_TIMEDOUT) {
        throw HttpTransportException::timeout();
      }
      if (\in_array($errorCode, [
        CURLE_SSL_CONNECT_ERROR,
        CURLE_SSL_CACERT,
        CURLE_SSL_CERTPROBLEM,
        CURLE_SSL_CIPHER,
        CURLE_SSL_CACERT_BADFILE,
      ], true)) {
        throw HttpTransportException::tlsFailure();
      }
      throw HttpTransportException::failed();
    }
    return new HttpResponse(
      (int)\curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
      (string)(\curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?: ''),
      $body
    );
  }

  /** @return array{non-empty-string, string, non-empty-list<string>} */
  private function authorizeUrl(string $url):array {
    if ($url === '') {
      throw HttpTransportException::policyRejected();
    }
    $parts = \parse_url($url);
    if (!\is_array($parts)) {
      throw HttpTransportException::policyRejected();
    }
    $scheme = \strtolower((string)($parts['scheme'] ?? ''));
    $host = self::normalizeHost((string)($parts['host'] ?? ''));
    if (
      $scheme !== 'https'
      || $host === ''
      || isset($parts['user'])
      || isset($parts['pass'])
      || isset($parts['fragment'])
      || (isset($parts['port']) && $parts['port'] !== 443)
      || !\in_array($host, $this->allowedHosts, true)
    ) {
      throw HttpTransportException::policyRejected();
    }
    return [$url, $host, $this->dnsResolver->resolve($host)];
  }

  private static function validHost(string $host):bool {
    return \filter_var($host, FILTER_VALIDATE_IP) !== false
      || (
        \strlen($host) <= 253
        && \preg_match('/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/D', $host) === 1
      );
  }

  private static function normalizeHost(string $host):string {
    $host = \strtolower(\trim($host));
    if (\str_starts_with($host, '[') && \str_ends_with($host, ']')) {
      $host = \substr($host, 1, -1);
    }
    return \filter_var($host, FILTER_VALIDATE_IP) !== false ? $host : \rtrim($host, '.');
  }
}
