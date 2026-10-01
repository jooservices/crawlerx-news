<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Dto;

use DateTimeImmutable;
use JOOservices\CrawlerXNews\Enums\ArticleFlag;
use JOOservices\Dto\Attributes\MapTo;
use JOOservices\Dto\Core\Dto;

final class ArticleDetailResultDto extends Dto
{
    /**
     * @param  list<string>  $authors
     * @param  list<string>  $categories
     * @param  list<string>  $tags
     * @param  list<string>  $gallery
     * @param  array<string, mixed>  $jsonLd
     * @param  array<string, mixed>  $meta
     * @param  list<ArticleFlag>  $flags
     */
    public function __construct(
        public readonly string $url,
        public readonly string $title,
        public readonly ?string $intro = null,
        #[MapTo('content_html')]
        public readonly ?string $contentHtml = null,
        #[MapTo('content_text')]
        public readonly ?string $contentText = null,
        #[MapTo('published_at')]
        public readonly ?DateTimeImmutable $publishedAt = null,
        #[MapTo('updated_at')]
        public readonly ?DateTimeImmutable $updatedAt = null,
        #[MapTo('retrieved_at')]
        public readonly DateTimeImmutable $retrievedAt = new DateTimeImmutable(),
        public readonly array $authors = [],
        public readonly array $categories = [],
        public readonly array $tags = [],
        public readonly ?string $image = null,
        public readonly array $gallery = [],
        #[MapTo('canonical_url')]
        public readonly ?string $canonicalUrl = null,
        public readonly ?string $language = null,
        public readonly ?string $source = null,
        public readonly array $jsonLd = [],
        public readonly array $meta = [],
        public readonly array $flags = [],
    ) {
    }
}
