<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Adapters\Cafebiz;

use JOOservices\CrawlerXNews\Adapters\AbstractNewsCrawler;
use JOOservices\CrawlerXNews\Dto\ArticleListItemDto;
use JOOservices\CrawlerXNews\Dto\Selectors;
use Symfony\Component\DomCrawler\Crawler;

final class CafebizCrawler extends AbstractNewsCrawler
{
    public function name(): string
    {
        return 'cafebiz';
    }

    public function selectors(): Selectors
    {
        return new Selectors(
            title: 'h1, h1.title',
            intro: '.sapo, .description, .detail-sapo',
            content: '.detail-content, .content-detail, #divContent',
            publishedAt: 'time[datetime], .time, .date',
            canonicalUrl: 'link[rel="canonical"]',
            image: 'meta[property="og:image"]',
            language: 'html',
            authorCandidates: ['.author, meta[name="author"]', '[class*="author"]'],
            categoryCandidates: ['meta[property="article:section"]'],
            tagCandidates: ['meta[property="article:tag"]'],
        );
    }

    protected function resolvePublishedAt(Crawler $crawler, array $jsonLd, array $meta): ?\DateTimeImmutable
    {
        $value = $this->stringField($jsonLd['published_at'] ?? null)
            ?? $this->stringField($meta['published_at'] ?? null)
            ?? $this->firstText($crawler, '.time');

        return $value !== null ? $this->parseDate($value) : null;
    }

    /**
     * @return list<ArticleListItemDto>
     */
    protected function parseListItems(Crawler $crawler): array
    {
        $items = [];

        foreach ($crawler->filter('a[href*=".chn"]') as $node) {
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
                url: $this->absoluteUrl($href, 'https://cafebiz.vn'),
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
