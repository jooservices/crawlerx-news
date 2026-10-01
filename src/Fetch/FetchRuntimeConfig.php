<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Fetch;

final readonly class FetchRuntimeConfig
{
    public function __construct(
        public string $nodeBinary = 'node',
        public string $playwrightScript = '',
        public ?string $flaresolverrUrl = null,
    ) {
    }

    public static function fromEnvironment(?string $packageRoot = null): self
    {
        $root = $packageRoot ?? dirname(__DIR__, 2);
        $node = getenv('CRAWLERXNEWS_NODE');
        $playwright = getenv('CRAWLERXNEWS_PLAYWRIGHT_SCRIPT');
        $flare = getenv('CRAWLERXNEWS_FLARESOLVERR_URL');

        return new self(
            nodeBinary: is_string($node) && $node !== '' ? $node : 'node',
            playwrightScript: is_string($playwright) && $playwright !== ''
                ? $playwright
                : $root . '/scripts/playwright-fetch.mjs',
            flaresolverrUrl: is_string($flare) && $flare !== '' ? $flare : null,
        );
    }
}
