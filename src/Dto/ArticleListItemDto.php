<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Dto;

use DateTimeImmutable;
use JOOservices\Dto\Attributes\MapTo;
use JOOservices\Dto\Core\Dto;

final class ArticleListItemDto extends Dto
{
    public function __construct(
        public readonly string $url,
        public readonly string $title,
        public readonly ?string $intro = null,
        #[MapTo('published_at')]
        public readonly ?DateTimeImmutable $publishedAt = null,
        public readonly ?string $image = null,
    ) {
    }
}
