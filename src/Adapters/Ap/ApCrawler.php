<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Adapters\Ap;

use JOOservices\CrawlerXNews\Adapters\AbstractNewsCrawler;
use JOOservices\CrawlerXNews\Dto\ArticleListItemDto;
use JOOservices\CrawlerXNews\Dto\Selectors;
use Symfony\Component\DomCrawler\Crawler;

final class ApCrawler extends AbstractNewsCrawler
{
    public function name(): string
    {
        return 'ap';
    }

    public function selectors(): Selectors
    {
        return new Selectors(
            title: 'h1',
            intro: '[data-key="card-headline"], .Article .SubHeadline, [data-key="lead"]',
            content: '[data-key="article-body"], .Article .RichTextBody, article .content',
            publishedAt: 'time[datetime]',
            canonicalUrl: 'link[rel="canonical"]',
            image: 'meta[property="og:image"]',
            language: 'html',
            authorCandidates: ['[data-key="author-name"]', 'a[rel="author"]', 'meta[name="author"]'],
            categoryCandidates: ['meta[property="article:section"]'],
            tagCandidates: ['meta[property="article:tag"]'],
        );
    }

    protected function resolvePublishedAt(Crawler $crawler, array $jsonLd, array $meta): ?\DateTimeImmutable
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

        foreach ($crawler->filter('a[href*="/article/"]') as $node) {
            $href = $node instanceof \DOMElement ? $node->getAttribute('href') : '';
            $rawTitle = $node->textContent;

            if ($href === '' || trim($rawTitle) === '') {
                continue;
            }

            $title = $this->normalizeText($rawTitle);
            if ($title === null || preg_match('/^\d+$/', $title) === 1) {
                continue;
            }

            $items[] = new ArticleListItemDto(
                url: $this->absoluteUrl($href, 'https://apnews.com'),
                title: $title,
            );
        }

        return $this->uniqueByUrl($items);
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
