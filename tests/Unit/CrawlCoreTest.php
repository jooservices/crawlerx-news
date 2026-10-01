<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Unit;

use JOOservices\CrawlerXNews\CrawlerXNews;
use JOOservices\CrawlerXNews\CrawlerXNewsFactory;
use JOOservices\CrawlerXNews\Dto\ArticleDetailResultDto;
use JOOservices\CrawlerXNews\Dto\ArticleListResultDto;
use JOOservices\CrawlerXNews\Dto\ArticleOutcomeDto;
use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use JOOservices\CrawlerXNews\Enums\ArticleErrorCode;
use JOOservices\CrawlerXNews\Enums\PageType;
use JOOservices\CrawlerXNews\Exceptions\AdapterNotFoundException;
use JOOservices\CrawlerXNews\Registry\AdapterRegistry;
use JOOservices\CrawlerXNews\Services\ClientFactory;
use JOOservices\CrawlerXNews\Services\CrawlOrchestrator;
use JOOservices\CrawlerXNews\Services\CrawlerXNewsService;
use JOOservices\CrawlerXNews\Services\UrlClassifier;
use JOOservices\CrawlerXNews\Tests\Support\FakeNewsCrawler;
use JOOservices\CrawlerXNews\Tests\Support\FixtureResponder;
use PHPUnit\Framework\TestCase;

final class CrawlCoreTest extends TestCase
{
    protected function setUp(): void
    {
        CrawlerXNewsFactory::reset();
    }

    public function testFactoryCreatesSharedOrchestrator(): void
    {
        $first = CrawlerXNewsFactory::create();
        $second = CrawlerXNewsFactory::create();

        self::assertSame($first, $second);

        CrawlerXNewsFactory::reset();
        self::assertNotSame($first, CrawlerXNewsFactory::create());
    }

    public function testOrchestratorCrawlsArticleThroughRegistry(): void
    {
        $options = new CrawlOptionsDto(
            prefetchedHtml: [
                'https://www.bbc.com/news/articles/c12345678' => FixtureResponder::for('bbc/detail-1.html'),
            ],
        );

        $result = CrawlerXNews::url('https://www.bbc.com/news/articles/c12345678')
            ->options($options)
            ->crawl();

        self::assertInstanceOf(ArticleDetailResultDto::class, $result);
    }

    public function testOrchestratorCrawlsListingForGenk(): void
    {
        $options = new CrawlOptionsDto(
            prefetchedHtml: [
                'https://genk.vn/' => FixtureResponder::for('genk/listing-1.html'),
            ],
        );

        $result = CrawlerXNews::url('https://genk.vn/')
            ->options($options)
            ->crawl();

        self::assertInstanceOf(ArticleListResultDto::class, $result);
        self::assertCount(3, $result->items);
    }

    public function testServiceThrowsForUnknownSite(): void
    {
        $clientFactory = new ClientFactory();
        $registry = new AdapterRegistry($clientFactory);
        $service = new CrawlerXNewsService($registry);

        $this->expectException(AdapterNotFoundException::class);

        $service->detail(new \JOOservices\CrawlerXNews\Dto\CrawlRequestDto(
            url: 'https://x.example/',
            type: PageType::Article,
            site: 'missing',
        ));
    }

    public function testBuilderSiteRequiresUrl(): void
    {
        $this->expectException(\JOOservices\CrawlerXNews\Exceptions\UnsupportedUrlException::class);

        CrawlerXNews::site('bbc')->crawl();
    }

    public function testBuilderTryCrawlMapsUnknownError(): void
    {
        $outcome = CrawlerXNews::url('https://unsupported.example/story')->tryCrawl();

        self::assertInstanceOf(ArticleOutcomeDto::class, $outcome);
        self::assertTrue($outcome->failed());
        self::assertSame(ArticleErrorCode::UnsupportedUrl, $outcome->error?->code);
        self::assertSame('https://unsupported.example/story', $outcome->error?->url);
    }

    public function testOrchestratorCrawlWithExplicitTypeListing(): void
    {
        $manifests = [
            [
                'slug' => 'fake',
                'hosts' => ['example.org'],
                'page_rules' => ['article' => ['/story/'], 'listing' => ['/']],
            ],
        ];
        $clientFactory = new ClientFactory();
        $registry = new AdapterRegistry($clientFactory);
        $registry->register(new FakeNewsCrawler($clientFactory));
        $orchestrator = new CrawlOrchestrator(
            new UrlClassifier($manifests),
            new CrawlerXNewsService($registry),
        );

        $html = '<html><body><a class="item" href="https://example.org/story/1">One</a></body></html>';
        $result = $orchestrator->url('https://example.org/')
            ->type(PageType::Listing)
            ->options(new CrawlOptionsDto(
                prefetchedHtml: ['https://example.org/' => $html],
            ))
            ->crawl();

        self::assertInstanceOf(ArticleListResultDto::class, $result);
        self::assertSame('https://example.org/story/1', $result->items[0]->url);
    }

    public function testDetailResultCarriesRichFields(): void
    {
        $options = new CrawlOptionsDto(
            prefetchedHtml: [
                'https://www.bbc.com/news/articles/c12345678' => FixtureResponder::for('bbc/detail-1.html'),
            ],
        );

        $result = CrawlerXNews::url('https://www.bbc.com/news/articles/c12345678')
            ->options($options)
            ->crawl();

        self::assertInstanceOf(ArticleDetailResultDto::class, $result);
        self::assertNotNull($result->retrievedAt);
        self::assertNotNull($result->contentHtml);
        self::assertSame('bbc', $result->source);
    }

    public function testOutcomeSuccessForDetail(): void
    {
        $options = new CrawlOptionsDto(
            prefetchedHtml: [
                'https://www.bbc.com/news/articles/c12345678' => FixtureResponder::for('bbc/detail-1.html'),
            ],
        );

        $outcome = CrawlerXNews::url('https://www.bbc.com/news/articles/c12345678')
            ->options($options)
            ->tryCrawl();

        self::assertFalse($outcome->failed());
        self::assertNotNull($outcome->article);
        self::assertNull($outcome->list);
        self::assertNull($outcome->error);
    }

    public function testTryCrawlMapsAdapterNotFound(): void
    {
        $outcome = CrawlerXNews::url('https://unsupported.example/story')
            ->site('nope')
            ->tryCrawl();

        self::assertTrue($outcome->failed());
        self::assertSame(ArticleErrorCode::AdapterNotFound, $outcome->error?->code);
    }

    public function testOutcomeFailureCarriesNullUrl(): void
    {
        $outcome = CrawlerXNews::site('nope')->tryCrawl();

        self::assertTrue($outcome->failed());
        self::assertSame(ArticleErrorCode::UnsupportedUrl, $outcome->error?->code);
        self::assertNull($outcome->error?->url);
    }
}
