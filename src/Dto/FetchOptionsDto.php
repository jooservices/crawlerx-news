<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Dto;

use JOOservices\CrawlerXNews\Enums\FetchMethod;
use JOOservices\CrawlerXNews\Enums\FetchProfile;
use JOOservices\Dto\Core\Dto;

final class FetchOptionsDto extends Dto
{
    public function __construct(
        public readonly ?FetchProfile $profile = null,
        public readonly ?FetchMethod $method = null,
        public readonly ?FetchChainDto $chain = null,
        public readonly bool $noFallback = false,
    ) {
    }
}
