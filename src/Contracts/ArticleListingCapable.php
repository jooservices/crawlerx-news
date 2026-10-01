<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Contracts;

use JOOservices\CrawlerXNews\Dto\ArticleListResultDto;
use JOOservices\CrawlerXNews\Dto\CrawlRequestDto;

interface ArticleListingCapable
{
    public function listing(CrawlRequestDto $request): ArticleListResultDto;
}
