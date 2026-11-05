<?php

namespace Drupal\iiif_random_block\Service;

use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Site\Settings;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

/**
 * Fetches remote manifests without granting access to arbitrary internal hosts.
 */
class ManifestFetcher {

  private const MAX_BYTES = 8388608;

  public function __construct(protected ClientFactory $clientFactory) {}

  /**
   * Follows a bounded number of redirects, validating and pinning every hop.
   */
  public function fetch(string $url): array {
    if (!extension_loaded('curl')) {
      throw new \RuntimeException('The cURL extension is required for secure manifest fetching.');
    }
    $uri = new Uri($url);
    for ($hop = 0; $hop <= 5; $hop++) {
      $scheme = strtolower($uri->getScheme());
      $host = strtolower(trim($uri->getHost(), '[]'));
      if (!in_array($scheme, ['http', 'https'], TRUE) || $host === '' || $uri->getUserInfo() !== '') {
        throw new \InvalidArgumentException('Manifest URLs must use HTTP(S), a host, and no embedded credentials.');
      }
      $addresses = $this->resolveAddresses($host);
      if (!$addresses) {
        throw new \RuntimeException('Could not resolve manifest host.');
      }
      $private_hosts = Settings::get('iiif_random_block_allowed_private_hosts', []);
      $allow_private = is_array($private_hosts) && in_array($host, array_map('strtolower', $private_hosts), TRUE);
      foreach ($addresses as $address) {
        if (!filter_var($address, FILTER_VALIDATE_IP) || (!$allow_private && !$this->isPublicAddress($address))) {
          throw new \RuntimeException('Manifest host resolves to a disallowed address.');
        }
      }

      // Pin cURL to a validated address to prevent a second DNS lookup from
      // reaching a different (private) address. Never use an outbound proxy.
      $port = $uri->getPort() ?? ($scheme === 'https' ? 443 : 80);
      $address = $addresses[0];
      $curl_options = [];
      if (!filter_var($host, FILTER_VALIDATE_IP)) {
        $pinned = str_contains($address, ':') ? '[' . $address . ']' : $address;
        $curl_options[CURLOPT_RESOLVE] = ["$host:$port:$pinned"];
      }
      $client = $this->clientFactory->fromOptions([
        'handler' => HandlerStack::create(new CurlHandler()),
        'allow_redirects' => FALSE,
        'http_errors' => FALSE,
        'proxy' => '',
        'verify' => TRUE,
        'timeout' => 20,
        'connect_timeout' => 5,
        'on_headers' => static function ($response): void {
          if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
            throw new \RuntimeException('Manifest is too large.');
          }
        },
        'progress' => static function ($total, $downloaded): void {
          if ($total > self::MAX_BYTES || $downloaded > self::MAX_BYTES) {
            throw new \RuntimeException('Manifest is too large.');
          }
        },
      ]);
      // Request options preserve cURL's integer option keys; Drupal's client
      // factory deep-merges defaults and can renumber nested integer keys.
      $response = $client->get((string) $uri, ['curl' => $curl_options]);
      $status = $response->getStatusCode();
      if (in_array($status, [301, 302, 303, 307, 308], TRUE)) {
        if ($hop === 5 || !$response->hasHeader('Location')) {
          throw new \RuntimeException('Invalid or excessive manifest redirects.');
        }
        $uri = UriResolver::resolve($uri, new Uri($response->getHeaderLine('Location')));
        continue;
      }
      if ($status !== 200) {
        throw new \RuntimeException('Manifest server returned HTTP ' . $status . '.');
      }
      $body = (string) $response->getBody();
      if (strlen($body) > self::MAX_BYTES) {
        throw new \RuntimeException('Manifest is too large.');
      }
      $manifest = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($manifest)) {
        throw new \RuntimeException('Manifest must be a JSON object.');
      }
      return $manifest;
    }
    throw new \RuntimeException('Too many manifest redirects.');
  }

  /**
   * Resolves once; the resulting address is pinned for the connection.
   */
  protected function resolveAddresses(string $host): array {
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      return [$host];
    }
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    $addresses = [];
    foreach ($records ?: [] as $record) {
      if (isset($record['ip'])) {
        $addresses[] = $record['ip'];
      }
      elseif (isset($record['ipv6'])) {
        $addresses[] = $record['ipv6'];
      }
    }
    if (!$addresses) {
      $addresses = gethostbynamel($host) ?: [];
    }
    return array_values(array_unique($addresses));
  }

  /**
   * Rejects non-global ranges, including mapped IPv4 and transition networks.
   */
  protected function isPublicAddress(string $address): bool {
    $packed = inet_pton($address);
    if ($packed === FALSE) {
      return FALSE;
    }
    if (strlen($packed) === 16) {
      if (substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
        return $this->isPublicAddress(inet_ntop(substr($packed, 12)));
      }
      if (!$this->inRange($address, '2000::', 3)) {
        return FALSE;
      }
      foreach ([['2001::', 23], ['2001:db8::', 32], ['2002::', 16]] as [$network, $bits]) {
        if ($this->inRange($address, $network, $bits)) {
          return FALSE;
        }
      }
      return TRUE;
    }
    foreach ([['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10], ['127.0.0.0', 8], ['169.254.0.0', 16], ['172.16.0.0', 12], ['192.0.0.0', 24], ['192.0.2.0', 24], ['192.168.0.0', 16], ['198.18.0.0', 15], ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4]] as [$network, $bits]) {
      if ($this->inRange($address, $network, $bits)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private function inRange(string $address, string $network, int $bits): bool {
    $address = inet_pton($address);
    $network = inet_pton($network);
    if (strlen($address) !== strlen($network)) {
      return FALSE;
    }
    $bytes = intdiv($bits, 8);
    $remainder = $bits % 8;
    return substr($address, 0, $bytes) === substr($network, 0, $bytes)
      && (!$remainder || ((ord($address[$bytes]) ^ ord($network[$bytes])) & (0xff << (8 - $remainder))) === 0);
  }

}
