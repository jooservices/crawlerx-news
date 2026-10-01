<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Services;

use JsonException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Extracts structured article metadata from JSON-LD blocks
 * (schema.org NewsArticle / Article / BlogPosting).
 */
final class JsonLdParser
{
    /**
     * @return array{title: ?string, intro: ?string, content: ?string, published_at: ?string, updated_at: ?string, authors: list<string>, image: ?string, language: ?string, categories: list<string>, tags: list<string>, canonical_url: ?string}|null
     */
    public function parse(string $html): ?array
    {
        $crawler = new Crawler($html);
        $scriptCount = $crawler->filter('script[type="application/ld+json"]')->count();

        if ($scriptCount === 0) {
            return null;
        }

        foreach ($crawler->filter('script[type="application/ld+json"]') as $script) {
            $content = trim($script->textContent);
            if ($content === '') {
                continue;
            }

            $node = $this->decode($content);
            if ($node === null) {
                continue;
            }

            $mainEntity = $node['mainEntity'] ?? null;
            if (is_array($mainEntity) && $this->isArticleType($mainEntity['@type'] ?? null)) {
                return $this->map($mainEntity);
            }

            foreach ($this->flatten($node) as $candidate) {
                if ($this->isArticleType($candidate['@type'] ?? null)) {
                    return $this->map($candidate);
                }
            }
        }

        return null;
    }

    /**
     * @return array<mixed, mixed>|null
     */
    private function decode(string $content): ?array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * @param  array<mixed, mixed>  $node
     * @return list<array<mixed, mixed>>
     */
    private function flatten(array $node): array
    {
        if (isset($node['@graph']) && is_array($node['@graph'])) {
            $result = [];
            foreach ($node['@graph'] as $child) {
                if (is_array($child)) {
                    $result[] = $child;
                }
            }

            return $result;
        }

        return [$node];
    }

    /**
     * @param  mixed  $type
     */
    private function isArticleType(mixed $type): bool
    {
        if (is_string($type)) {
            return in_array($type, ['NewsArticle', 'Article', 'BlogPosting', 'DiscussionForumPosting'], true);
        }

        if (is_array($type)) {
            foreach ($type as $candidate) {
                if (is_string($candidate) && $this->isArticleType($candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<mixed, mixed>  $node
     * @return array{title: ?string, intro: ?string, content: ?string, published_at: ?string, updated_at: ?string, authors: list<string>, image: ?string, language: ?string, categories: list<string>, tags: list<string>, canonical_url: ?string}
     */
    private function map(array $node): array
    {
        $mainEntity = $node['mainEntityOfPage'] ?? null;

        return [
            'title' => $this->stringValue($node['headline'] ?? $node['name'] ?? null),
            'intro' => $this->stringValue($node['description'] ?? null),
            'content' => $this->stringValue($node['articleBody'] ?? $node['text'] ?? null),
            'published_at' => $this->stringValue($node['datePublished'] ?? null),
            'updated_at' => $this->stringValue($node['dateModified'] ?? null),
            'authors' => $this->authors($node['author'] ?? null),
            'image' => $this->stringValue($node['image'] ?? null),
            'language' => $this->stringValue($node['inLanguage'] ?? null),
            'categories' => $this->categoryValues($node['articleSection'] ?? null),
            'tags' => $this->categoryValues($node['keywords'] ?? null),
            'canonical_url' => $this->stringValue(
                is_array($mainEntity) ? ($mainEntity['@id'] ?? null) : null,
            ) ?? $this->stringValue($node['url'] ?? null),
        ];
    }

    /**
     * @return list<string>
     */
    private function categoryValues(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            return array_values(array_filter(
                array_map('trim', explode(',', $value)),
                fn(string $item): bool => $item !== '',
            ));
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function authors(mixed $author): array
    {
        if (is_string($author) && $author !== '') {
            return [$author];
        }

        if (! is_array($author)) {
            return [];
        }

        $names = [];
        if (isset($author['name'])) {
            $name = $this->stringValue($author['name']);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        if (isset($author[0]) && is_array($author[0])) {
            foreach ($author as $entry) {
                $name = $this->stringValue(is_array($entry) ? ($entry['name'] ?? null) : null);
                if ($name !== null) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        if (is_array($value)) {
            if (isset($value['url']) && is_string($value['url'])) {
                return trim($value['url']);
            }

            if (isset($value['name']) && is_string($value['name'])) {
                return trim($value['name']);
            }

            foreach ($value as $candidate) {
                $string = $this->stringValue($candidate);
                if ($string !== null) {
                    return $string;
                }
            }
        }

        return null;
    }
}
