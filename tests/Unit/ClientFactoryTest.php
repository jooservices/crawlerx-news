<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Unit;

use JOOservices\CrawlerXNews\Http\ClientCrawlHttpClient;
use JOOservices\CrawlerXNews\Http\PrefetchedCrawlHttpClient;
use JOOservices\CrawlerXNews\Services\ClientFactory;
use JOOservices\CrawlerXNews\Tests\Support\FakeNewsCrawler;
use PHPUnit\Framework\TestCase;

final class ClientFactoryTest extends TestCase
{
    public function testBuildsPrefetchedClient(): void
    {
        $factory = new ClientFactory();
        $client = $factory->factory([
            'prefetched_html' => ['https://example.org/a' => '<html>hello</html>'],
        ]);

        self::assertInstanceOf(PrefetchedCrawlHttpClient::class, $client);
        $response = $client->get('https://example.org/a');
        self::assertSame(200, $response->toPsrResponse()->getStatusCode());
        self::assertStringContainsString('hello', $response->toPsrResponse()->getBody()->__toString());
    }

    public function testPrefetchedClientReturns404ForUnknownUrl(): void
    {
        $factory = new ClientFactory();
        $client = $factory->factory([
            'prefetched_html' => ['https://example.org/a' => '<html>hello</html>'],
        ]);

        $response = $client->get('https://example.org/missing');
        self::assertSame(404, $response->toPsrResponse()->getStatusCode());
    }

    public function testPrefetchedClientIgnoresNonStringEntries(): void
    {
        $factory = new ClientFactory();
        $client = $factory->factory([
            'prefetched_html' => [42 => 123, 'https://example.org/a' => '<html>x</html>'],
        ]);

        $response = $client->get('https://example.org/a');
        self::assertSame(200, $response->toPsrResponse()->getStatusCode());
    }

    public function testAdapterReceivesPrefetchedClientThroughOptions(): void
    {
        $crawler = new FakeNewsCrawler(new ClientFactory());
        $request = new \JOOservices\CrawlerXNews\Dto\CrawlRequestDto(
            url: 'https://example.org/x',
            type: \JOOservices\CrawlerXNews\Enums\PageType::Article,
            site: 'fake',
            options: ['prefetched_html' => ['https://example.org/x' => '<html>ok</html>']],
        );

        $crawler->prepareRequest($request);
        $reflection = new \ReflectionProperty($crawler, 'client');

        self::assertInstanceOf(PrefetchedCrawlHttpClient::class, $reflection->getValue($crawler));
    }

    public function testBuildsHttpClientWithOptions(): void
    {
        $factory = new ClientFactory();
        $client = $factory->factory([
            'base_uri' => 'https://example.org',
            'timeout' => 30,
            'headers' => ['User-Agent' => 'CrawlerXNews/1.0', 'X-Num' => 42],
        ]);

        self::assertInstanceOf(ClientCrawlHttpClient::class, $client);
    }

    public function testBuildsHttpClientWithoutOptions(): void
    {
        $factory = new ClientFactory();

        self::assertInstanceOf(ClientCrawlHttpClient::class, $factory->factory());
    }
}
