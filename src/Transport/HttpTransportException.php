<?php

declare(strict_types=1);

namespace TimeFrontiers\Transport;

/** Bounded transport failure with no URL, credential, or raw provider detail. */
final class HttpTransportException extends \RuntimeException {

  private function __construct(private readonly string $reason, string $message) {
    parent::__construct($message);
  }

  public static function unavailable():self {
    return new self('transport_unavailable', 'The configured HTTP transport is unavailable.');
  }

  public static function policyRejected():self {
    return new self('transport_policy_rejected', 'The HTTP request was rejected by transport policy.');
  }

  public static function timeout():self {
    return new self('transport_timeout', 'The location provider request timed out.');
  }

  public static function tlsFailure():self {
    return new self('transport_tls_failure', 'The location provider TLS connection failed.');
  }

  public static function responseTooLarge():self {
    return new self('response_too_large', 'The location provider response exceeded the configured limit.');
  }

  public static function failed():self {
    return new self('transport_failed', 'The location provider request failed.');
  }

  public function reason():string {
    return $this->reason;
  }
}
