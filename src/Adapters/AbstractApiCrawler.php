<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Adapters;

use DateTimeImmutable;
use JOOservices\CrawlerXNews\Dto\ArticleDetailResultDto;
use JOOservices\CrawlerXNews\Dto\CrawlRequestDto;
use JOOservices\CrawlerXNews\Exceptions\CrawlParseException;

/**
 * Base class for adapters that resolve article details through a JSON API
 * instead of parsing HTML. Used by sites whose article pages are served to
 * browsers via AJAX (e.g. the VietNamNet / ICTNews newsapi).
 *
 * Subclasses provide the API URL for an article URL and map the decoded JSON
 * into the article field array consumed by ArticleDetailResultDto.
 */
abstract class AbstractApiCrawler extends AbstractNewsCrawler
{
    public function detail(CrawlRequestDto $request): ArticleDetailResultDto
    {
        $apiUrl = $this->apiDetailUrl($request->url);
        $payload = $this->fetchJson($apiUrl, $request);
        $fields = $this->mapApiArticle($payload, $request);

        return new ArticleDetailResultDto(
            url: $request->url,
            title: $this->requireString($fields['title'] ?? null, 'title', $request->url),
            intro: $this->nullableString($fields['intro'] ?? null),
            contentHtml: $this->nullableString($fields['content_html'] ?? null),
            contentText: $this->nullableString($fields['content_text'] ?? null),
            publishedAt: $this->nullableDate($fields['published_at'] ?? null),
            updatedAt: $this->nullableDate($fields['updated_at'] ?? null),
            canonicalUrl: $this->nullableString($fields['canonical_url'] ?? null),
            image: $this->nullableString($fields['image'] ?? null),
            language: $this->nullableString($fields['language'] ?? null),
            authors: $fields['authors'],
            categories: $fields['categories'],
            tags: $fields['tags'],
            source: $request->site,
        );
    }

    /**
     * Build the JSON API URL for a given article page URL.
     */
    abstract protected function apiDetailUrl(string $url): string;

    /**
     * Map a decoded API payload into article fields.
     *
     * @param  array<mixed, mixed>  $payload
     * @return array{title: ?string, intro: ?string, content_html: ?string, content_text: ?string, published_at: ?string, updated_at: ?string, canonical_url: ?string, image: ?string, language: ?string, authors: list<string>, categories: list<string>, tags: list<string>}
     */
    abstract protected function mapApiArticle(array $payload, CrawlRequestDto $request): array;

    /**
     * @return array<mixed, mixed>
     */
    protected function fetchJson(string $apiUrl, CrawlRequestDto $request): array
    {
        $response = $this->client->get($apiUrl);
        $body = $response->toPsrResponse()->getBody()->__toString();

        if (trim($body) === '') {
            throw new CrawlParseException('Empty API response for ' . $apiUrl);
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            throw new CrawlParseException('Invalid JSON from API for ' . $apiUrl);
        }

        return $decoded;
    }

    protected function requireString(mixed $value, string $field, string $url): string
    {
        $string = $this->nullableString($value);
        if ($string === null) {
            throw new CrawlParseException('Could not resolve article ' . $field . ' for ' . $url);
        }

        return $string;
    }

    protected function nullableString(mixed $value): ?string
    {
        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    protected function nullableDate(mixed $value): ?DateTimeImmutable
    {
        $string = $this->nullableString($value);

        return $string !== null ? $this->parseDate($string) : null;
    }

    /**
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }
}
