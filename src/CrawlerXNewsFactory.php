<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\CrawlerXNews\Dto\SiteProfile;
use JOOservices\CrawlerXNews\Enums\FetchMethod;
use JOOservices\CrawlerXNews\Fetch\FetchFallbackChain;
use JOOservices\CrawlerXNews\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerXNews\Fetch\Handlers\FlaresolverrFetchHandler;
use JOOservices\CrawlerXNews\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerXNews\Fetch\Handlers\PlaywrightFamilyFetchHandler;
use JOOservices\CrawlerXNews\Fetch\ProcOpenProcessRunner;
use JOOservices\CrawlerXNews\Registry\AdapterRegistry;
use JOOservices\CrawlerXNews\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerXNews\Services\ClientFactory;
use JOOservices\CrawlerXNews\Services\CrawlOrchestrator;
use JOOservices\CrawlerXNews\Services\CrawlerXNewsService;
use JOOservices\CrawlerXNews\Services\UrlClassifier;

final class CrawlerXNewsFactory
{
    private static ?CrawlOrchestrator $orchestrator = null;

    private static ?string $adapterDir = null;

    public static function create(): CrawlOrchestrator
    {
        if (self::$orchestrator instanceof CrawlOrchestrator) {
            return self::$orchestrator;
        }

        $dir = self::$adapterDir ?? __DIR__ . '/Adapters';
        $manifestRegistry = new FileAdapterManifestRegistry();
        $manifests = $manifestRegistry->discover($dir);

        $clientFactory = new ClientFactory();
        $registry = new AdapterRegistry($clientFactory);
        $fetchChain = self::defaultFetchChain($clientFactory);

        foreach ($manifests as $manifest) {
            $adapter = $registry->make($manifest['class']);
            $adapter->setFetchChain($fetchChain, SiteProfile::fromManifest($manifest['slug']));
            $registry->register($adapter);
        }

        self::$orchestrator = new CrawlOrchestrator(
            new UrlClassifier($manifests),
            new CrawlerXNewsService($registry),
        );

        return self::$orchestrator;
    }

    public static function setAdapterDir(string $dir): void
    {
        self::$adapterDir = $dir;
        self::$orchestrator = null;
    }

    public static function reset(): void
    {
        self::$orchestrator = null;
        self::$adapterDir = null;
    }

    private static function defaultFetchChain(ClientFactory $clientFactory): FetchFallbackChain
    {
        $runtime = FetchRuntimeConfig::fromEnvironment();

        if (ClientBuilder::isFaked()) {
            return new FetchFallbackChain([
                FetchMethod::Http->value => new HttpFetchHandler($clientFactory),
            ]);
        }

        $runner = new ProcOpenProcessRunner();
        $playwright = new PlaywrightFamilyFetchHandler($runtime, $runner);

        return new FetchFallbackChain([
            FetchMethod::Http->value => new HttpFetchHandler($clientFactory),
            FetchMethod::Playwright->value => $playwright,
            FetchMethod::PlaywrightStealth->value => $playwright,
            FetchMethod::ChromeStealth->value => $playwright,
            FetchMethod::Flaresolverr->value => new FlaresolverrFetchHandler($runtime),
        ]);
    }
}
