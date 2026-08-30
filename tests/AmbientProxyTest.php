<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\TestCase;
use TimeFrontiers\Transport\CurlHttpTransport;
use TimeFrontiers\Transport\HttpRequestOptions;

final class AmbientProxyTest extends TestCase {

  public function testAmbientHttpsAndAllProxyCannotInterceptThePinnedDirectTransport():void {
    if (!\extension_loaded('curl') || !\function_exists('proc_open')) {
      self::markTestSkipped('The cURL and process capabilities are required.');
    }

    $directory = \sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tf-location-proxy-' . \bin2hex(\random_bytes(8));
    self::assertTrue(\mkdir($directory));
    $log = $directory . DIRECTORY_SEPARATOR . 'trap.log';
    self::assertNotFalse(\file_put_contents($log, ''));
    $port = self::unusedLoopbackPort();
    $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $directory];
    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['file', $log, 'a'],
      2 => ['file', $log, 'a'],
    ];
    $pipes = [];
    $process = \proc_open($command, $descriptors, $pipes, $directory);
    self::assertIsResource($process);

    $names = ['HTTPS_PROXY', 'ALL_PROXY', 'https_proxy', 'all_proxy', 'NO_PROXY', 'no_proxy'];
    $previous = [];
    foreach ($names as $name) {
      $value = \getenv($name);
      $previous[$name] = $value === false ? null : $value;
    }

    try {
      self::waitForServer($port);
      \usleep(100_000);
      self::truncate($log);

      $proxy = 'http://127.0.0.1:' . $port;
      foreach (['HTTPS_PROXY', 'ALL_PROXY', 'https_proxy', 'all_proxy'] as $name) {
        \putenv($name . '=' . $proxy);
      }
      foreach (['NO_PROXY', 'no_proxy'] as $name) {
        \putenv($name . '=');
      }

      self::rawCurlProbe();
      \usleep(100_000);
      \clearstatcache(true, $log);
      self::assertGreaterThan(0, (int)\filesize($log), 'The ambient proxy trap was not active.');

      self::truncate($log);
      try {
        (new CurlHttpTransport(['192.0.0.9']))->get(
          'https://192.0.0.9/',
          [],
          new HttpRequestOptions(100, 250, 1024)
        );
      } catch (\Throwable) {
        // Direct timeout/TLS/network failure is expected; only proxy contact matters.
      }
      \usleep(100_000);
      \clearstatcache(true, $log);
      self::assertSame(0, (int)\filesize($log), 'The hardened transport contacted an ambient proxy.');
    } finally {
      foreach ($previous as $name => $value) {
        \putenv($value === null ? $name : $name . '=' . $value);
      }
      foreach ($pipes as $pipe) {
        if (\is_resource($pipe)) {
          \fclose($pipe);
        }
      }
      \proc_terminate($process);
      \proc_close($process);
      @\unlink($log);
      @\rmdir($directory);
    }
  }

  private static function rawCurlProbe():void {
    $handle = \curl_init();
    self::assertNotFalse($handle);
    self::assertTrue(\curl_setopt_array($handle, [
      CURLOPT_URL => 'https://192.0.0.9/',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT_MS => 100,
      CURLOPT_TIMEOUT_MS => 250,
    ]));
    \curl_exec($handle);
  }

  private static function unusedLoopbackPort():int {
    $errno = 0;
    $error = '';
    $socket = \stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($socket === false) {
      self::fail('Unable to reserve a proxy-trap port.');
    }
    $name = \stream_socket_get_name($socket, false);
    \fclose($socket);
    if (!\is_string($name) || !\preg_match('/:(\d+)\z/D', $name, $matches)) {
      self::fail('Unable to determine the proxy-trap port.');
    }
    return (int)$matches[1];
  }

  private static function waitForServer(int $port):void {
    for ($attempt = 0; $attempt < 50; $attempt++) {
      $errno = 0;
      $error = '';
      $socket = @\fsockopen('127.0.0.1', $port, $errno, $error, 0.05);
      if ($socket !== false) {
        \fclose($socket);
        return;
      }
      \usleep(20_000);
    }
    self::fail('The ambient proxy trap did not start.');
  }

  private static function truncate(string $path):void {
    self::assertNotFalse(\file_put_contents($path, ''));
    \clearstatcache(true, $path);
  }
}
