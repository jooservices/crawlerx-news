<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Unit;

use JOOservices\CrawlerXNews\Services\MetaParser;
use PHPUnit\Framework\TestCase;
use JOOservices\CrawlerXNews\Tests\Support\FixtureResponder;

final class MetaParserTest extends TestCase
{
    public function testParsesOgAndArticleMeta(): void
    {
        $parser = new MetaParser();
        $result = $parser->parse(FixtureResponder::for('bbc/detail-1.html'));

        self::assertSame('UK tech firm invents world\'s first recyclable smartphone', $result['title']);
        self::assertSame('A British startup says it has built a phone that can be fully recycled.', $result['intro']);
        self::assertSame('https://ichef.bbci.co.uk/images/ic/1024x576/p0smartphone.jpg', $result['image']);
        self::assertSame('2026-09-15T08:30:00Z', $result['published_at']);
        self::assertSame('2026-09-15T09:00:00Z', $result['updated_at']);
        self::assertSame('https://www.bbc.com/news/articles/c12345678', $result['canonical_url']);
        self::assertSame('en-GB', $result['language']);
        self::assertSame(['Technology'], $result['categories']);
        self::assertSame(['Innovation'], $result['tags']);
    }
}
