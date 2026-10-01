<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Services;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Client\HttpClient;
use JOOservices\CrawlerXNews\Http\ClientCrawlHttpClient;
use JOOservices\CrawlerXNews\Http\CrawlHttpClient;
use JOOservices\CrawlerXNews\Http\PrefetchedCrawlHttpClient;

final class ClientFactory
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function factory(array $options = []): CrawlHttpClient
    {
        if (isset($options['prefetched_html']) && is_array($options['prefetched_html'])) {
            $map = [];
            foreach ($options['prefetched_html'] as $url => $html) {
                if (is_string($url) && is_string($html)) {
                    $map[$url] = $html;
                }
            }

            return new PrefetchedCrawlHttpClient($map);
        }

        $builder = ClientBuilder::create();

        if (isset($options['base_uri']) && is_string($options['base_uri'])) {
            $builder->withBaseUri($options['base_uri']);
        }

        if (isset($options['timeout']) && is_int($options['timeout'])) {
            $builder->withTimeout($options['timeout']);
        }

        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                if (! is_scalar($value)) {
                    continue;
                }

                $builder->withHeader((string) $name, (string) $value);
            }
        }

        /** @var HttpClient $client */
        $client = $builder->build();

        return new ClientCrawlHttpClient($client);
    }
}
