<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Unit;

use JOOservices\CrawlerXNews\Adapters\Bbc\BbcCrawler;
use JOOservices\CrawlerXNews\Adapters\Genk\GenkCrawler;
use JOOservices\CrawlerXNews\Services\ClientFactory;
use JOOservices\CrawlerXNews\Services\JsonLdParser;
use JOOservices\CrawlerXNews\Tests\Support\FixtureResponder;
use PHPUnit\Framework\TestCase;

final class RemainingBranchesTest extends TestCase
{
    public function testBbcParsesRelativeListingLinks(): void
    {
        $crawler = new BbcCrawler(new ClientFactory());
        $request = new \JOOservices\CrawlerXNews\Dto\CrawlRequestDto(
            url: 'https://www.bbc.com/news',
            type: \JOOservices\CrawlerXNews\Enums\PageType::Listing,
            site: 'bbc',
            options: ['prefetched_html' => ['https://www.bbc.com/news' => FixtureResponder::for('bbc/listing-1.html')]],
        );
        $crawler->prepareRequest($request);

        $result = $crawler->listing($request);

        self::assertSame('https://www.bbc.com/news/articles/c12345678', $result->items[0]->url);
    }

    public function testGenkParsesAbsoluteListingLinks(): void
    {
        $crawler = new GenkCrawler(new ClientFactory());
        $request = new \JOOservices\CrawlerXNews\Dto\CrawlRequestDto(
            url: 'https://genk.vn/',
            type: \JOOservices\CrawlerXNews\Enums\PageType::Listing,
            site: 'genk',
            options: ['prefetched_html' => ['https://genk.vn/' => FixtureResponder::for('genk/listing-1.html')]],
        );
        $crawler->prepareRequest($request);

        $result = $crawler->listing($request);

        self::assertSame('https://genk.vn/ong-lon-cong-nghe-tuyen-bo-mo-hinh-ai-moi-a1234570.html', $result->items[1]->url);
    }

    public function testJsonLdSkipsEmptyScriptBlocks(): void
    {
        $parser = new JsonLdParser();
        $html = '<html><head><script type="application/ld+json">   </script></head><body></body></html>';

        self::assertNull($parser->parse($html));
    }

    public function testJsonLdSkipsMalformedJson(): void
    {
        $parser = new JsonLdParser();
        $html = '<html><head><script type="application/ld+json">{broken</script></head><body></body></html>';

        self::assertNull($parser->parse($html));
    }
}
