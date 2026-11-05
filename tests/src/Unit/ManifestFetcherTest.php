<?php

namespace Drupal\Tests\iiif_random_block\Unit;

use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Site\Settings;
use Drupal\iiif_random_block\Service\ManifestFetcher;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests SSRF protection without making DNS or HTTP requests.
 */
class ManifestFetcherTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    new Settings([]);
  }

  private function fetcher(array $responses, array &$history, array $dns = []): ManifestFetcher {
    $mock = new MockHandler($responses);
    $factory = $this->createMock(ClientFactory::class);
    $factory->method('fromOptions')->willReturnCallback(function (array $options) use ($mock, &$history) {
      $this->assertFalse($options['allow_redirects']);
      $this->assertSame('', $options['proxy']);
      $this->assertTrue($options['verify']);
      $stack = HandlerStack::create($mock);
      $stack->push(Middleware::history($history));
      $options['handler'] = $stack;
      return new Client($options);
    });
    return new class($factory, $dns) extends ManifestFetcher {
      public function __construct(ClientFactory $factory, private array $dns) {
        parent::__construct($factory);
      }

      protected function resolveAddresses(string $host): array {
        return $this->dns[$host] ?? (filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ['93.184.216.34']);
      }
    };
  }

  public function testPublicFetchPinsValidatedDnsAddress(): void {
    $history = [];
    $fetcher = $this->fetcher([new Response(200, [], '{"label":"Public"}')], $history);
    $this->assertSame(['label' => 'Public'], $fetcher->fetch('https://example.org/manifest.json'));
    $this->assertCount(1, $history);
    $this->assertSame(['example.org:443:93.184.216.34'], $history[0]['options']['curl'][CURLOPT_RESOLVE]);
  }

  public static function unsafeUrls(): array {
    return array_map(static fn($url) => [$url], [
      'http://127.0.0.1/private', 'http://10.0.0.1/private',
      'http://169.254.169.254/latest/meta-data/', 'http://100.64.0.1/private',
      'http://192.168.1.1/private', 'http://172.16.0.1/private',
      'http://[::1]/private', 'http://[fc00::1]/private',
      'http://[::ffff:127.0.0.1]/private', 'http://[2002:7f00:1::]/private',
      'file:///etc/passwd', 'ftp://example.org/file',
      'https://user:password@example.org/manifest',
    ]);
  }

  #[DataProvider('unsafeUrls')]
  public function testUnsafeTargetsAreRejectedBeforeConnecting(string $url): void {
    $history = [];
    try {
      $this->fetcher([], $history)->fetch($url);
      $this->fail('Unsafe target was accepted.');
    }
    catch (\RuntimeException | \InvalidArgumentException $e) {
      $this->assertCount(0, $history);
    }
  }

  public function testPublicHostRedirectingToLoopbackIsBlocked(): void {
    $history = [];
    $fetcher = $this->fetcher([new Response(302, ['Location' => 'http://127.0.0.1/internal'])], $history);
    try {
      $fetcher->fetch('https://example.org/manifest');
      $this->fail('Private redirect was accepted.');
    }
    catch (\RuntimeException $e) {
      $this->assertCount(1, $history);
    }
  }

  public function testPublicRedirectIsRevalidatedAndPinned(): void {
    $history = [];
    $fetcher = $this->fetcher([
      new Response(302, ['Location' => 'https://other.example.org/manifest']),
      new Response(200, [], '{}'),
    ], $history, ['other.example.org' => ['1.1.1.1']]);
    $this->assertSame([], $fetcher->fetch('https://example.org/manifest'));
    $this->assertCount(2, $history);
    $this->assertSame(['other.example.org:443:1.1.1.1'], $history[1]['options']['curl'][CURLOPT_RESOLVE]);
  }

  public function testMixedPublicAndPrivateDnsAnswersFailClosed(): void {
    $history = [];
    $fetcher = $this->fetcher([], $history, ['example.org' => ['93.184.216.34', '127.0.0.1']]);
    $this->expectException(\RuntimeException::class);
    $fetcher->fetch('https://example.org/manifest');
  }

  public function testPrivateHostNeedsExplicitExactAllowlist(): void {
    new Settings(['iiif_random_block_allowed_private_hosts' => ['iiif.internal']]);
    $history = [];
    $fetcher = $this->fetcher([new Response(200, [], '{}')], $history, ['iiif.internal' => ['10.0.0.1']]);
    $this->assertSame([], $fetcher->fetch('http://iiif.internal/manifest'));
    $this->assertSame(['iiif.internal:80:10.0.0.1'], $history[0]['options']['curl'][CURLOPT_RESOLVE]);
    $this->expectException(\RuntimeException::class);
    $fetcher->fetch('http://10.0.0.1/manifest');
  }

}
