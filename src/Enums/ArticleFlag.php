<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Enums;

enum ArticleFlag: string
{
    case Paywall = 'paywall';
    case Amp = 'amp';
    case Truncated = 'truncated';
}
