<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Enums;

enum PageType: string
{
    case Article = 'article';
    case Listing = 'listing';
}
