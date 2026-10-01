<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Unit;

use JOOservices\CrawlerXNews\Enums\PageType;
use JOOservices\CrawlerXNews\Exceptions\UnsupportedUrlException;
use JOOservices\CrawlerXNews\Services\UrlClassifier;
use PHPUnit\Framework\TestCase;

final class UrlClassifierTest extends TestCase
{
    /**
     * @return list<array{slug: string, hosts: list<string>, page_rules: array<string, list<string>>}>
     */
    private function manifests(): array
    {
        return [
            [
                'slug' => 'bbc',
                'hosts' => ['bbc.com'],
                'page_rules' => ['article' => ['/news/articles/'], 'listing' => ['/news']],
            ],
            [
                'slug' => 'genk',
                'hosts' => ['genk.vn'],
                'page_rules' => ['article' => ['-a'], 'listing' => ['/']],
            ],
        ];
    }

    public function testClassifiesArticleByHostAndFragment(): void
    {
        $classifier = new UrlClassifier($this->manifests());
        $result = $classifier->classify('https://www.bbc.com/news/articles/c12345678');

        self::assertSame('bbc', $result['slug']);
        self::assertSame(PageType::Article, $result['type']);
    }

    public function testClassifiesListing(): void
    {
        $classifier = new UrlClassifier($this->manifests());
        $result = $classifier->classify('https://genk.vn/');

        self::assertSame('genk', $result['slug']);
        self::assertSame(PageType::Listing, $result['type']);
    }

    public function testExplicitSiteOverridesDetection(): void
    {
        $classifier = new UrlClassifier($this->manifests());
        $result = $classifier->classify('https://genk.vn/dien-thoai-a1234567.html', 'genk');

        self::assertSame('genk', $result['slug']);
        self::assertSame(PageType::Article, $result['type']);
    }

    public function testRejectsUnsupportedHost(): void
    {
        $this->expectException(UnsupportedUrlException::class);

        (new UrlClassifier($this->manifests()))->classify('https://example.org/story');
    }

    public function testRejectsUnmatchedPageFragment(): void
    {
        $this->expectException(UnsupportedUrlException::class);

        (new UrlClassifier($this->manifests()))->classify('https://www.bbc.com/sport');
    }
}
