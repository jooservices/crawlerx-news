<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Feature;

use JOOservices\CrawlerXNews\CrawlerXNews;
use JOOservices\CrawlerXNews\CrawlerXNewsFactory;
use JOOservices\CrawlerXNews\Dto\ArticleOutcomeDto;
use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use JOOservices\CrawlerXNews\Enums\ArticleErrorCode;
use JOOservices\CrawlerXNews\Enums\PageType;
use JOOservices\CrawlerXNews\Tests\Support\FixtureResponder;
use PHPUnit\Framework\TestCase;

final class FullFlowTest extends TestCase
{
    protected function setUp(): void
    {
        CrawlerXNewsFactory::reset();
    }

    public function testTryCrawlReturnsParseFailedForMissingPrefetch(): void
    {
        $options = new CrawlOptionsDto(
            prefetchedHtml: ['https://www.bbc.com/news/articles/c1' => '<html>ok</html>'],
        );

        $outcome = CrawlerXNews::url('https://www.bbc.com/news/articles/c999')
            ->options($options)
            ->tryCrawl();

        self::assertInstanceOf(ArticleOutcomeDto::class, $outcome);
        self::assertTrue($outcome->failed());
        self::assertSame(ArticleErrorCode::ParseFailed, $outcome->error?->code);
    }

    public function testForceListingOnArticleUrl(): void
    {
        $options = new CrawlOptionsDto(
            prefetchedHtml: [
                'https://www.bbc.com/news/articles/c12345678' => FixtureResponder::for('bbc/detail-1.html'),
            ],
        );

        $result = CrawlerXNews::url('https://www.bbc.com/news/articles/c12345678')
            ->type(PageType::Listing)
            ->options($options)
            ->crawl();

        self::assertInstanceOf(\JOOservices\CrawlerXNews\Dto\ArticleListResultDto::class, $result);
    }

    public function testArticleResultSerializesToArray(): void
    {
        $options = new CrawlOptionsDto(
            prefetchedHtml: [
                'https://genk.vn/dien-thoai-ai-gia-re-sap-ra-mat-tai-viet-nam-a1234567.html' => FixtureResponder::for('genk/detail-1.html'),
            ],
        );

        $result = CrawlerXNews::url('https://genk.vn/dien-thoai-ai-gia-re-sap-ra-mat-tai-viet-nam-a1234567.html')
            ->options($options)
            ->crawl();

        $data = $result->toArray();

        self::assertArrayHasKey('content_html', $data);
        self::assertArrayHasKey('published_at', $data);
        self::assertSame('Điện thoại AI giá rẻ sắp ra mắt tại Việt Nam', $data['title']);
    }

    public function testListingResultSerializesToArray(): void
    {
        $options = new CrawlOptionsDto(
            prefetchedHtml: [
                'https://genk.vn/' => FixtureResponder::for('genk/listing-1.html'),
            ],
        );

        $result = CrawlerXNews::url('https://genk.vn/')
            ->options($options)
            ->crawl();

        $data = $result->toArray();

        self::assertArrayHasKey('url', $data);
        self::assertArrayHasKey('items', $data);
        self::assertCount(3, $data['items']);
    }

    public function testUnknownSiteWithEmptyUrlFails(): void
    {
        $outcome = CrawlerXNews::url('')->tryCrawl();

        self::assertTrue($outcome->failed());
        self::assertSame(ArticleErrorCode::UnsupportedUrl, $outcome->error?->code);
    }
}
