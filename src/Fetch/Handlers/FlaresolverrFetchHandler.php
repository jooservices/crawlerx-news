<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Fetch\Handlers;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\CrawlerXNews\Contracts\FetchMethodHandler;
use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use JOOservices\CrawlerXNews\Dto\FetchResultDto;
use JOOservices\CrawlerXNews\Enums\FetchMethod;
use JOOservices\CrawlerXNews\Fetch\ChallengeDetector;
use JOOservices\CrawlerXNews\Fetch\FetchRuntimeConfig;
use Nyholm\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use Throwable;

final class FlaresolverrFetchHandler implements FetchMethodHandler
{
    public function __construct(
        private readonly FetchRuntimeConfig $runtime,
        private readonly ?ClientInterface $client = null,
    ) {
    }

    public function supports(FetchMethod $method): bool
    {
        return $method === FetchMethod::Flaresolverr;
    }

    public function fetch(
        string $url,
        FetchMethod $method,
        ?CrawlOptionsDto $options = null,
    ): FetchResultDto {
        $started = (int) round(microtime(true) * 1000);
        $endpoint = $this->runtime->flaresolverrUrl;
        if ($endpoint === null || $endpoint === '') {
            return $this->fail($method, $started, $url, 'CRAWLERXNEWS_FLARESOLVERR_URL is not set');
        }

        $timeoutSeconds = $options->timeout ?? 30;
        $maxTimeoutMs = max(1, $timeoutSeconds) * 1000;

        $payload = json_encode([
            'cmd' => 'request.get',
            'url' => $url,
            'maxTimeout' => $maxTimeoutMs,
        ], JSON_THROW_ON_ERROR);

        try {
            $client = $this->client ?? ClientBuilder::create()->withTimeout($timeoutSeconds + 30)->build();
            $response = $client->sendRequest(new Request('POST', $endpoint, [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ], $payload));
            $raw = (string) $response->getBody();
        } catch (Throwable $exception) {
            return $this->fail($method, $started, $url, 'FlareSolverr request failed: ' . $exception->getMessage());
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $this->fail($method, $started, $url, 'FlareSolverr returned invalid JSON');
        }

        $status = is_string($decoded['status'] ?? null) ? $decoded['status'] : '';
        $solution = is_array($decoded['solution'] ?? null) ? $decoded['solution'] : [];
        $html = is_string($solution['response'] ?? null) ? $solution['response'] : '';
        $httpStatus = is_numeric($solution['status'] ?? null) ? (int) $solution['status'] : 0;
        $challenge = $status !== 'ok' || ChallengeDetector::isChallenge($html, $httpStatus);
        $ok = $status === 'ok' && ! $challenge && ChallengeDetector::isUsableBody($html, $httpStatus > 0 ? $httpStatus : 200);

        return new FetchResultDto(
            ok: $ok,
            body: $html,
            status: $httpStatus > 0 ? $httpStatus : ($ok ? 200 : 0),
            methodUsed: $method,
            elapsedMs: (int) round(microtime(true) * 1000) - $started,
            challengeDetected: $challenge,
            finalUrl: is_string($solution['url'] ?? null) ? $solution['url'] : $url,
            error: $ok ? null : (is_string($decoded['message'] ?? null) ? $decoded['message'] : 'flaresolverr failed'),
        );
    }

    private function fail(FetchMethod $method, int $started, string $url, string $error): FetchResultDto
    {
        return new FetchResultDto(
            ok: false,
            body: '',
            status: 0,
            methodUsed: $method,
            elapsedMs: (int) round(microtime(true) * 1000) - $started,
            challengeDetected: false,
            finalUrl: $url,
            error: $error,
        );
    }
}
