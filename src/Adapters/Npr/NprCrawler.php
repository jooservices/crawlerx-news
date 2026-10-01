<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Adapters\Npr;

use JOOservices\CrawlerXNews\Adapters\AbstractNewsCrawler;
use JOOservices\CrawlerXNews\Dto\ArticleListItemDto;
use JOOservices\CrawlerXNews\Dto\Selectors;
use Symfony\Component\DomCrawler\Crawler;

final class NprCrawler extends AbstractNewsCrawler
{
    public function name(): string
    {
        return 'npr';
    }

    public function selectors(): Selectors
    {
        return new Selectors(
            title: 'h1',
            intro: '.storytext .dek, [data-component="dek"]',
            content: '.storytext',
            publishedAt: 'time[datetime], time',
            canonicalUrl: 'link[rel="canonical"]',
            image: 'meta[property="og:image"]',
            language: 'html',
            authorCandidates: ['[data-author] [data-author-name]', 'meta[name="author"]'],
            categoryCandidates: ['meta[property="article:section"]'],
            tagCandidates: ['meta[property="article:tag"]'],
        );
    }

    /**
     * @return list<ArticleListItemDto>
     */
    protected function parseListItems(Crawler $crawler): array
    {
        $items = [];

        foreach ($crawler->filter('a[href*="nx-s1-"]') as $node) {
            $href = $node instanceof \DOMElement ? $node->getAttribute('href') : '';
            $title = $node->textContent;

            if ($href === '' || trim($title) === '') {
                continue;
            }

            $title = $this->normalizeText($title);
            if ($title === null) {
                continue;
            }

            $items[] = new ArticleListItemDto(url: $this->absoluteUrl($href, 'https://www.npr.org'), title: $title);
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
