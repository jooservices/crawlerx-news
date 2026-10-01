<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Feature;

use JOOservices\CrawlerXNews\CrawlerXNews;
use JOOservices\CrawlerXNews\CrawlerXNewsFactory;
use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use JOOservices\CrawlerXNews\Enums\PageType;
use JOOservices\CrawlerXNews\Exceptions\AdapterNotFoundException;
use JOOservices\CrawlerXNews\Tests\Support\FixtureResponder;
use PHPUnit\Framework\TestCase;

final class OrchestratorTest extends TestCase
{
    protected function setUp(): void
    {
        CrawlerXNewsFactory::reset();
    }

    public function testSiteFacadeRequiresUrl(): void
    {
        $this->expectException(\JOOservices\CrawlerXNews\Exceptions\UnsupportedUrlException::class);

        CrawlerXNews::site('bbc')->crawl();
    }

    public function testTypeOverrideForcesArticleParsing(): void
    {
        $options = new CrawlOptionsDto(
            prefetchedHtml: [
                'https://genk.vn/dien-thoai-ai-gia-re-sap-ra-mat-tai-viet-nam-a1234567.html' => FixtureResponder::for('genk/detail-1.html'),
            ],
        );

        $result = CrawlerXNews::url('https://genk.vn/dien-thoai-ai-gia-re-sap-ra-mat-tai-viet-nam-a1234567.html')
            ->type(PageType::Article)
            ->options($options)
            ->crawl();

        self::assertSame('Điện thoại AI giá rẻ sắp ra mắt tại Việt Nam', $result->title);
    }

    public function testTryCrawlReturnsParseFailedForEmptyBody(): void
    {
        $options = new CrawlOptionsDto(
            prefetchedHtml: [
                'https://www.bbc.com/news/articles/c999' => '',
            ],
        );

        $outcome = CrawlerXNews::url('https://www.bbc.com/news/articles/c999')
            ->options($options)
            ->tryCrawl();

        self::assertTrue($outcome->failed());
        self::assertSame(\JOOservices\CrawlerXNews\Enums\ArticleErrorCode::ParseFailed, $outcome->error?->code);
    }

    public function testExplicitSiteSkipsHostDetection(): void
    {
        $options = new CrawlOptionsDto(
            prefetchedHtml: [
                'https://www.bbc.com/news/articles/c12345678' => FixtureResponder::for('bbc/detail-1.html'),
            ],
        );

        $result = CrawlerXNews::url('https://www.bbc.com/news/articles/c12345678')
            ->site('bbc')
            ->options($options)
            ->crawl();

        self::assertSame('UK tech firm invents world\'s first recyclable smartphone', $result->title);
    }

    public function testUnknownExplicitSiteThrowsAdapterNotFound(): void
    {
        $this->expectException(AdapterNotFoundException::class);

        CrawlerXNews::url('https://unsupported.example/x')
            ->site('nope')
            ->crawl();
    }
}
