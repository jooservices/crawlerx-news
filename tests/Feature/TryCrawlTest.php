<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Feature;

use JOOservices\CrawlerXNews\CrawlerXNews;
use JOOservices\CrawlerXNews\CrawlerXNewsFactory;
use JOOservices\CrawlerXNews\Dto\ArticleOutcomeDto;
use JOOservices\CrawlerXNews\Enums\ArticleErrorCode;
use JOOservices\CrawlerXNews\Tests\Support\FixtureResponder;
use PHPUnit\Framework\TestCase;

final class TryCrawlTest extends TestCase
{
    protected function setUp(): void
    {
        CrawlerXNewsFactory::reset();
    }

    public function testReturnsFailureForUnsupportedUrl(): void
    {
        $outcome = CrawlerXNews::url('https://unsupported.example/story')->tryCrawl();

        self::assertInstanceOf(ArticleOutcomeDto::class, $outcome);
        self::assertTrue($outcome->failed());
        self::assertSame(ArticleErrorCode::UnsupportedUrl, $outcome->error?->code);
    }

    public function testReturnsSuccessForSupportedArticle(): void
    {
        $options = new \JOOservices\CrawlerXNews\Dto\CrawlOptionsDto(
            prefetchedHtml: [
                'https://www.bbc.com/news/articles/c12345678' => FixtureResponder::for('bbc/detail-1.html'),
            ],
        );

        $outcome = CrawlerXNews::url('https://www.bbc.com/news/articles/c12345678')
            ->options($options)
            ->tryCrawl();

        self::assertFalse($outcome->failed());
        self::assertSame('UK tech firm invents world\'s first recyclable smartphone', $outcome->article?->title);
    }
}
