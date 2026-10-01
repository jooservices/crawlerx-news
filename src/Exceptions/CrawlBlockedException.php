<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Exceptions;

use JOOservices\CrawlerXNews\Dto\FetchMetaDto;
use RuntimeException;

class CrawlBlockedException extends RuntimeException
{
    public readonly ?FetchMetaDto $fetch;

    public function __construct(string $message, ?FetchMetaDto $fetch = null)
    {
        parent::__construct($message);

        $this->fetch = $fetch;
    }
}
