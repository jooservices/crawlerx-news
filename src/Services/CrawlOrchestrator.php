<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Services;

use JOOservices\CrawlerXNews\Dto\ArticleDetailResultDto;
use JOOservices\CrawlerXNews\Dto\ArticleListResultDto;
use JOOservices\CrawlerXNews\Dto\CrawlRequestDto;
use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use JOOservices\CrawlerXNews\Enums\PageType;

final class CrawlOrchestrator
{
    public function __construct(
        private readonly UrlClassifier $classifier,
        private readonly CrawlerXNewsService $service,
    ) {
    }

    public function url(string $url): CrawlRequestBuilder
    {
        return new CrawlRequestBuilder($this, $url);
    }

    public function site(string $slug): CrawlRequestBuilder
    {
        return (new CrawlRequestBuilder($this, null))->site($slug);
    }

    public function crawl(
        ?string $url,
        ?string $site = null,
        ?PageType $type = null,
        ?CrawlOptionsDto $options = null,
    ): ArticleDetailResultDto|ArticleListResultDto {
        if ($url === null || trim($url) === '') {
            throw new \JOOservices\CrawlerXNews\Exceptions\UnsupportedUrlException('Empty URL');
        }

        $classified = $this->classifier->classify($url, $site);
        $pageType = $type ?? $classified['type'];

        $request = new CrawlRequestDto(
            url: $classified['url'],
            type: $pageType,
            site: $classified['slug'],
            options: $options?->toArray() ?? [],
        );

        return $pageType === PageType::Listing
            ? $this->service->listing($request)
            : $this->service->detail($request);
    }
}
