<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Services;

use JOOservices\CrawlerXNews\Dto\ArticleDetailResultDto;
use JOOservices\CrawlerXNews\Dto\ArticleListResultDto;
use JOOservices\CrawlerXNews\Dto\CrawlRequestDto;
use JOOservices\CrawlerXNews\Exceptions\AdapterNotFoundException;
use JOOservices\CrawlerXNews\Registry\AdapterRegistry;

final class CrawlerXNewsService
{
    public function __construct(private readonly AdapterRegistry $registry)
    {
    }

    public function detail(CrawlRequestDto $request): ArticleDetailResultDto
    {
        $adapter = $this->registry->get($request->site);

        if ($adapter === null) {
            throw new AdapterNotFoundException('Adapter not found for slug: ' . $request->site);
        }

        $adapter->prepareRequest($request);

        return $adapter->detail($request);
    }

    public function listing(CrawlRequestDto $request): ArticleListResultDto
    {
        $adapter = $this->registry->get($request->site);

        if ($adapter === null) {
            throw new AdapterNotFoundException('Adapter not found for slug: ' . $request->site);
        }

        $adapter->prepareRequest($request);

        return $adapter->listing($request);
    }
}
