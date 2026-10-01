<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Services;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Extracts Open Graph / Twitter / article meta tags and the canonical link.
 */
final class MetaParser
{
    /**
     * @return array{title: ?string, intro: ?string, image: ?string, published_at: ?string, updated_at: ?string, canonical_url: ?string, language: ?string, authors: list<string>, categories: list<string>, tags: list<string>}
     */
    public function parse(string $html): array
    {
        $crawler = new Crawler($html);

        return [
            'title' => $this->metaContent($crawler, ['og:title']),
            'intro' => $this->metaContent($crawler, ['og:description', 'description']),
            'image' => $this->metaContent($crawler, ['og:image', 'twitter:image']),
            'published_at' => $this->metaContent($crawler, ['article:published_time']),
            'updated_at' => $this->metaContent($crawler, ['article:modified_time']),
            'canonical_url' => $this->linkHref($crawler, 'canonical'),
            'language' => $this->htmlLang($crawler),
            'authors' => $this->metaContents($crawler, ['article:author', 'author']),
            'categories' => $this->metaContents($crawler, ['article:section', 'section']),
            'tags' => $this->metaContents($crawler, ['article:tag', 'keywords']),
        ];
    }

    /**
     * @param  list<string>  $properties
     */
    private function metaContent(Crawler $crawler, array $properties): ?string
    {
        foreach ($properties as $property) {
            foreach (['property', 'name'] as $attribute) {
                $selector = sprintf('meta[%s="%s"]', $attribute, $property);
                if ($crawler->filter($selector)->count() === 0) {
                    continue;
                }

                $value = trim($crawler->filter($selector)->first()->attr('content') ?? '');
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $properties
     * @return list<string>
     */
    private function metaContents(Crawler $crawler, array $properties): array
    {
        $values = [];
        foreach ($properties as $property) {
            foreach (['property', 'name'] as $attribute) {
                $selector = sprintf('meta[%s="%s"]', $attribute, $property);
                if ($crawler->filter($selector)->count() === 0) {
                    continue;
                }

                foreach ($crawler->filter($selector)->each(fn(Crawler $node): ?string => $node->attr('content')) as $content) {
                    $value = trim((string) $content);
                    if ($value !== '') {
                        $values[] = $value;
                    }
                }
            }
        }

        return array_values(array_unique($values));
    }

    private function linkHref(Crawler $crawler, string $rel): ?string
    {
        $selector = sprintf('link[rel="%s"]', $rel);
        if ($crawler->filter($selector)->count() === 0) {
            return null;
        }

        $href = trim($crawler->filter($selector)->first()->attr('href') ?? '');

        return $href !== '' ? $href : null;
    }

    private function htmlLang(Crawler $crawler): ?string
    {
        if ($crawler->filter('html')->count() === 0) {
            return null;
        }

        $lang = trim($crawler->filter('html')->first()->attr('lang') ?? '');

        return $lang !== '' ? $lang : null;
    }
}
