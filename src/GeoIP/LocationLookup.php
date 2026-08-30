<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

use TimeFrontiers\LocationException;

/** Explicit, side-effect-free-on-construction geolocation coordinator. */
final readonly class LocationLookup {

  private ClientIpResolverInterface $resolver;
  private IpAddressPolicy $ipPolicy;

  public function __construct(
    private GeoIPInterface $provider,
    ?ClientIpResolverInterface $resolver = null,
    ?IpAddressPolicy $ipPolicy = null,
    private ?LocationDataEnricherInterface $enricher = null
  ) {
    $this->resolver = $resolver ?? new RemoteAddressResolver();
    $this->ipPolicy = $ipPolicy ?? IpAddressPolicy::publicOnly();
  }

  public function locate(?string $ip = null):LocationData {
    $target = $ip ?? $this->resolver->resolve();
    if ($target === null) {
      throw LocationException::clientIpUnavailable();
    }
    $target = $this->ipPolicy->normalize($target);

    try {
      $data = $this->provider->locate($target);
    } catch (LocationException $error) {
      throw $error;
    } catch (\Throwable $error) {
      throw LocationException::providerFailure($error);
    }
    if ($data->ip !== $target) {
      throw LocationException::invalidProviderResponse();
    }
    if ($this->enricher === null) {
      return $data;
    }
    try {
      $enriched = $this->enricher->enrich($data);
    } catch (\Throwable $error) {
      throw LocationException::enrichmentFailure($error);
    }
    if ($enriched->ip !== $target) {
      throw LocationException::invalidProviderResponse();
    }
    return $enriched;
  }
}
