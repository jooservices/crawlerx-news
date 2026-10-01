<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Tests\Unit;

use JOOservices\CrawlerXNews\Dto\CrawlOptionsDto;
use JOOservices\CrawlerXNews\Dto\FetchChainDto;
use JOOservices\CrawlerXNews\Dto\FetchMetaDto;
use JOOservices\CrawlerXNews\Dto\FetchOptionsDto;
use JOOservices\CrawlerXNews\Dto\FetchResultDto;
use JOOservices\CrawlerXNews\Dto\HttpOptionsDto;
use JOOservices\CrawlerXNews\Dto\ProcessResultDto;
use JOOservices\CrawlerXNews\Dto\SiteProfile;
use JOOservices\CrawlerXNews\Enums\FetchMethod;
use JOOservices\CrawlerXNews\Enums\FetchProfile;
use JOOservices\CrawlerXNews\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerXNews\Fetch\ChallengeDetector;
use JOOservices\CrawlerXNews\Fetch\FetchFallbackChain;
use JOOservices\CrawlerXNews\Fetch\FetchPlanResolver;
use JOOservices\CrawlerXNews\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerXNews\Fetch\Handlers\FlaresolverrFetchHandler;
use JOOservices\CrawlerXNews\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerXNews\Fetch\Handlers\PlaywrightFamilyFetchHandler;
use JOOservices\CrawlerXNews\Fetch\ProcOpenProcessRunner;
use JOOservices\CrawlerXNews\Services\ClientFactory;
use PHPUnit\Framework\TestCase;

final class FetchTest extends TestCase
{
    public function testChallengeDetectorDetectsCloudflare(): void
    {
        self::assertTrue(ChallengeDetector::isChallenge('<title>Just a moment...</title>', 403));
        self::assertTrue(ChallengeDetector::isChallenge('', 403, ['cf-mitigated' => 'challenge']));
        self::assertFalse(ChallengeDetector::isChallenge('<html>normal page</html>', 200));
    }

    public function testIsUsableBody(): void
    {
        self::assertTrue(ChallengeDetector::isUsableBody('<html>content that is long enough</html>', 200));
        self::assertTrue(ChallengeDetector::isUsableBody('{"a":1}', 200));
        self::assertFalse(ChallengeDetector::isUsableBody('', 200));
        self::assertFalse(ChallengeDetector::isUsableBody('x', 404));
        self::assertFalse(ChallengeDetector::isUsableBody('{broken', 200));
    }

    public function testFetchPlanResolverUsesProfileChain(): void
    {
        $resolver = new FetchPlanResolver();
        $profile = new SiteProfile(fetchChain: FetchMethod::defaultChain());

        $default = $resolver->resolve($profile);
        self::assertSame(FetchMethod::defaultChain(), $default);

        $chainPlan = $resolver->resolve($profile, new FetchOptionsDto(
            chain: new FetchChainDto([FetchMethod::Flaresolverr]),
        ));
        self::assertSame([FetchMethod::Flaresolverr], $chainPlan);

        $profilePlan = $resolver->resolve($profile, new FetchOptionsDto(
            profile: FetchProfile::BrowserLikely,
        ));
        self::assertSame(FetchMethod::browserChain(), $profilePlan);

        $noFallback = $resolver->resolve($profile, new FetchOptionsDto(
            method: FetchMethod::Playwright,
            noFallback: true,
        ));
        self::assertSame([FetchMethod::Playwright], $noFallback);

        $startAt = $resolver->resolve($profile, new FetchOptionsDto(method: FetchMethod::Flaresolverr));
        self::assertSame([FetchMethod::Flaresolverr], $startAt);
    }

    public function testHttpFetchHandlerWithPrefetched(): void
    {
        $handler = new HttpFetchHandler(new ClientFactory());
        $result = $handler->fetch(
            'https://example.org/a',
            FetchMethod::Http,
            new CrawlOptionsDto(prefetchedHtml: ['https://example.org/a' => '<html><body><p>this is a real page body long enough to pass the usable check</p></body></html>']),
        );

        self::assertTrue($result->ok);
        self::assertSame(200, $result->status);
        self::assertStringContainsString('long enough', $result->body);
    }

    public function testHttpFetchHandlerUnusableBody(): void
    {
        $handler = new HttpFetchHandler(new ClientFactory());
        $result = $handler->fetch(
            'https://example.org/a',
            FetchMethod::Http,
            new CrawlOptionsDto(prefetchedHtml: ['https://example.org/a' => '']),
        );

        self::assertFalse($result->ok);
    }

    public function testFetchFallbackChainSucceedsOnFirstOk(): void
    {
        $http = new HttpFetchHandler(new ClientFactory());
        $chain = new FetchFallbackChain([FetchMethod::Http->value => $http]);

        $result = $chain->fetch(
            'https://example.org/a',
            [FetchMethod::Http],
            new CrawlOptionsDto(prefetchedHtml: ['https://example.org/a' => '<html><body><p>this is a real page body long enough to pass the usable check</p></body></html>']),
        );

        self::assertTrue($result->ok);
        self::assertCount(1, $result->attempts);
    }

    public function testFetchFallbackChainThrowsWhenExhausted(): void
    {
        $this->expectException(CrawlBlockedException::class);

        $chain = new FetchFallbackChain([]);
        $chain->fetch('https://example.org/a', [FetchMethod::Http]);
    }

    public function testFetchResultDtoToMeta(): void
    {
        $result = new FetchResultDto(
            ok: true,
            body: 'x',
            status: 200,
            methodUsed: FetchMethod::Http,
            elapsedMs: 5,
            challengeDetected: false,
        );

        $meta = $result->toMeta();
        self::assertInstanceOf(FetchMetaDto::class, $meta);
        self::assertSame('http', $meta->methodUsed);

        $withAttempts = $result->withAttempts([]);
        self::assertSame([], $withAttempts->attempts);
    }

    public function testFetchProfileChains(): void
    {
        self::assertSame([FetchMethod::Http], FetchProfile::HttpOnly->chain());
        self::assertSame(FetchMethod::browserChain(), FetchProfile::BrowserLikely->chain());
        self::assertSame(FetchMethod::defaultChain(), FetchProfile::Adaptive->chain());
    }

    public function testSiteProfileFromManifest(): void
    {
        $profile = SiteProfile::fromManifest('ap');

        self::assertNotEmpty($profile->fetchChain);
        self::assertSame([FetchMethod::Http, FetchMethod::Flaresolverr], array_slice($profile->fetchChain, 0, 2));
    }

    public function testSiteProfileUnknownSlug(): void
    {
        $profile = SiteProfile::fromManifest('nonexistent');

        self::assertSame(FetchMethod::defaultChain(), $profile->fetchChain);
    }

    public function testProcOpenProcessRunner(): void
    {
        $runner = new ProcOpenProcessRunner();
        $result = $runner->run(['php', '-r', 'echo "hi";']);

        self::assertSame(0, $result->exitCode);
        self::assertSame('hi', $result->stdout);
    }

    public function testProcessResultDto(): void
    {
        $dto = new ProcessResultDto(exitCode: 1, stdout: 'out', stderr: 'err');

        self::assertSame(1, $dto->exitCode);
        self::assertSame('out', $dto->stdout);
        self::assertSame('err', $dto->stderr);
    }

    public function testFetchOptionsDto(): void
    {
        $dto = new FetchOptionsDto(noFallback: true);

        self::assertTrue($dto->noFallback);
    }

    public function testHttpOptionsDto(): void
    {
        $dto = new HttpOptionsDto(timeout: 30, headers: ['X-Test' => '1']);

        self::assertSame(30, $dto->timeout);
        self::assertSame(['X-Test' => '1'], $dto->headers);
    }

    public function testPlaywrightHandlerFailsWithoutScript(): void
    {
        $handler = new PlaywrightFamilyFetchHandler(
            new FetchRuntimeConfig(playwrightScript: '/nonexistent/script.mjs'),
            new ProcOpenProcessRunner(),
        );

        $result = $handler->fetch('https://example.org/', FetchMethod::Playwright);

        self::assertFalse($result->ok);
        self::assertStringContainsString('Playwright script not found', (string) $result->error);
        self::assertTrue($handler->supports(FetchMethod::PlaywrightStealth));
        self::assertTrue($handler->supports(FetchMethod::ChromeStealth));
        self::assertFalse($handler->supports(FetchMethod::Flaresolverr));
    }

    public function testFlaresolverrHandlerFailsWithoutEndpoint(): void
    {
        $handler = new FlaresolverrFetchHandler(new FetchRuntimeConfig(flaresolverrUrl: null));

        $result = $handler->fetch('https://example.org/', FetchMethod::Flaresolverr);

        self::assertFalse($result->ok);
        self::assertStringContainsString('FLARESOLVERR_URL', (string) $result->error);
        self::assertTrue($handler->supports(FetchMethod::Flaresolverr));
    }

    public function testFetchRuntimeConfigFromEnvironment(): void
    {
        $config = FetchRuntimeConfig::fromEnvironment();

        self::assertSame('node', $config->nodeBinary);
        self::assertStringContainsString('playwright-fetch.mjs', $config->playwrightScript);
    }
}
