<?php

declare(strict_types=1);

namespace TimeFrontiers\GeoIP;

/** Narrow optional MaxMind reader boundary. */
interface MaxMindReaderInterface {
  public function city(string $ip):object;
}
