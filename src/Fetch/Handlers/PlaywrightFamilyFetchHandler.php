<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Fetch\Handlers;

use JOOservices\CrawlerXNews\Contracts\FetchMethodHandler;
use JOOservices\CrawlerXNews\Contracts\ProcessRunner;
use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use JOOservices\CrawlerXNews\Dto\FetchResultDto;
use JOOservices\CrawlerXNews\Enums\FetchMethod;
use JOOservices\CrawlerXNews\Fetch\ChallengeDetector;
use JOOservices\CrawlerXNews\Fetch\FetchRuntimeConfig;

final class PlaywrightFamilyFetchHandler implements FetchMethodHandler
{
    public function __construct(
        private readonly FetchRuntimeConfig $runtime,
        private readonly ProcessRunner $runner,
    ) {
    }

    public function supports(FetchMethod $method): bool
    {
        return in_array($method, [
            FetchMethod::Playwright,
            FetchMethod::PlaywrightStealth,
            FetchMethod::ChromeStealth,
        ], true);
    }

    public function fetch(
        string $url,
        FetchMethod $method,
        ?CrawlOptionsDto $options = null,
    ): FetchResultDto {
        $started = (int) round(microtime(true) * 1000);
        $script = $this->runtime->playwrightScript;
        if ($script === '' || ! is_file($script)) {
            return $this->fail($method, $started, $url, 'Playwright script not found: ' . $script);
        }

        $config = [
            'url' => $url,
            'waitMs' => 8000,
            'browser' => 'chromium',
            'headless' => true,
            'navigationTimeoutMs' => 90000,
            'viewport' => ['width' => 1440, 'height' => 900],
            'locale' => 'en-US',
            'stealthEnabled' => true,
            'stealthLevel' => $method === FetchMethod::Playwright ? 'minimal' : 'enhanced',
        ];

        $configPath = tempnam(sys_get_temp_dir(), 'cxn-pw-');
        if ($configPath === false) {
            return $this->fail($method, $started, $url, 'Unable to create Playwright config tempfile');
        }

        file_put_contents($configPath, json_encode($config, JSON_THROW_ON_ERROR));

        try {
            $result = $this->runner->run(
                [$this->runtime->nodeBinary, $script, '--config=' . $configPath],
                120,
            );
        } finally {
            if (is_file($configPath)) {
                unlink($configPath);
            }
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($result->stdout, true);
        if (! is_array($decoded)) {
            return $this->fail(
                $method,
                $started,
                $url,
                $result->stderr !== '' ? $result->stderr : 'Playwright sidecar returned invalid JSON',
            );
        }

        $body = is_string($decoded['html'] ?? null) ? $decoded['html'] : '';
        $status = is_numeric($decoded['status'] ?? null) ? (int) $decoded['status'] : 0;
        $finalUrl = is_string($decoded['finalUrl'] ?? null) ? $decoded['finalUrl'] : $url;
        $challenge = (bool) ($decoded['challenge'] ?? false) || ChallengeDetector::isChallenge($body, $status);
        $ok = $result->exitCode === 0 && ! $challenge && ChallengeDetector::isUsableBody($body, $status > 0 ? $status : 200);

        return new FetchResultDto(
            ok: $ok,
            body: $body,
            status: $status > 0 ? $status : ($ok ? 200 : 0),
            methodUsed: $method,
            elapsedMs: is_numeric($decoded['elapsedMs'] ?? null)
                ? (int) $decoded['elapsedMs']
                : (int) round(microtime(true) * 1000) - $started,
            challengeDetected: $challenge,
            finalUrl: $finalUrl,
            error: $ok ? null : (is_string($decoded['error'] ?? null) ? $decoded['error'] : 'playwright fetch failed'),
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
