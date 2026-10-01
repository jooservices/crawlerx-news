<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews;

use JOOservices\CrawlerXNews\Services\CrawlRequestBuilder;

final class CrawlerXNews
{
    public static function url(string $url): CrawlRequestBuilder
    {
        return CrawlerXNewsFactory::create()->url($url);
    }

    public static function site(string $slug): CrawlRequestBuilder
    {
        return CrawlerXNewsFactory::create()->site($slug);
    }
}
