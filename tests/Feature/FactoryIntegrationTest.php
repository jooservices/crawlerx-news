<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Feature;

use JOOservices\CrawlerXNews\CrawlerXNewsFactory;
use JOOservices\CrawlerXNews\Registry\AdapterRegistry;
use JOOservices\CrawlerXNews\Services\ClientFactory;
use JOOservices\CrawlerXNews\Services\CrawlOrchestrator;
use JOOservices\CrawlerXNews\Services\CrawlerXNewsService;
use JOOservices\CrawlerXNews\Services\JsonLdParser;
use JOOservices\CrawlerXNews\Services\UrlClassifier;
use JOOservices\CrawlerXNews\Tests\Support\FakeNewsCrawler;
use JOOservices\CrawlerXNews\Tests\Support\FixtureResponder;
use PHPUnit\Framework\TestCase;

final class FactoryIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        CrawlerXNewsFactory::reset();
    }

    public function testFactoryRegistersAllManifestAdapters(): void
    {
        $orchestrator = CrawlerXNewsFactory::create();

        self::assertInstanceOf(CrawlOrchestrator::class, $orchestrator);
    }

    public function testFactoryAdapterDirOverride(): void
    {
        CrawlerXNewsFactory::setAdapterDir(__DIR__ . '/../Support/Adapters');
        $orchestrator = CrawlerXNewsFactory::create();

        self::assertInstanceOf(CrawlOrchestrator::class, $orchestrator);

        CrawlerXNewsFactory::reset();
        CrawlerXNewsFactory::setAdapterDir(__DIR__ . '/../../src/Adapters');
        self::assertInstanceOf(CrawlOrchestrator::class, CrawlerXNewsFactory::create());
    }

    public function testServiceDispatchesDetailThroughOrchestrator(): void
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
        $service = new CrawlerXNewsService($registry);
        $orchestrator = new CrawlOrchestrator(new UrlClassifier($manifests), $service);

        $html = '<html><body><h1 class="article-title">Fake title</h1>'
            . '<div class="article-content"><p>body</p></div></body></html>';
        $result = $orchestrator->url('https://example.org/story/1')
            ->options(new \JOOservices\CrawlerXNews\Dto\CrawlOptionsDto(
                prefetchedHtml: ['https://example.org/story/1' => $html],
            ))
            ->crawl();

        self::assertSame('Fake title', $result->title);
        self::assertSame('fake', $result->source);
    }

    public function testJsonLdParserOnGraphWithMultipleTypes(): void
    {
        $parser = new JsonLdParser();
        $html = '<html><head><script type="application/ld+json">'
            . json_encode([
                '@context' => 'https://schema.org',
                '@graph' => [
                    ['@type' => ['WebPage', 'NewsArticle'], 'headline' => 'Multi type'],
                    ['@type' => 'ImageObject', 'url' => 'https://example.org/img.png'],
                ],
            ], JSON_THROW_ON_ERROR)
            . '</script></head><body></body></html>';

        $result = $parser->parse($html);

        self::assertNotNull($result);
        self::assertSame('Multi type', $result['title']);
    }

    public function testBbcFixtureHasFullMetadata(): void
    {
        $parser = new JsonLdParser();
        $meta = (new \JOOservices\CrawlerXNews\Services\MetaParser())->parse(FixtureResponder::for('bbc/detail-1.html'));
        $jsonLd = $parser->parse(FixtureResponder::for('bbc/detail-1.html'));

        self::assertNotNull($jsonLd);
        self::assertSame('Jane Reporter', $jsonLd['authors'][0]);
        self::assertSame('Technology', $meta['categories'][0]);
    }
}
