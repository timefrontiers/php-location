<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests\Fixtures;

use TimeFrontiers\Transport\HttpRequestOptions;
use TimeFrontiers\Transport\HttpResponse;
use TimeFrontiers\Transport\HttpTransportInterface;

final class FakeTransport implements HttpTransportInterface {
  public int $calls = 0;
  public string $url = '';
  /** @var list<string> */
  public array $headers = [];
  public ?HttpRequestOptions $options = null;

  public function __construct(
    public ?HttpResponse $response = null,
    public ?\Throwable $failure = null
  ) {}

  public function get(string $url, array $headers, HttpRequestOptions $options):HttpResponse {
    $this->calls++;
    $this->url = $url;
    $this->headers = $headers;
    $this->options = $options;
    if ($this->failure !== null) {
      throw $this->failure;
    }
    return $this->response ?? throw new \LogicException('The fake transport has no response.');
  }
}
