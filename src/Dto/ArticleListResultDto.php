<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Dto;

use JOOservices\Dto\Core\Dto;

final class ArticleListResultDto extends Dto
{
    /**
     * @param  list<ArticleListItemDto>  $items
     */
    public function __construct(
        public readonly string $url,
        public readonly array $items,
        public readonly ?string $nextUrl = null,
    ) {
    }
}
