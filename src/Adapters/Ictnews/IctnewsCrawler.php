<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Adapters\Ictnews;

use JOOservices\CrawlerXNews\Adapters\AbstractApiCrawler;
use JOOservices\CrawlerXNews\Adapters\Concerns\VietnamnetApiMapper;
use JOOservices\CrawlerXNews\Dto\ArticleListItemDto;
use JOOservices\CrawlerXNews\Dto\CrawlRequestDto;
use JOOservices\CrawlerXNews\Dto\Selectors;
use JOOservices\CrawlerXNews\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * ICTNews is a VietNamNet sub-publication. Its article HTML pages are served
 * to browsers via AJAX; the shared VietNamNet newsapi resolves article details
 * as JSON. The listing page is parsed from the category HTML.
 */
final class IctnewsCrawler extends AbstractApiCrawler
{
    use VietnamnetApiMapper;

    public function name(): string
    {
        return 'ictnews';
    }

    public function selectors(): Selectors
    {
        return new Selectors(
            title: 'h1.title, h1',
            intro: '.lead, .sapo, .description',
            content: '.content-detail, .main-content, .article-content',
            publishedAt: 'time[datetime], .time, .date',
            canonicalUrl: 'link[rel="canonical"]',
            image: 'meta[property="og:image"]',
            language: 'html',
            authorCandidates: ['meta[name="author"]', '.author', '[class*="author"]'],
            categoryCandidates: ['meta[property="article:section"]'],
            tagCandidates: ['meta[property="article:tag"]'],
        );
    }

    protected function apiDetailUrl(string $url): string
    {
        return 'https://vietnamnet.vn/newsapi/NewsDetail/Get?id=' . $this->extractId($url);
    }

    /**
     * @param  array<mixed, mixed>  $payload
     * @return array{title: ?string, intro: ?string, content_html: ?string, content_text: ?string, published_at: ?string, updated_at: ?string, canonical_url: ?string, image: ?string, language: ?string, authors: list<string>, categories: list<string>, tags: list<string>}
     */
    protected function mapApiArticle(array $payload, CrawlRequestDto $request): array
    {
        return $this->mapVietnamnetPayload($payload);
    }

    /**
     * @return list<ArticleListItemDto>
     */
    protected function parseListItems(Crawler $crawler): array
    {
        $items = [];

        foreach ($crawler->filter('a[href*=".html"]') as $node) {
            $href = $node instanceof \DOMElement ? $node->getAttribute('href') : '';
            $title = $node->textContent;

            if ($href === '' || trim($title) === '') {
                continue;
            }

            $title = $this->normalizeText($title);
            if ($title === null) {
                continue;
            }

            $items[] = new ArticleListItemDto(
                url: $this->absoluteUrl($href, 'https://ictnews.vn'),
                title: $title,
            );
        }

        return $this->uniqueByUrl($items);
    }

    private function extractId(string $url): string
    {
        if (preg_match('/-(\d+)\.html$/', $url, $matches) !== 1) {
            throw new CrawlParseException('Could not extract article id from URL: ' . $url);
        }

        return $matches[1];
    }

    /**
     * @param  list<ArticleListItemDto>  $items
     * @return list<ArticleListItemDto>
     */
    private function uniqueByUrl(array $items): array
    {
        $unique = [];
        foreach ($items as $item) {
            $unique[$item->url] = $item;
        }

        return array_values($unique);
    }
}
