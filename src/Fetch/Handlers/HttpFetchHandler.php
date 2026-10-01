<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Fetch\Handlers;

use JOOservices\CrawlerXNews\Contracts\FetchMethodHandler;
use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use JOOservices\CrawlerXNews\Dto\FetchResultDto;
use JOOservices\CrawlerXNews\Enums\FetchMethod;
use JOOservices\CrawlerXNews\Fetch\ChallengeDetector;
use JOOservices\CrawlerXNews\Services\ClientFactory;
use Throwable;

final class HttpFetchHandler implements FetchMethodHandler
{
    public function __construct(private readonly ClientFactory $clientFactory)
    {
    }

    public function supports(FetchMethod $method): bool
    {
        return $method === FetchMethod::Http;
    }

    public function fetch(
        string $url,
        FetchMethod $method,
        ?CrawlOptionsDto $options = null,
    ): FetchResultDto {
        $started = (int) round(microtime(true) * 1000);

        $httpOptions = [];
        if ($options !== null) {
            if ($options->timeout !== null) {
                $httpOptions['timeout'] = $options->timeout;
            }

            if ($options->prefetchedHtml !== []) {
                $httpOptions['prefetched_html'] = $options->prefetchedHtml;
            }
        }

        try {
            $response = $this->clientFactory->factory($httpOptions)->get($url);
            $psr = $response->toPsrResponse();
            $body = (string) $psr->getBody();
            $status = $psr->getStatusCode();
        } catch (Throwable $exception) {
            return new FetchResultDto(
                ok: false,
                body: '',
                status: 0,
                methodUsed: $method,
                elapsedMs: (int) round(microtime(true) * 1000) - $started,
                challengeDetected: false,
                finalUrl: $url,
                error: $exception->getMessage(),
            );
        }

        $challenge = ChallengeDetector::isChallenge($body, $status);
        $ok = ! $challenge && ChallengeDetector::isUsableBody($body, $status);

        return new FetchResultDto(
            ok: $ok,
            body: $body,
            status: $status,
            methodUsed: $method,
            elapsedMs: (int) round(microtime(true) * 1000) - $started,
            challengeDetected: $challenge,
            finalUrl: $url,
            error: $ok ? null : ($challenge ? 'challenge page' : 'unusable HTTP body'),
        );
    }
}
