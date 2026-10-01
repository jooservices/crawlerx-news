<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Registry;

use JOOservices\CrawlerXNews\Adapters\AbstractNewsCrawler;
use JOOservices\CrawlerXNews\Services\ClientFactory;

/**
 * Registry of registered site adapters, keyed by site slug.
 */
final class AdapterRegistry
{
    /**
     * @var array<string, AbstractNewsCrawler>
     */
    private array $adapters = [];

    public function __construct(private readonly ClientFactory $clientFactory)
    {
    }

    public function register(AbstractNewsCrawler $adapter): void
    {
        $this->adapters[$adapter->name()] = $adapter;
    }

    public function has(string $slug): bool
    {
        return isset($this->adapters[$slug]);
    }

    public function get(string $slug): ?AbstractNewsCrawler
    {
        return $this->adapters[$slug] ?? null;
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->adapters);
    }

    public function make(string $class): AbstractNewsCrawler
    {
        $adapter = new $class($this->clientFactory);

        if (! $adapter instanceof AbstractNewsCrawler) {
            throw new \InvalidArgumentException('Adapter class must extend AbstractNewsCrawler: ' . $class);
        }

        return $adapter;
    }
}
