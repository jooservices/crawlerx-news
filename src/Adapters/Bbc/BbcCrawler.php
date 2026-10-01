<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Adapters\Bbc;

use DateTimeImmutable;
use JOOservices\CrawlerXNews\Adapters\AbstractNewsCrawler;
use JOOservices\CrawlerXNews\Dto\ArticleListItemDto;
use JOOservices\CrawlerXNews\Dto\Selectors;
use Symfony\Component\DomCrawler\Crawler;

final class BbcCrawler extends AbstractNewsCrawler
{
    public function name(): string
    {
        return 'bbc';
    }

    public function selectors(): Selectors
    {
        return new Selectors(
            title: 'h1',
            intro: 'p[data-component="standfirst"], [data-testid="standfirst"]',
            content: '[data-component="text-block"]',
            publishedAt: 'time[datetime], time',
            canonicalUrl: 'link[rel="canonical"]',
            image: 'meta[property="og:image"]',
            language: 'html',
            authorCandidates: ['[data-component="byline"] [data-testid="name"]', 'meta[property="article:author"]'],
            categoryCandidates: ['li[data-testid="chip"] a', 'meta[property="article:section"]'],
            tagCandidates: ['li[data-testid="chip"] a', 'meta[property="article:tag"]'],
        );
    }

    protected function resolvePublishedAt(Crawler $crawler, array $jsonLd, array $meta): ?DateTimeImmutable
    {
        $value = $this->stringField($jsonLd['published_at'] ?? null)
            ?? $this->stringField($meta['published_at'] ?? null)
            ?? $this->firstAttribute($crawler, 'time[datetime]', 'datetime');

        return $value !== null ? $this->parseDate($value) : null;
    }

    /**
     * @return list<ArticleListItemDto>
     */
    protected function parseListItems(Crawler $crawler): array
    {
        $items = [];

        foreach ($crawler->filter('a[href*="/news/articles/"]') as $node) {
            $title = trim($node->textContent);
            $href = $node instanceof \DOMElement ? $node->getAttribute('href') : '';

            if ($title === '' || $href === '') {
                continue;
            }

            $url = $this->absoluteUrl($href, 'https://www.bbc.com');
            $items[] = new ArticleListItemDto(url: $url, title: $this->normalizeText($title) ?? $title);
        }

        return $this->uniqueItems($items);
    }

    /**
     * @param  list<ArticleListItemDto>  $items
     * @return list<ArticleListItemDto>
     */
    private function uniqueItems(array $items): array
    {
        $unique = [];
        foreach ($items as $item) {
            $unique[$item->url] = $item;
        }

        return array_values($unique);
    }
}
