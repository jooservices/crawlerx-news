<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Dto;

use JOOservices\Dto\Core\Dto;

/**
 * CSS selectors used by a site adapter to locate article fields in HTML.
 * Empty strings mean the field is not available via HTML for this site.
 */
final class Selectors extends Dto
{
    /**
     * @param  list<string>  $authorCandidates
     */
    public function __construct(
        public readonly string $title,
        public readonly string $intro,
        public readonly string $content,
        public readonly string $publishedAt,
        public readonly string $canonicalUrl = '',
        public readonly string $image = '',
        public readonly string $language = '',
        public readonly array $authorCandidates = [],
        /** @var list<string> */
        public readonly array $categoryCandidates = [],
        /** @var list<string> */
        public readonly array $tagCandidates = [],
    ) {
    }
}
