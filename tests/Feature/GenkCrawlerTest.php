<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Feature;

use JOOservices\CrawlerXNews\CrawlerXNews;
use JOOservices\CrawlerXNews\CrawlerXNewsFactory;
use JOOservices\CrawlerXNews\Dto\ArticleDetailResultDto;
use JOOservices\CrawlerXNews\Dto\ArticleListResultDto;
use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use PHPUnit\Framework\TestCase;
use JOOservices\CrawlerXNews\Tests\Support\FixtureResponder;

final class GenkCrawlerTest extends TestCase
{
    protected function setUp(): void
    {
        CrawlerXNewsFactory::reset();
    }

    private function options(): CrawlOptionsDto
    {
        return new CrawlOptionsDto(
            prefetchedHtml: [
                'https://genk.vn/dien-thoai-ai-gia-re-sap-ra-mat-tai-viet-nam-a1234567.html' => FixtureResponder::for('genk/detail-1.html'),
                'https://genk.vn/' => FixtureResponder::for('genk/listing-1.html'),
            ],
        );
    }

    public function testCrawlsArticleDetail(): void
    {
        $result = CrawlerXNews::url('https://genk.vn/dien-thoai-ai-gia-re-sap-ra-mat-tai-viet-nam-a1234567.html')
            ->options($this->options())
            ->crawl();

        self::assertInstanceOf(ArticleDetailResultDto::class, $result);
        self::assertSame('Điện thoại AI giá rẻ sắp ra mắt tại Việt Nam', $result->title);
        self::assertSame('Hãng điện thoại Trung Quốc xác nhận mẫu AI giá rẻ sẽ về Việt Nam trong tháng sau.', $result->intro);
        self::assertSame('2026-09-14', $result->publishedAt?->format('Y-m-d'));
        self::assertSame('vi', $result->language);
        self::assertSame(['Minh Công'], $result->authors);
        self::assertSame(['Mobile'], $result->categories);
        self::assertSame('https://genk.vn/dien-thoai-ai-gia-re-sap-ra-mat-tai-viet-nam-a1234567.html', $result->canonicalUrl);
        self::assertStringContainsString('trợ lý AI', (string) $result->contentHtml);
        self::assertSame('genk', $result->source);
    }

    public function testCrawlsListing(): void
    {
        $result = CrawlerXNews::url('https://genk.vn/')
            ->options($this->options())
            ->crawl();

        self::assertInstanceOf(ArticleListResultDto::class, $result);
        self::assertCount(3, $result->items);
        self::assertSame('https://genk.vn/laptop-mong-nhe-thoi-thuong-a1234580.html', $result->items[2]->url);
    }
}
