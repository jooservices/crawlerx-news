<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Unit;

use JOOservices\CrawlerXNews\Dto\CrawlRequestDto;
use JOOservices\CrawlerXNews\Enums\PageType;
use JOOservices\CrawlerXNews\Exceptions\CrawlParseException;
use JOOservices\CrawlerXNews\Services\ClientFactory;
use JOOservices\CrawlerXNews\Tests\Support\FakeNewsCrawler;
use PHPUnit\Framework\TestCase;

final class AbstractNewsCrawlerTest extends TestCase
{
    private function crawler(): FakeNewsCrawler
    {
        return new FakeNewsCrawler(new ClientFactory());
    }

    private function request(string $url): CrawlRequestDto
    {
        return new CrawlRequestDto(
            url: $url,
            type: PageType::Article,
            site: 'fake',
        );
    }

    public function testParsesArticleFromHtmlSelectors(): void
    {
        $html = <<<'HTML'
        <html>
        <head>
            <link rel="canonical" href="https://example.org/story/1">
            <meta name="language" content="vi">
        </head>
        <body>
            <h1 class="article-title">HTML title fallback</h1>
            <p class="article-sapo">HTML intro fallback</p>
            <span class="article-date">2026-09-10T05:00:00+07:00</span>
            <div class="article-content"><p>First paragraph.</p><p>Second paragraph.</p></div>
            <span class="article-author">Author A</span>
            <span class="article-category">Tech</span>
            <span class="article-tag">AI</span>
            <img class="cover" src="https://example.org/img.jpg">
        </body>
        </html>
        HTML;

        $crawler = $this->crawler();
        $crawler->prepareRequest($this->request('https://example.org/story/1')->with(
            options: ['prefetched_html' => ['https://example.org/story/1' => $html]],
        ));

        $result = $crawler->detail($this->request('https://example.org/story/1')->with(
            options: ['prefetched_html' => ['https://example.org/story/1' => $html]],
        ));

        self::assertSame('HTML title fallback', $result->title);
        self::assertSame('HTML intro fallback', $result->intro);
        self::assertSame('vi', $result->language);
        self::assertSame(['Author A'], $result->authors);
        self::assertSame(['Tech'], $result->categories);
        self::assertSame(['AI'], $result->tags);
        self::assertSame('https://example.org/story/1', $result->canonicalUrl);
        self::assertSame('https://example.org/img.jpg', $result->image);
        self::assertSame('2026-09-10', $result->publishedAt?->format('Y-m-d'));
        self::assertStringContainsString('First paragraph', (string) $result->contentHtml);
        self::assertStringContainsString('Second paragraph', (string) $result->contentText);
    }

    public function testThrowsWhenTitleMissing(): void
    {
        $html = '<html><body><p>no title here</p></body></html>';
        $crawler = $this->crawler();
        $request = $this->request('https://example.org/story/2')->with(
            options: ['prefetched_html' => ['https://example.org/story/2' => $html]],
        );

        $this->expectException(CrawlParseException::class);

        $crawler->detail($request);
    }

    public function testParsesListingItems(): void
    {
        $html = <<<'HTML'
        <html><body>
            <a class="item" href="https://example.org/story/1">First story</a>
            <a class="item" href="https://example.org/story/2">Second story</a>
        </body></html>
        HTML;

        $crawler = $this->crawler();
        $request = new CrawlRequestDto(
            url: 'https://example.org/',
            type: PageType::Listing,
            site: 'fake',
            options: ['prefetched_html' => ['https://example.org/' => $html]],
        );
        $crawler->prepareRequest($request);

        $result = $crawler->listing($request);

        self::assertCount(2, $result->items);
        self::assertSame('https://example.org/story/1', $result->items[0]->url);
    }

    public function testThrowsOnEmptyBody(): void
    {
        $crawler = $this->crawler();
        $request = $this->request('https://example.org/empty')->with(
            options: ['prefetched_html' => ['https://example.org/empty' => '']],
        );

        $this->expectException(CrawlParseException::class);

        $crawler->detail($request);
    }
}
