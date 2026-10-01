<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Unit;

use JOOservices\CrawlerXNews\Services\JsonLdParser;
use PHPUnit\Framework\TestCase;
use JOOservices\CrawlerXNews\Tests\Support\FixtureResponder;

final class JsonLdParserTest extends TestCase
{
    public function testParsesNewsArticleFields(): void
    {
        $parser = new JsonLdParser();
        $result = $parser->parse(FixtureResponder::for('bbc/detail-1.html'));

        self::assertNotNull($result);
        self::assertSame('UK tech firm invents world\'s first recyclable smartphone', $result['title']);
        self::assertSame('A British startup says it has built a phone that can be fully recycled.', $result['intro']);
        self::assertSame('2026-09-15T08:30:00Z', $result['published_at']);
        self::assertSame('2026-09-15T09:00:00Z', $result['updated_at']);
        self::assertSame(['Jane Reporter'], $result['authors']);
        self::assertSame('https://ichef.bbci.co.uk/images/ic/1024x576/p0smartphone.jpg', $result['image']);
        self::assertSame('en-GB', $result['language']);
        self::assertSame('https://www.bbc.com/news/articles/c12345678', $result['canonical_url']);
    }

    public function testReturnsNullWithoutJsonLd(): void
    {
        $parser = new JsonLdParser();

        self::assertNull($parser->parse('<html><body>no schema here</body></html>'));
    }

    public function testParsesGraphBlock(): void
    {
        $parser = new JsonLdParser();
        $html = '<html><head><script type="application/ld+json">'
            . json_encode([
                '@context' => 'https://schema.org',
                '@graph' => [
                    ['@type' => 'WebSite', 'name' => 'Example'],
                    ['@type' => 'NewsArticle', 'headline' => 'Graph article', 'datePublished' => '2026-01-01T00:00:00Z'],
                ],
            ], JSON_THROW_ON_ERROR)
            . '</script></head><body></body></html>';

        $result = $parser->parse($html);

        self::assertNotNull($result);
        self::assertSame('Graph article', $result['title']);
    }
}
