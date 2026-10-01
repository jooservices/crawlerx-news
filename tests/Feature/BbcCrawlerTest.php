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

final class BbcCrawlerTest extends TestCase
{
    protected function setUp(): void
    {
        CrawlerXNewsFactory::reset();
    }

    private function options(): CrawlOptionsDto
    {
        return new CrawlOptionsDto(
            prefetchedHtml: [
                'https://www.bbc.com/news/articles/c12345678' => FixtureResponder::for('bbc/detail-1.html'),
                'https://www.bbc.com/news' => FixtureResponder::for('bbc/listing-1.html'),
            ],
        );
    }

    public function testCrawlsArticleDetail(): void
    {
        $result = CrawlerXNews::url('https://www.bbc.com/news/articles/c12345678')
            ->options($this->options())
            ->crawl();

        self::assertInstanceOf(ArticleDetailResultDto::class, $result);
        self::assertSame('UK tech firm invents world\'s first recyclable smartphone', $result->title);
        self::assertSame('A British startup says it has built a phone that can be fully recycled.', $result->intro);
        self::assertSame('2026-09-15', $result->publishedAt?->format('Y-m-d'));
        self::assertSame('2026-09-15', $result->updatedAt?->format('Y-m-d'));
        self::assertSame('en-GB', $result->language);
        self::assertSame(['Jane Reporter'], $result->authors);
        self::assertSame(['Technology'], $result->categories);
        self::assertSame(['Innovation'], $result->tags);
        self::assertSame('https://www.bbc.com/news/articles/c12345678', $result->canonicalUrl);
        self::assertStringContainsString('recyclable parts', (string) $result->contentHtml);
        self::assertSame('bbc', $result->source);
    }

    public function testCrawlsListing(): void
    {
        $result = CrawlerXNews::url('https://www.bbc.com/news')
            ->options($this->options())
            ->crawl();

        self::assertInstanceOf(ArticleListResultDto::class, $result);
        self::assertCount(3, $result->items);
        self::assertSame('https://www.bbc.com/news/articles/c23456789', $result->items[1]->url);
    }
}
