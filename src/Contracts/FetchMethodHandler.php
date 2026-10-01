<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Contracts;

use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use JOOservices\CrawlerXNews\Dto\FetchResultDto;
use JOOservices\CrawlerXNews\Enums\FetchMethod;

interface FetchMethodHandler
{
    public function supports(FetchMethod $method): bool;

    public function fetch(
        string $url,
        FetchMethod $method,
        ?CrawlOptionsDto $options = null,
    ): FetchResultDto;
}
