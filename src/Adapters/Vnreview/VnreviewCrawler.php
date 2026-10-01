<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Adapters\Vnreview;

use JOOservices\CrawlerXNews\Adapters\AbstractNewsCrawler;
use JOOservices\CrawlerXNews\Dto\ArticleListItemDto;
use JOOservices\CrawlerXNews\Dto\Selectors;
use Symfony\Component\DomCrawler\Crawler;

final class VnreviewCrawler extends AbstractNewsCrawler
{
    public function name(): string
    {
        return 'vnreview';
    }

    public function selectors(): Selectors
    {
        return new Selectors(
            title: 'h1, .p-title-value',
            intro: '.article-intro',
            content: '.message-body, .bbWrapper, article .messageContent',
            publishedAt: 'time[datetime], .date',
            canonicalUrl: 'link[rel="canonical"]',
            image: 'meta[property="og:image"]',
            language: 'html',
            authorCandidates: ['[itemprop="author"]', '.username', '.message-author'],
            categoryCandidates: ['meta[property="article:section"]'],
            tagCandidates: ['meta[property="article:tag"]'],
        );
    }

    protected function resolvePublishedAt(Crawler $crawler, array $jsonLd, array $meta): ?\DateTimeImmutable
    {
        $value = $this->stringField($jsonLd['published_at'] ?? null)
            ?? $this->firstAttribute($crawler, 'time[datetime]', 'datetime')
            ?? $this->firstText($crawler, '.date');

        return $value !== null ? $this->parseDate($value) : null;
    }

    /**
     * @return list<ArticleListItemDto>
     */
    protected function parseListItems(Crawler $crawler): array
    {
        $items = [];

        foreach ($crawler->filter('a[href*="/threads/"]') as $node) {
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
                url: $this->absoluteUrl($href, 'https://vnreview.vn'),
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
