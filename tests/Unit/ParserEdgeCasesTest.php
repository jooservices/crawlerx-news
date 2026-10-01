<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Unit;

use JOOservices\CrawlerXNews\Services\JsonLdParser;
use JOOservices\CrawlerXNews\Services\MetaParser;
use PHPUnit\Framework\TestCase;

final class ParserEdgeCasesTest extends TestCase
{
    public function testJsonLdParsesArticleBodyAndMultipleAuthors(): void
    {
        $parser = new JsonLdParser();
        $html = '<html><head><script type="application/ld+json">'
            . json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => 'Edge article',
                'articleBody' => 'Full body text with <b>markup</b>.',
                'author' => [
                    ['@type' => 'Person', 'name' => 'One'],
                    ['@type' => 'Person', 'name' => 'Two'],
                ],
                'image' => ['https://example.org/pic.jpg'],
            ], JSON_THROW_ON_ERROR)
            . '</script></head><body></body></html>';

        $result = $parser->parse($html);

        self::assertNotNull($result);
        self::assertSame('Full body text with <b>markup</b>.', $result['content']);
        self::assertSame(['One', 'Two'], $result['authors']);
        self::assertSame('https://example.org/pic.jpg', $result['image']);
    }

    public function testJsonLdParsesUrlAsCanonicalFallback(): void
    {
        $parser = new JsonLdParser();
        $html = '<html><head><script type="application/ld+json">'
            . json_encode([
                '@type' => 'NewsArticle',
                'headline' => 'URL article',
                'url' => 'https://example.org/u/1',
            ], JSON_THROW_ON_ERROR)
            . '</script></head><body></body></html>';

        $result = $parser->parse($html);

        self::assertNotNull($result);
        self::assertSame('https://example.org/u/1', $result['canonical_url']);
    }

    public function testJsonLdSkipsNonArticleBlocks(): void
    {
        $parser = new JsonLdParser();
        $html = '<html><head><script type="application/ld+json">'
            . json_encode([
                '@context' => 'https://schema.org',
                '@graph' => [
                    ['@type' => 'WebSite', 'name' => 'No article here'],
                    ['@type' => 'Organization', 'name' => 'Also not article'],
                ],
            ], JSON_THROW_ON_ERROR)
            . '</script></head><body></body></html>';

        self::assertNull($parser->parse($html));
    }

    public function testMetaParsesAuthorViaNameAttribute(): void
    {
        $parser = new MetaParser();
        $html = '<html lang="en"><head>'
            . '<meta name="author" content="Reporter One">'
            . '<meta name="author" content="Reporter Two">'
            . '<meta name="keywords" content="k1, k2">'
            . '</head><body></body></html>';

        $result = $parser->parse($html);

        self::assertSame(['Reporter One', 'Reporter Two'], $result['authors']);
        self::assertSame(['k1, k2'], $result['tags']);
    }
}
