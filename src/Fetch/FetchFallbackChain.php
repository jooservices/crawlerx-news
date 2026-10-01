<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Fetch;

use JOOservices\CrawlerXNews\Contracts\FetchMethodHandler;
use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use JOOservices\CrawlerXNews\Dto\FetchMetaDto;
use JOOservices\CrawlerXNews\Dto\FetchResultDto;
use JOOservices\CrawlerXNews\Enums\FetchMethod;
use JOOservices\CrawlerXNews\Exceptions\CrawlBlockedException;
use Throwable;

final class FetchFallbackChain
{
    /**
     * @param  array<string, FetchMethodHandler>  $handlers
     */
    public function __construct(private readonly array $handlers)
    {
    }

    /**
     * @param  list<FetchMethod>  $plan
     */
    public function fetch(string $url, array $plan, ?CrawlOptionsDto $options = null): FetchResultDto
    {
        $attempts = [];
        $last = null;

        foreach ($plan as $method) {
            $handler = $this->handlers[$method->value] ?? null;
            if (! $handler instanceof FetchMethodHandler || ! $handler->supports($method)) {
                $attempts[] = $this->attempt($method, 0, 0, false, false, 'handler not registered');
                continue;
            }

            $started = (int) round(microtime(true) * 1000);

            try {
                $result = $handler->fetch($url, $method, $options);
            } catch (Throwable $exception) {
                $elapsed = (int) round(microtime(true) * 1000) - $started;
                $attempts[] = $this->attempt($method, $elapsed, 0, false, false, $exception->getMessage());
                continue;
            }

            $attempts[] = $this->attempt(
                $method,
                $result->elapsedMs,
                $result->status,
                $result->challengeDetected,
                $result->ok,
                $result->error,
            );
            $last = $result->withAttempts($attempts);

            if ($result->ok) {
                return $last;
            }
        }

        throw new CrawlBlockedException(
            'All fetch methods exhausted for URL [' . $url . '].',
            $last?->toMeta() ?? new FetchMetaDto(
                methodUsed: ($plan[0] ?? FetchMethod::Http)->value,
                elapsedMs: 0,
                challengeDetected: true,
                attempts: $attempts,
            ),
        );
    }

    /**
     * @return array{method: string, elapsed_ms: int, status: int, challenge: bool, ok: bool, error?: string|null}
     */
    private function attempt(
        FetchMethod $method,
        int $elapsedMs,
        int $status,
        bool $challenge,
        bool $ok,
        ?string $error,
    ): array {
        $row = [
            'method' => $method->value,
            'elapsed_ms' => $elapsedMs,
            'status' => $status,
            'challenge' => $challenge,
            'ok' => $ok,
        ];

        if ($error !== null && $error !== '') {
            $row['error'] = $error;
        }

        return $row;
    }
}
