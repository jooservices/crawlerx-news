<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Dto;

use JOOservices\CrawlerXNews\Enums\FetchMethod;
use JOOservices\Dto\Core\Dto;

/**
 * Fetch profile for a site adapter: the ordered fallback chain and default
 * HTTP headers. Mirrors the per-site manifest fetch_chain / browser_headers.
 */
final class SiteProfile extends Dto
{
    /**
     * @param  list<FetchMethod>  $fetchChain
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly array $fetchChain,
        public readonly int $timeout = 30,
        public readonly array $headers = [],
    ) {
    }

    public static function fromManifest(string $slug): self
    {
        $manifestPath = dirname(__DIR__, 2) . '/src/Adapters/' . $slug . '/manifest.json';
        $chain = FetchMethod::defaultChain();
        $timeout = 30;
        $headers = [];

        if (is_file($manifestPath)) {
            $json = file_get_contents($manifestPath);
            $decoded = $json === false ? null : json_decode($json, true);

            if (is_array($decoded)) {
                $declaredChain = $decoded['fetch_chain'] ?? null;
                if (is_array($declaredChain)) {
                    $methods = [];
                    foreach ($declaredChain as $method) {
                        if (is_string($method)) {
                            $enum = FetchMethod::tryFrom($method);
                            if ($enum !== null) {
                                $methods[] = $enum;
                            }
                        }
                    }
                    if ($methods !== []) {
                        $chain = $methods;
                    }
                }

                if (isset($decoded['timeout']) && is_int($decoded['timeout']) && $decoded['timeout'] > 0) {
                    $timeout = $decoded['timeout'];
                }

                if (isset($decoded['browser_headers']) && is_array($decoded['browser_headers'])) {
                    foreach ($decoded['browser_headers'] as $name => $value) {
                        if (is_string($name) && is_scalar($value)) {
                            $headers[$name] = (string) $value;
                        }
                    }
                }
            }
        }

        return new self(fetchChain: $chain, timeout: $timeout, headers: $headers);
    }
}
