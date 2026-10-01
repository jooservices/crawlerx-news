<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Registry;

use JsonException;

/**
 * Discovers adapter classes by scanning the Adapters directory manifest.
 * Each adapter folder carries a manifest.json declaring the adapter class.
 */
final class FileAdapterManifestRegistry
{
    /**
     * @return list<array{slug: string, hosts: list<string>, page_rules: array<string, list<string>>, class: string}>
     */
    public function discover(string $adapterDir): array
    {
        $manifests = [];
        $entries = glob($adapterDir . '/*/manifest.json');

        if ($entries === false) {
            return [];
        }

        foreach ($entries as $file) {
            $manifest = $this->readManifest($file);
            if ($manifest === null) {
                continue;
            }

            $adapter = $manifest['adapter'] ?? null;
            $class = is_array($adapter) ? ($adapter['class'] ?? null) : null;
            $slug = $manifest['slug'] ?? null;

            if (! is_string($class) || ! is_string($slug) || ! class_exists($class)) {
                continue;
            }

            $hostsRaw = $manifest['hosts'] ?? [];
            $hosts = is_array($hostsRaw)
                ? array_values(array_filter($hostsRaw, fn(mixed $host): bool => is_string($host)))
                : [];
            $pageRules = $manifest['page_rules'] ?? [];
            $pageRules = is_array($pageRules) ? $pageRules : [];

            $manifests[] = [
                'slug' => $slug,
                'hosts' => $hosts,
                'page_rules' => $this->normalizePageRules($pageRules),
                'class' => $class,
            ];
        }

        usort($manifests, fn(array $a, array $b): int => strcmp($a['slug'], $b['slug']));

        return $manifests;
    }

    /**
     * @param  array<mixed, mixed>  $rules
     * @return array<string, list<string>>
     */
    private function normalizePageRules(array $rules): array
    {
        $normalized = ['article' => [], 'listing' => []];

        foreach (['article', 'listing'] as $type) {
            $fragments = $rules[$type] ?? [];
            if (! is_array($fragments)) {
                continue;
            }

            $normalized[$type] = array_values(array_map(
                'strval',
                array_filter($fragments, fn(mixed $fragment): bool => is_string($fragment)),
            ));
        }

        return $normalized;
    }

    /**
     * @return array<mixed, mixed>|null
     */
    private function readManifest(string $file): ?array
    {
        $json = file_get_contents($file);
        if ($json === false) {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }
}
