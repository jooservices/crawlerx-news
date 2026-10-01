<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Contracts;

use Psr\Http\Message\ResponseInterface;

interface CrawlHttpResponse
{
    public function toPsrResponse(): ResponseInterface;
}
