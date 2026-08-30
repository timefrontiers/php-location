<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

/** Host boundary for an already-applied trusted-proxy policy. */
interface ClientIpResolverInterface {
  public function resolve():?string;
}
