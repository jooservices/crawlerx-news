<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Contracts;

use JOOservices\CrawlerXNews\Dto\ArticleDetailResultDto;
use JOOservices\CrawlerXNews\Dto\CrawlRequestDto;

interface ArticleDetailCapable
{
    public function detail(CrawlRequestDto $request): ArticleDetailResultDto;
}
