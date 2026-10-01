<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Dto;

use JOOservices\CrawlerXNews\Enums\FetchMethod;
use JOOservices\Dto\Core\Dto;

final class FetchChainDto extends Dto
{
    /**
     * @param  list<FetchMethod>  $methods
     */
    public function __construct(
        public readonly array $methods,
    ) {
    }
}
