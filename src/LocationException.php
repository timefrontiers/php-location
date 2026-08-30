<?php

declare(strict_types=1);

namespace TimeFrontiers;

/** Safe public failure for location configuration, resolution, and lookup. */
final class LocationException extends \RuntimeException {

  private ?\SensitiveParameterValue $internalType;

  private function __construct(
    private readonly string $reason,
    string $message,
    ?\Throwable $internal = null
  ) {
    parent::__construct($message);
    $this->internalType = $internal === null
      ? null
      : new \SensitiveParameterValue($internal::class);
  }

  public static function invalidIp():self {
    return new self('invalid_ip', 'The IP address is invalid or is not allowed by policy.');
  }

  public static function clientIpUnavailable():self {
    return new self('client_ip_unavailable', 'A client IP address was not provided or resolved.');
  }

  public static function providerNotConfigured():self {
    return new self('provider_not_configured', 'A location provider must be configured before lookup.');
  }

  public static function providerFailure(?\Throwable $internal = null):self {
    return new self('provider_failure', 'The location provider could not complete the lookup.', $internal);
  }

  public static function transportFailure(?\Throwable $internal = null):self {
    return new self('transport_failure', 'The location provider transport failed.', $internal);
  }

  public static function invalidProviderResponse():self {
    return new self('invalid_provider_response', 'The location provider returned an invalid response.');
  }

  public static function invalidConfiguration(?\Throwable $internal = null):self {
    return new self('invalid_configuration', 'The location provider configuration is invalid.', $internal);
  }

  public static function enrichmentFailure(?\Throwable $internal = null):self {
    return new self('enrichment_failure', 'Location enrichment could not be completed.', $internal);
  }

  public function reason():string {
    return $this->reason;
  }

  /** @return array{reason: string, internal_context_recorded: bool} */
  public function __debugInfo():array {
    return [
      'reason' => $this->reason,
      'internal_context_recorded' => $this->internalType !== null,
    ];
  }
}
