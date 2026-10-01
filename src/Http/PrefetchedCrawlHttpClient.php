<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Http;

use JOOservices\CrawlerXNews\Contracts\CrawlHttpResponse;
use Nyholm\Psr7\Response;

/**
 * Serves captured HTML fixtures without any network access. Used by tests
 * and by tooling that must work offline.
 */
final readonly class PrefetchedCrawlHttpClient implements CrawlHttpClient
{
    /**
     * @param  array<string, string>  $urlMap
     */
    public function __construct(private array $urlMap)
    {
    }

    public function get(string $url): CrawlHttpResponse
    {
        if (! isset($this->urlMap[$url])) {
            return new PsrCrawlHttpResponse(new Response(404, [], 'Not found'));
        }

        return new PsrCrawlHttpResponse(new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $this->urlMap[$url]));
    }
}
