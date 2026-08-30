<?php

declare(strict_types=1);

namespace TimeFrontiers\Transport;

interface HttpTransportInterface {
  /** @param list<string> $headers */
  public function get(string $url, array $headers, HttpRequestOptions $options):HttpResponse;
}
