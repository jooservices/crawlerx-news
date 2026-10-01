<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Adapters;

use DateTimeImmutable;
use JOOservices\CrawlerXNews\Contracts\ArticleDetailCapable;
use JOOservices\CrawlerXNews\Contracts\ArticleListingCapable;
use JOOservices\CrawlerXNews\Contracts\SiteAdapter;
use JOOservices\CrawlerXNews\Dto\ArticleDetailResultDto;
use JOOservices\CrawlerXNews\Dto\ArticleListItemDto;
use JOOservices\CrawlerXNews\Dto\ArticleListResultDto;
use JOOservices\CrawlerXNews\Dto\CrawlRequestDto;
use JOOservices\CrawlerXNews\Dto\Selectors;
use JOOservices\CrawlerXNews\Dto\SiteProfile;
use JOOservices\CrawlerXNews\Exceptions\CrawlParseException;
use JOOservices\CrawlerXNews\Fetch\FetchFallbackChain;
use JOOservices\CrawlerXNews\Fetch\FetchPlanResolver;
use JOOservices\CrawlerXNews\Http\CrawlHttpClient;
use JOOservices\CrawlerXNews\Services\ClientFactory;
use JOOservices\CrawlerXNews\Services\JsonLdParser;
use JOOservices\CrawlerXNews\Services\MetaParser;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Base class for site adapters. Fetches the page, then assembles article DTOs
 * with a field-priority chain: JSON-LD first, Open Graph / meta second, and
 * HTML selectors as the fallback for each field. Subclasses provide
 * site-specific selectors and the listing item parser.
 */
abstract class AbstractNewsCrawler implements ArticleDetailCapable, ArticleListingCapable, SiteAdapter
{
    protected CrawlHttpClient $client;

    protected readonly Selectors $selectors;

    protected readonly JsonLdParser $jsonLdParser;

    protected readonly MetaParser $metaParser;

    protected ?FetchFallbackChain $fetchChain = null;

    protected ?SiteProfile $siteProfile = null;

    public function __construct(private readonly ClientFactory $clientFactory)
    {
        $this->client = $this->clientFactory->factory();
        $this->selectors = $this->selectors();
        $this->jsonLdParser = new JsonLdParser();
        $this->metaParser = new MetaParser();
    }

    abstract public function name(): string;

    abstract public function selectors(): Selectors;

    public function setFetchChain(?FetchFallbackChain $chain, ?SiteProfile $profile = null): void
    {
        $this->fetchChain = $chain;
        $this->siteProfile = $profile;
    }

    public function prepareRequest(CrawlRequestDto $request): void
    {
        $this->refreshClient($request);
    }

    protected function refreshClient(CrawlRequestDto $request): void
    {
        $options = $request->options;

        $prefetched = $options['prefetched_html'] ?? null;
        if (is_array($prefetched) && $prefetched !== []) {
            $this->client = $this->clientFactory->factory(['prefetched_html' => $prefetched]);

            return;
        }

        $http = [];
        $timeout = $options['timeout'] ?? null;
        if (is_int($timeout)) {
            $http['timeout'] = $timeout;
        }

        if ($http !== []) {
            $this->client = $this->clientFactory->factory($http);
        }
    }

    /**
     * Parse an article detail page into a typed result.
     */
    public function detail(CrawlRequestDto $request): ArticleDetailResultDto
    {
        $html = $this->fetch($request);
        $crawler = new Crawler($html);
        $jsonLd = $this->jsonLdParser->parse($html) ?? [];
        $meta = $this->metaParser->parse($html);

        return new ArticleDetailResultDto(
            url: $request->url,
            title: $this->resolveTitle($crawler, $jsonLd, $meta, $request),
            intro: $this->resolveIntro($crawler, $jsonLd, $meta),
            contentHtml: $this->resolveContentHtml($crawler, $jsonLd),
            contentText: $this->resolveContentText($crawler, $jsonLd),
            publishedAt: $this->resolvePublishedAt($crawler, $jsonLd, $meta),
            updatedAt: $this->resolveUpdatedAt($jsonLd, $meta),
            canonicalUrl: $this->resolveCanonicalUrl($crawler, $jsonLd, $meta),
            image: $this->resolveImage($crawler, $jsonLd, $meta),
            language: $this->resolveLanguage($crawler, $jsonLd, $meta),
            authors: $this->resolveAuthors($crawler, $jsonLd, $meta),
            categories: $this->resolveCategories($crawler, $jsonLd, $meta),
            tags: $this->resolveTags($crawler, $jsonLd, $meta),
            source: $request->site,
        );
    }

    /**
     * Parse a listing / category page into typed list items.
     *
     * @return list<ArticleListItemDto>
     */
    abstract protected function parseListItems(Crawler $crawler): array;

    public function listing(CrawlRequestDto $request): ArticleListResultDto
    {
        $html = $this->fetch($request);
        $crawler = new Crawler($html);

        return new ArticleListResultDto(
            url: $request->url,
            items: $this->parseListItems($crawler),
        );
    }

    protected function fetch(CrawlRequestDto $request): string
    {
        $prefetched = $request->options['prefetched_html'] ?? null;
        if (is_array($prefetched) && $prefetched !== []) {
            $body = $this->client->get($request->url)->toPsrResponse()->getBody()->__toString();
        } elseif ($this->fetchChain instanceof FetchFallbackChain && $this->siteProfile !== null) {
            $plan = (new FetchPlanResolver())->resolve($this->siteProfile);
            $result = $this->fetchChain->fetch($request->url, $plan);
            $body = $result->body;
        } else {
            $body = $this->client->get($request->url)->toPsrResponse()->getBody()->__toString();
        }

        if (trim($body) === '') {
            throw new CrawlParseException('Empty response body for ' . $request->url);
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $jsonLd
     * @param  array<string, mixed>  $meta
     */
    protected function resolveTitle(Crawler $crawler, array $jsonLd, array $meta, CrawlRequestDto $request): string
    {
        $title = $this->stringField($jsonLd['title'] ?? null)
            ?? $this->stringField($meta['title'] ?? null)
            ?? $this->firstText($crawler, $this->selectors->title);

        if ($title === null) {
            throw new CrawlParseException('Could not resolve article title for ' . $request->url);
        }

        return $title;
    }

    /**
     * @param  array<string, mixed>  $jsonLd
     * @param  array<string, mixed>  $meta
     */
    protected function resolveIntro(Crawler $crawler, array $jsonLd, array $meta): ?string
    {
        return $this->stringField($jsonLd['intro'] ?? null)
            ?? $this->stringField($meta['intro'] ?? null)
            ?? $this->firstText($crawler, $this->selectors->intro);
    }

    /**
     * @param  array<string, mixed>  $jsonLd
     */
    protected function resolveContentHtml(Crawler $crawler, array $jsonLd): ?string
    {
        $content = $this->stringField($jsonLd['content'] ?? null);

        return $content ?? $this->firstHtml($crawler, $this->selectors->content);
    }

    /**
     * @param  array<string, mixed>  $jsonLd
     */
    protected function resolveContentText(Crawler $crawler, array $jsonLd): ?string
    {
        $html = $this->resolveContentHtml($crawler, $jsonLd);

        if ($html === null) {
            return null;
        }

        return $this->normalizeText($html);
    }

    /**
     * @param  array<string, mixed>  $jsonLd
     * @param  array<string, mixed>  $meta
     */
    protected function resolvePublishedAt(Crawler $crawler, array $jsonLd, array $meta): ?DateTimeImmutable
    {
        $value = $this->stringField($jsonLd['published_at'] ?? null)
            ?? $this->stringField($meta['published_at'] ?? null)
            ?? $this->firstText($crawler, $this->selectors->publishedAt);

        return $value !== null ? $this->parseDate($value) : null;
    }

    /**
     * @param  array<string, mixed>  $jsonLd
     * @param  array<string, mixed>  $meta
     */
    protected function resolveUpdatedAt(array $jsonLd, array $meta): ?DateTimeImmutable
    {
        $value = $this->stringField($jsonLd['updated_at'] ?? null)
            ?? $this->stringField($meta['updated_at'] ?? null);

        return $value !== null ? $this->parseDate($value) : null;
    }

    /**
     * @param  array<string, mixed>  $jsonLd
     * @param  array<string, mixed>  $meta
     */
    protected function resolveCanonicalUrl(Crawler $crawler, array $jsonLd, array $meta): ?string
    {
        return $this->stringField($jsonLd['canonical_url'] ?? null)
            ?? $this->stringField($meta['canonical_url'] ?? null)
            ?? $this->firstAttribute($crawler, $this->selectors->canonicalUrl, 'href');
    }

    /**
     * @param  array<string, mixed>  $jsonLd
     * @param  array<string, mixed>  $meta
     */
    protected function resolveImage(Crawler $crawler, array $jsonLd, array $meta): ?string
    {
        $src = $this->stringField($jsonLd['image'] ?? null)
            ?? $this->stringField($meta['image'] ?? null)
            ?? $this->firstAttribute($crawler, $this->selectors->image, 'src');

        if ($src !== null) {
            return $src;
        }

        return $this->firstAttribute($crawler, $this->selectors->image, 'content');
    }

    /**
     * @param  array<string, mixed>  $jsonLd
     * @param  array<string, mixed>  $meta
     */
    protected function resolveLanguage(Crawler $crawler, array $jsonLd, array $meta): ?string
    {
        return $this->stringField($jsonLd['language'] ?? null)
            ?? $this->stringField($meta['language'] ?? null)
            ?? $this->firstAttribute($crawler, $this->selectors->language, 'content');
    }

    /**
     * @param  array<string, mixed>  $jsonLd
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    protected function resolveAuthors(Crawler $crawler, array $jsonLd, array $meta): array
    {
        $authors = [];

        foreach ((array) ($jsonLd['authors'] ?? []) as $author) {
            if (is_string($author)) {
                $authors[] = $author;
            }
        }

        foreach ((array) ($meta['authors'] ?? []) as $author) {
            if (is_string($author)) {
                $authors[] = $author;
            }
        }

        foreach ($this->selectors->authorCandidates as $selector) {
            $authors = [...$authors, ...$this->texts($crawler, $selector)];
        }

        return array_values(array_unique($authors));
    }

    /**
     * @param  array<string, mixed>  $jsonLd
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    protected function resolveCategories(Crawler $crawler, array $jsonLd, array $meta): array
    {
        $categories = [];

        foreach ((array) ($jsonLd['categories'] ?? []) as $category) {
            if (is_string($category)) {
                $categories[] = $category;
            }
        }

        foreach ((array) ($meta['categories'] ?? []) as $category) {
            if (is_string($category)) {
                $categories[] = $category;
            }
        }

        foreach ($this->selectors->categoryCandidates as $selector) {
            $categories = [...$categories, ...$this->texts($crawler, $selector)];
        }

        return array_values(array_unique($categories));
    }

    /**
     * @param  array<string, mixed>  $jsonLd
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    protected function resolveTags(Crawler $crawler, array $jsonLd, array $meta): array
    {
        $tags = [];

        foreach ((array) ($jsonLd['tags'] ?? []) as $tag) {
            if (is_string($tag)) {
                $tags[] = $tag;
            }
        }

        foreach ((array) ($meta['tags'] ?? []) as $tag) {
            if (is_string($tag)) {
                $tags[] = $tag;
            }
        }

        foreach ($this->selectors->tagCandidates as $selector) {
            $tags = [...$tags, ...$this->texts($crawler, $selector)];
        }

        return array_values(array_unique($tags));
    }

    protected function parseDate(string $value): ?DateTimeImmutable
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($normalized);
        } catch (\Exception) {
            return null;
        }
    }

    protected function firstText(Crawler $crawler, string $selector): ?string
    {
        if ($selector === '' || $crawler->filter($selector)->count() === 0) {
            return null;
        }

        return $this->normalizeText($crawler->filter($selector)->first()->text(''));
    }

    protected function firstHtml(Crawler $crawler, string $selector): ?string
    {
        if ($selector === '' || $crawler->filter($selector)->count() === 0) {
            return null;
        }

        $html = $crawler->filter($selector)->first()->html();

        return trim($html) !== '' ? $html : null;
    }

    protected function firstAttribute(Crawler $crawler, string $selector, string $attribute): ?string
    {
        if ($selector === '' || $crawler->filter($selector)->count() === 0) {
            return null;
        }

        $value = $crawler->filter($selector)->first()->attr($attribute);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return list<string>
     */
    protected function texts(Crawler $crawler, string $selector): array
    {
        if ($selector === '' || $crawler->filter($selector)->count() === 0) {
            return [];
        }

        return array_values(array_unique(array_filter(
            $crawler->filter($selector)->each(fn(Crawler $node): string => $this->normalizeText($node->text('')) ?? ''),
            fn(string $text): bool => $text !== '',
        )));
    }

    protected function normalizeText(string $text): ?string
    {
        $normalized = preg_replace('/\s+/', ' ', html_entity_decode($text));
        $normalized = trim(is_string($normalized) ? $normalized : '');

        return $normalized !== '' ? $normalized : null;
    }

    protected function stringField(mixed $value): ?string
    {
        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    protected function absoluteUrl(string $href, string $baseUrl): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
    }
}
