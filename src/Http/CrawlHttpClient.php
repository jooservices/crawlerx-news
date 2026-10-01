<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Http;

use JOOservices\CrawlerXNews\Contracts\CrawlHttpResponse;

interface CrawlHttpClient
{
    public function get(string $url): CrawlHttpResponse;
}
