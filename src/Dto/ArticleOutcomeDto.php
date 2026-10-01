<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Dto;

use JOOservices\Dto\Core\Dto;

final class ArticleOutcomeDto extends Dto
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?ArticleListResultDto $list = null,
        public readonly ?ArticleDetailResultDto $article = null,
        public readonly ?ArticleErrorDto $error = null,
    ) {
    }

    public static function success(ArticleListResultDto|ArticleDetailResultDto $result): self
    {
        return $result instanceof ArticleListResultDto
            ? new self(ok: true, list: $result)
            : new self(ok: true, article: $result);
    }

    public static function failure(ArticleErrorDto $error): self
    {
        return new self(ok: false, error: $error);
    }

    public function failed(): bool
    {
        return ! $this->ok;
    }
}
