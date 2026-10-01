<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Services;

use JOOservices\CrawlerXNews\Enums\PageType;
use JOOservices\CrawlerXNews\Exceptions\AdapterNotFoundException;
use JOOservices\CrawlerXNews\Exceptions\AmbiguousUrlException;
use JOOservices\CrawlerXNews\Exceptions\UnsupportedUrlException;

/**
 * Classifies a URL into a site slug and a page type using the declared
 * manifests. Matching is host-based; page type is resolved from URL fragments.
 */
final class UrlClassifier
{
    /**
     * @param  list<array{slug: string, hosts: list<string>, page_rules: array<string, list<string>>}>  $manifests
     */
    public function __construct(private readonly array $manifests)
    {
    }

    /**
     * @return array{slug: string, type: PageType, url: string}
     */
    public function classify(string $url, ?string $site = null): array
    {
        $host = $this->extractHost($url);
        $slug = $site ?? $this->matchSlug($host);

        $manifest = $this->findManifest($slug);
        $type = $this->resolveType($url, $manifest);

        return ['slug' => $slug, 'type' => $type, 'url' => $url];
    }

    private function extractHost(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new UnsupportedUrlException('Could not parse host from URL: ' . $url);
        }

        return strtolower($host);
    }

    private function matchSlug(string $host): string
    {
        $matches = [];
        foreach ($this->manifests as $manifest) {
            foreach ($manifest['hosts'] as $manifestHost) {
                if ($this->hostMatches($host, $manifestHost)) {
                    $matches[] = $manifest['slug'];
                }
            }
        }

        $matches = array_values(array_unique($matches));

        if (count($matches) === 0) {
            throw new UnsupportedUrlException('No adapter supports host: ' . $host);
        }

        if (count($matches) > 1) {
            throw new AmbiguousUrlException('Multiple adapters match host: ' . $host);
        }

        return $matches[0];
    }

    private function hostMatches(string $host, string $pattern): bool
    {
        $pattern = strtolower($pattern);

        return $host === $pattern || str_ends_with($host, '.' . $pattern);
    }

    /**
     * @return array<string, mixed>
     */
    private function findManifest(string $slug): array
    {
        foreach ($this->manifests as $manifest) {
            if ($manifest['slug'] === $slug) {
                return $manifest;
            }
        }

        throw new AdapterNotFoundException('Adapter manifest not found for slug: ' . $slug);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function resolveType(string $url, array $manifest): PageType
    {
        $rules = $manifest['page_rules'] ?? [];
        $rules = is_array($rules) ? $rules : [];

        $article = $rules['article'] ?? [];
        $listing = $rules['listing'] ?? [];

        foreach (is_array($article) ? $article : [] as $fragment) {
            if (is_string($fragment) && str_contains($url, $fragment)) {
                return PageType::Article;
            }
        }

        foreach (is_array($listing) ? $listing : [] as $fragment) {
            if (is_string($fragment) && str_contains($url, $fragment)) {
                return PageType::Listing;
            }
        }

        throw new UnsupportedUrlException('URL does not match any page rule: ' . $url);
    }
}
