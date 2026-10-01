<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Adapters\Vnexpress;

use JOOservices\CrawlerXNews\Adapters\AbstractNewsCrawler;
use JOOservices\CrawlerXNews\Dto\ArticleListItemDto;
use JOOservices\CrawlerXNews\Dto\Selectors;
use Symfony\Component\DomCrawler\Crawler;

final class VnexpressCrawler extends AbstractNewsCrawler
{
    public function name(): string
    {
        return 'vnexpress';
    }

    public function selectors(): Selectors
    {
        return new Selectors(
            title: 'h1.title-detail',
            intro: 'p.description',
            content: 'article.fck_detail',
            publishedAt: 'span.date',
            canonicalUrl: 'link[rel="canonical"]',
            image: 'meta[property="og:image"]',
            language: 'html',
            authorCandidates: ['p.author', 'meta[name="author"]'],
            categoryCandidates: ['meta[property="article:section"]'],
            tagCandidates: ['meta[property="article:tag"]'],
        );
    }

    protected function resolvePublishedAt(Crawler $crawler, array $jsonLd, array $meta): ?\DateTimeImmutable
    {
        $value = $this->stringField($jsonLd['published_at'] ?? null)
            ?? $this->stringField($meta['published_at'] ?? null)
            ?? $this->firstText($crawler, 'span.date');

        return $value !== null ? $this->parseDate($value) : null;
    }

    /**
     * @return list<ArticleListItemDto>
     */
    protected function parseListItems(Crawler $crawler): array
    {
        $items = [];

        foreach ($crawler->filter('a[href*="vnexpress.net/"][href*=".html"]') as $node) {
            $href = $node instanceof \DOMElement ? $node->getAttribute('href') : '';
            $title = $node->textContent;

            if ($href === '' || trim($title) === '') {
                continue;
            }

            $title = $this->normalizeText($title);
            if ($title === null) {
                continue;
            }

            $items[] = new ArticleListItemDto(url: $href, title: $title);
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
