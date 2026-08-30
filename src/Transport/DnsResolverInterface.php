<?php

declare(strict_types=1);

namespace TimeFrontiers\Transport;

interface DnsResolverInterface {
  /** @return list<string> */
  public function resolve(string $host):array;
}
