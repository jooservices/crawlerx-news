<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Dto;

use JOOservices\Dto\Attributes\MapTo;
use JOOservices\Dto\Core\Dto;

/**
 * Per-request crawl options. Kept minimal for the news library: HTTP timeout
 * and optional prefetched HTML map (used by tests and offline tooling).
 */
final class CrawlOptionsDto extends Dto
{
    /**
     * @param  array<string, string>  $prefetchedHtml  url => html
     */
    public function __construct(
        public readonly ?int $timeout = null,
        #[MapTo('prefetched_html')]
        public readonly array $prefetchedHtml = [],
    ) {
    }
}
