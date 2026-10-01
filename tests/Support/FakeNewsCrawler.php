<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Support;

use JOOservices\CrawlerXNews\Adapters\AbstractNewsCrawler;
use JOOservices\CrawlerXNews\Dto\ArticleListItemDto;
use JOOservices\CrawlerXNews\Dto\Selectors;
use Symfony\Component\DomCrawler\Crawler;

final class FakeNewsCrawler extends AbstractNewsCrawler
{
    public function name(): string
    {
        return 'fake';
    }

    public function selectors(): Selectors
    {
        return new Selectors(
            title: 'h1.article-title',
            intro: 'p.article-sapo',
            content: 'div.article-content',
            publishedAt: '.article-date',
            canonicalUrl: 'link[rel="canonical"]',
            image: 'img.cover',
            language: 'meta[name="language"]',
            authorCandidates: ['.article-author'],
            categoryCandidates: ['.article-category'],
            tagCandidates: ['.article-tag'],
        );
    }

    /**
     * @return list<ArticleListItemDto>
     */
    protected function parseListItems(Crawler $crawler): array
    {
        $items = [];
        foreach ($crawler->filter('a.item') as $node) {
            $href = $node instanceof \DOMElement ? $node->getAttribute('href') : '';
            $items[] = new ArticleListItemDto(
                url: $href !== '' ? $href : 'https://example.org/fallback',
                title: trim($node->textContent),
            );
        }

        return $items;
    }
}
