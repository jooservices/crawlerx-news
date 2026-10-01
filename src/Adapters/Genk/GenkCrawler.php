<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Adapters\Genk;

use JOOservices\CrawlerXNews\Adapters\AbstractNewsCrawler;
use JOOservices\CrawlerXNews\Dto\ArticleListItemDto;
use JOOservices\CrawlerXNews\Dto\Selectors;
use Symfony\Component\DomCrawler\Crawler;

final class GenkCrawler extends AbstractNewsCrawler
{
    public function name(): string
    {
        return 'genk';
    }

    public function selectors(): Selectors
    {
        return new Selectors(
            title: 'h1.kbw-title, h1.title, h1',
            intro: '.knc-sapo, .sapo, [data-sapo]',
            content: '.knc-content, .content-detail, article .detail-content',
            publishedAt: '.kbw-time, .date, time',
            canonicalUrl: 'link[rel="canonical"]',
            image: 'meta[property="og:image"]',
            language: 'html',
            authorCandidates: ['.kbw-title-author, [class*="author"] a, meta[property="article:author"]'],
            categoryCandidates: ['meta[property="article:section"]', '.breadcrumb li:last-child a'],
            tagCandidates: ['meta[property="article:tag"]', '.kbw-tag a, [class*="tag"] a'],
        );
    }

    /**
     * @return list<ArticleListItemDto>
     */
    protected function parseListItems(Crawler $crawler): array
    {
        $items = [];

        foreach ($crawler->filter('a[href*="-a"]') as $node) {
            $title = trim($node->textContent);
            $href = $node instanceof \DOMElement ? $node->getAttribute('href') : '';

            if ($title === '' || $href === '') {
                continue;
            }

            $url = $this->absoluteUrl($href, 'https://genk.vn');
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
