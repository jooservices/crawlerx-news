<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Services;

use JOOservices\CrawlerXNews\Dto\ArticleDetailResultDto;
use JOOservices\CrawlerXNews\Dto\ArticleErrorDto;
use JOOservices\CrawlerXNews\Dto\ArticleListResultDto;
use JOOservices\CrawlerXNews\Dto\ArticleOutcomeDto;
use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use JOOservices\CrawlerXNews\Enums\ArticleErrorCode;
use JOOservices\CrawlerXNews\Enums\PageType;
use JOOservices\CrawlerXNews\Exceptions\AdapterNotFoundException;
use JOOservices\CrawlerXNews\Exceptions\AmbiguousUrlException;
use JOOservices\CrawlerXNews\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerXNews\Exceptions\CrawlParseException;
use JOOservices\CrawlerXNews\Exceptions\UnsupportedUrlException;
use Throwable;

final class CrawlRequestBuilder
{
    private ?string $site = null;

    private ?PageType $type = null;

    private ?CrawlOptionsDto $options = null;

    public function __construct(
        private readonly CrawlOrchestrator $orchestrator,
        private ?string $url = null,
    ) {
    }

    public function url(string $url): self
    {
        $clone = clone $this;
        $clone->url = $url;

        return $clone;
    }

    public function site(string $site): self
    {
        $clone = clone $this;
        $clone->site = $site;

        return $clone;
    }

    public function type(PageType $type): self
    {
        $clone = clone $this;
        $clone->type = $type;

        return $clone;
    }

    public function options(?CrawlOptionsDto $options): self
    {
        $clone = clone $this;
        $clone->options = $options;

        return $clone;
    }

    public function crawl(): ArticleListResultDto|ArticleDetailResultDto
    {
        return $this->orchestrator->crawl(
            $this->url,
            $this->site,
            $this->type,
            $this->options,
        );
    }

    public function tryCrawl(): ArticleOutcomeDto
    {
        try {
            return ArticleOutcomeDto::success($this->crawl());
        } catch (UnsupportedUrlException $exception) {
            return $this->failure(ArticleErrorCode::UnsupportedUrl, $exception->getMessage());
        } catch (AmbiguousUrlException $exception) {
            return $this->failure(ArticleErrorCode::AmbiguousUrl, $exception->getMessage());
        } catch (AdapterNotFoundException $exception) {
            return $this->failure(ArticleErrorCode::AdapterNotFound, $exception->getMessage());
        } catch (CrawlBlockedException $exception) {
            return $this->failure(ArticleErrorCode::Blocked, $exception->getMessage());
        } catch (CrawlParseException $exception) {
            return $this->failure(ArticleErrorCode::ParseFailed, $exception->getMessage());
        } catch (Throwable $exception) {
            return $this->failure(ArticleErrorCode::Unknown, $exception->getMessage());
        }
    }

    private function failure(ArticleErrorCode $code, string $message): ArticleOutcomeDto
    {
        return ArticleOutcomeDto::failure(new ArticleErrorDto(
            code: $code,
            message: $message,
            url: $this->url,
        ));
    }
}
