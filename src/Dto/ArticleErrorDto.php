<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Dto;

use JOOservices\CrawlerXNews\Enums\ArticleErrorCode;
use JOOservices\Dto\Core\Dto;

final class ArticleErrorDto extends Dto
{
    public function __construct(
        public readonly ArticleErrorCode $code,
        public readonly string $message,
        public readonly ?string $url = null,
    ) {
    }
}
