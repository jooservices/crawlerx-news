<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Dto;

use JOOservices\CrawlerXNews\Enums\PageType;
use JOOservices\Dto\Core\Dto;

final class CrawlRequestDto extends Dto
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $url,
        public readonly PageType $type,
        public readonly string $site,
        public readonly array $options = [],
    ) {
    }
}
