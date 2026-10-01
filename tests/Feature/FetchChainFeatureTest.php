<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Feature;

use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use JOOservices\CrawlerXNews\Dto\FetchChainDto;
use JOOservices\CrawlerXNews\Dto\FetchOptionsDto;
use JOOservices\CrawlerXNews\Dto\SiteProfile;
use JOOservices\CrawlerXNews\Enums\FetchMethod;
use JOOservices\CrawlerXNews\Enums\FetchProfile;
use JOOservices\CrawlerXNews\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerXNews\Contracts\ProcessRunner;
use JOOservices\CrawlerXNews\Dto\ProcessResultDto;
use JOOservices\CrawlerXNews\Fetch\ChallengeDetector;
use JOOservices\CrawlerXNews\Fetch\FetchFallbackChain;
use JOOservices\CrawlerXNews\Fetch\FetchPlanResolver;
use JOOservices\CrawlerXNews\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerXNews\Fetch\Handlers\FlaresolverrFetchHandler;
use JOOservices\CrawlerXNews\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerXNews\Fetch\Handlers\PlaywrightFamilyFetchHandler;
use JOOservices\CrawlerXNews\Fetch\ProcOpenProcessRunner;
use JOOservices\CrawlerXNews\Services\ClientFactory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the browser-fetch plumbing from the Feature suite so coverage
 * spans both suites. Network-free: uses prefetched HTML and mock handlers.
 */
final class FetchChainFeatureTest extends TestCase
{
    public function testFallbackChainViaFacadeProfile(): void
    {
        $handler = new HttpFetchHandler(new ClientFactory());
        $chain = new FetchFallbackChain([FetchMethod::Http->value => $handler]);
        $resolver = new FetchPlanResolver();

        $profile = new SiteProfile(fetchChain: FetchMethod::httpOnlyChain());
        $plan = $resolver->resolve($profile, new FetchOptionsDto(profile: FetchProfile::HttpOnly));

        $result = $chain->fetch(
            'https://example.org/a',
            $plan,
            new CrawlOptionsDto(prefetchedHtml: ['https://example.org/a' => '<html><body><p>long enough body for usable check here</p></body></html>']),
        );

        self::assertTrue($result->ok);
        self::assertSame([FetchMethod::Http], $plan);
    }

    public function testFallbackChainSkipsMissingHandler(): void
    {
        $handler = new HttpFetchHandler(new ClientFactory());
        $chain = new FetchFallbackChain([FetchMethod::Http->value => $handler]);

        $result = $chain->fetch(
            'https://example.org/a',
            [FetchMethod::Playwright, FetchMethod::Http],
            new CrawlOptionsDto(prefetchedHtml: ['https://example.org/a' => '<html><body><p>long enough body for usable check here</p></body></html>']),
        );

        self::assertTrue($result->ok);
        self::assertCount(2, $result->attempts);
        self::assertSame('playwright', $result->attempts[0]['method']);
    }

    public function testFallbackChainThrowsWhenAllExhausted(): void
    {
        $this->expectException(CrawlBlockedException::class);

        $chain = new FetchFallbackChain([]);
        $chain->fetch('https://example.org/', [FetchMethod::Flaresolverr]);
    }

    public function testChallengeDetectionAcrossSuites(): void
    {
        self::assertTrue(ChallengeDetector::isChallenge('<title>Just a moment...</title>', 503));
        self::assertFalse(ChallengeDetector::isChallenge('<title>News</title>', 200));
        self::assertTrue(ChallengeDetector::isUsableBody('{"status":"ok"}', 200));
        self::assertFalse(ChallengeDetector::isUsableBody('', 200));
    }

    public function testExplicitFetchChainDto(): void
    {
        $resolver = new FetchPlanResolver();
        $profile = new SiteProfile(fetchChain: FetchMethod::defaultChain());

        $plan = $resolver->resolve($profile, new FetchOptionsDto(
            chain: new FetchChainDto([FetchMethod::ChromeStealth, FetchMethod::Flaresolverr]),
        ));

        self::assertSame([FetchMethod::ChromeStealth, FetchMethod::Flaresolverr], $plan);
    }

    public function testProcOpenProcessRunnerRunsCommand(): void
    {
        $runner = new ProcOpenProcessRunner();
        $result = $runner->run(['php', '-r', 'echo "output";'], 30);

        self::assertSame(0, $result->exitCode);
        self::assertSame('output', $result->stdout);
    }

    public function testPlaywrightHandlerMissingScript(): void
    {
        $handler = new PlaywrightFamilyFetchHandler(
            new FetchRuntimeConfig(playwrightScript: '/no/such/script.mjs'),
            new ProcOpenProcessRunner(),
        );

        $result = $handler->fetch('https://example.org/', FetchMethod::PlaywrightStealth);

        self::assertFalse($result->ok);
        self::assertStringContainsString('Playwright script not found', (string) $result->error);
    }

    public function testFlaresolverrHandlerMissingEndpoint(): void
    {
        $handler = new FlaresolverrFetchHandler(new FetchRuntimeConfig(flaresolverrUrl: null));

        $result = $handler->fetch('https://example.org/', FetchMethod::Flaresolverr);

        self::assertFalse($result->ok);
        self::assertStringContainsString('FLARESOLVERR_URL', (string) $result->error);
    }

    public function testPlaywrightHandlerSuccessPath(): void
    {
        $runner = new class implements ProcessRunner {
            public function run(array $command, int $timeoutSeconds = 120, ?string $cwd = null): ProcessResultDto
            {
                $html = '<html><body><p>this is a real rendered page with enough content</p></body></html>';

                return new ProcessResultDto(
                    exitCode: 0,
                    stdout: json_encode(['html' => $html, 'status' => 200, 'finalUrl' => 'https://example.org/', 'challenge' => false, 'elapsedMs' => 42], JSON_THROW_ON_ERROR),
                );
            }
        };

        $script = tempnam(sys_get_temp_dir(), 'cxn-pw-test') . '.mjs';
        file_put_contents($script, '// fake playwright script');

        try {
            $handler = new PlaywrightFamilyFetchHandler(
                new FetchRuntimeConfig(playwrightScript: $script),
                $runner,
            );

            $result = $handler->fetch('https://example.org/', FetchMethod::ChromeStealth);

            self::assertTrue($result->ok);
            self::assertStringContainsString('rendered page', $result->body);
            self::assertSame(200, $result->status);
        } finally {
            unlink($script);
        }
    }

    public function testFlaresolverrHandlerSuccessPath(): void
    {
        $client = new class implements \Psr\Http\Client\ClientInterface {
            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(200, [], json_encode([
                    'status' => 'ok',
                    'solution' => [
                        'response' => '<html><body><p>this is a real flaresolverr rendered page content</p></body></html>',
                        'status' => 200,
                        'url' => 'https://example.org/',
                    ],
                ], JSON_THROW_ON_ERROR));
            }
        };

        $handler = new FlaresolverrFetchHandler(
            new FetchRuntimeConfig(flaresolverrUrl: 'http://flare:8191/v1'),
            $client,
        );

        $result = $handler->fetch('https://example.org/', FetchMethod::Flaresolverr);

        self::assertTrue($result->ok);
        self::assertStringContainsString('flaresolverr rendered', $result->body);
    }

    public function testFlaresolverrHandlerChallengeResponse(): void
    {
        $client = new class implements \Psr\Http\Client\ClientInterface {
            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(200, [], json_encode([
                    'status' => 'error',
                    'message' => 'blocked',
                    'solution' => ['response' => '<title>Just a moment...</title>', 'status' => 403],
                ], JSON_THROW_ON_ERROR));
            }
        };

        $handler = new FlaresolverrFetchHandler(
            new FetchRuntimeConfig(flaresolverrUrl: 'http://flare:8191/v1'),
            $client,
        );

        $result = $handler->fetch('https://example.org/', FetchMethod::Flaresolverr);

        self::assertFalse($result->ok);
        self::assertTrue($result->challengeDetected);
    }
}
